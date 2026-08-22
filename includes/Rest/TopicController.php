<?php
/**
 * Topic reads.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * `GET /topics?tag={id}` and `GET /topics/{id}` — a tag's threads, and one thread.
 *
 * ## The collection is a tag filter, not a feed
 *
 * `tag` is required, so this route cannot become an unfiltered cross-forum list of
 * everything on the site. That is a contract decision rather than an implementation
 * one: the brief has no screen for a global feed, the design deliberately declined a
 * cross-forum "Latest", and a route that answered without a filter would be the same
 * feature arriving through the back door.
 *
 * ⚠ **An unknown tag answers 200 with an empty array**, exactly as a tag used only on
 * topics in a forum this reader cannot open does. Distinguishing them would turn the
 * route into a way to enumerate the vocabulary of a private forum one ID at a time —
 * the same reason `Rest\AccessPolicy` answers 404 for everything a reader may not know
 * about.
 *
 * ## The singular route carries the opening post; the collection does not
 *
 * A list of fifty threads that each carried their opening post would be a large
 * response to render a title. `content` is a property of reading one thread.
 *
 * ## The three state routes carry no policy
 *
 * `PUT /topics/{id}/read`, `PUT|DELETE /topics/{id}/favorite` and
 * `PUT|DELETE /topics/{id}/subscription` read an ID, read a verb, and hand both to
 * Rest\StateService. Whether the feature is on, whether this member may have this
 * thread, and whether the relationship is already where they asked for it are all
 * settled there, in one place, for all four state routes on the API — see that class
 * for why they are not settled here.
 *
 * ⚠ **One endpoint per path, not two.** `PUT` and `DELETE` differ by a single boolean
 * and answer with the same entity, so the verb is read in the handler rather than
 * declared twice. What they have in common is the contract: both are idempotent, and
 * both return the *current* topic — so the app is told the authoritative
 * `is_favorite`, `is_subscribed` and `is_unread` after the write rather than assuming
 * what it asked for took.
 *
 * ## Public route, private answer
 *
 * Both read routes are declared through `Rest\RequestBounds::readable_route()`, which
 * owns the open permission callback and the reason for it. Which class answers instead is
 * per-route and belongs here: `Rest\AccessPolicy` for the singular route,
 * `Rest\CollectionVisibility` inside the query for the collection — before
 * `found_posts`, so the total describes the rows.
 *
 * @since 0.6.0
 */
class TopicController implements ControllerInterface {

	/**
	 * The two verbs an idempotent state route answers.
	 *
	 * ⚠ Not `WP_REST_Server::EDITABLE`, which is `POST, PUT, PATCH` — a favourite is
	 * set with `PUT` and cleared with `DELETE`, and neither `POST` nor `PATCH` means
	 * anything for a relationship that either exists or does not.
	 *
	 * @var string
	 * @since 0.6.0
	 */
	private const SETTABLE = 'PUT, DELETE';

	/**
	 * The one verb the read-position route answers.
	 *
	 * `PUT` alone, and again not `WP_REST_Server::EDITABLE`: reporting how far a
	 * reader got is putting a value at a known address, and `POST` would invite an app
	 * to treat it as appending to a history that is not kept.
	 *
	 * @var string
	 * @since 0.6.0
	 */
	private const MARKABLE = 'PUT';

	/**
	 * Who may read what.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

	/**
	 * Whether there is a vocabulary to filter by at all.
	 *
	 * @var FeatureGate
	 * @since 0.6.0
	 */
	private FeatureGate $features;

	/**
	 * Collection arguments.
	 *
	 * @var CollectionRepository
	 * @since 0.6.0
	 */
	private CollectionRepository $collections;

	/**
	 * Topic rows.
	 *
	 * @var TopicSerializer
	 * @since 0.6.0
	 */
	private TopicSerializer $topics;

	/**
	 * What a request may ask for.
	 *
	 * @var RequestBounds
	 * @since 0.6.0
	 */
	private RequestBounds $bounds;

	/**
	 * What comes back.
	 *
	 * @var ResponseFactory
	 * @since 0.6.0
	 */
	private ResponseFactory $responses;

	/**
	 * What a member may change about their own relationship to a thread.
	 *
	 * @var StateService
	 * @since 0.6.0
	 */
	private StateService $state;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param AccessPolicy         $access      Who may read what.
	 * @param FeatureGate          $features    Whether tagging is on.
	 * @param CollectionRepository $collections Collection arguments.
	 * @param TopicSerializer      $topics      Topic rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 * @param StateService         $state       Personal state changes.
	 */
	public function __construct(
		AccessPolicy $access,
		FeatureGate $features,
		CollectionRepository $collections,
		TopicSerializer $topics,
		RequestBounds $bounds,
		ResponseFactory $responses,
		StateService $state
	) {
		$this->access      = $access;
		$this->features    = $features;
		$this->collections = $collections;
		$this->topics      = $topics;
		$this->bounds      = $bounds;
		$this->responses   = $responses;
		$this->state       = $state;
	}

	/**
	 * The routes this controller answers.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
		return array(
			'/topics'               => $this->bounds->readable_route(
				array( $this, 'get_tag_collection' ),
				$this->bounds->collection_args() + array(
					// Required, so WordPress refuses a bare `/topics` with
					// `rest_missing_callback_param` before the callback runs.
					'tag' => $this->bounds->integer_arg(
						array(
							'required' => true,
							'minimum'  => 1,
						)
					),
				),
			),
			'/topics/(?P<id>[\d]+)' => $this->bounds->readable_route(
				array( $this, 'get_item' ),
				array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				) + $this->bounds->avatar_args()
			),
		) + $this->state_routes();
	}

	/**
	 * The three routes a member uses to change their own relationship to a thread.
	 *
	 * Kept apart from the read routes because they are declared differently in every
	 * way that matters — a real permission callback, verbs that are not `GET`, and no
	 * caller who is not signed in — and because a reader looking for "what can the app
	 * write here" should find the answer in one list rather than by scanning for the
	 * ones that are not `readable_route()`.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function state_routes(): array {
		$id = array( 'id' => $this->bounds->integer_arg( array( 'required' => true ) ) );

		return array(
			'/topics/(?P<id>[\d]+)/read'         => $this->bounds->authenticated_route(
				array( $this, 'mark_read' ),
				array( $this->responses, 'authenticated' ),
				$id + $this->bounds->avatar_args() + array(
					// `required`, so a cursor-less request is refused with
					// `rest_missing_callback_param` before the callback runs.
					//
					// ⚠ **No `sanitize_text_field`**, which `string_arg()` would give it.
					// It would be sanitising a base64url payload and an HMAC, and anything
					// it changed would fail the signature check as a forgery rather than as
					// the mangling it was. Unread\ReadCursor::parse() refuses everything it
					// did not issue, so a sanitizer in front of it can only turn a good
					// token bad.
					'read_cursor' => $this->bounds->string_arg(
						array(
							'required'          => true,
							'sanitize_callback' => static fn( $value ): string => is_scalar( $value ) ? (string) $value : '',
						)
					),
				),
				self::MARKABLE
			),
			'/topics/(?P<id>[\d]+)/favorite'     => $this->bounds->authenticated_route(
				array( $this, 'favorite' ),
				array( $this->responses, 'authenticated' ),
				$id + $this->bounds->avatar_args(),
				self::SETTABLE
			),
			'/topics/(?P<id>[\d]+)/subscription' => $this->bounds->authenticated_route(
				array( $this, 'subscription' ),
				array( $this->responses, 'authenticated' ),
				$id + $this->bounds->avatar_args(),
				self::SETTABLE
			),
		);
	}

	/**
	 * Mark this thread read as far as the cursor the app was given.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function mark_read( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$written  = $this->state->mark_read(
			$topic_id,
			$this->bounds->caller(),
			(string) $request->get_param( 'read_cursor' )
		);

		return true === $written ? $this->current( $topic_id, $request ) : $written;
	}

	/**
	 * Favourite this thread, or take it out of the member's favourites.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function favorite( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$written  = $this->state->set_favorite( $topic_id, $this->bounds->caller(), $this->wanted( $request ) );

		return true === $written ? $this->current( $topic_id, $request ) : $written;
	}

	/**
	 * Subscribe to this thread, or unsubscribe from it.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function subscription( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$written  = $this->state->set_topic_subscription( $topic_id, $this->bounds->caller(), $this->wanted( $request ) );

		return true === $written ? $this->current( $topic_id, $request ) : $written;
	}

	/**
	 * Whether the verb asks for the relationship to exist afterwards.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	private function wanted( \WP_REST_Request $request ): bool {
		return 'DELETE' !== strtoupper( $request->get_method() );
	}

	/**
	 * The thread as it stands after a state change.
	 *
	 * ⚠ **Serialized after the write, never assembled from what was asked for.** The
	 * contract promises the app authoritative `is_favorite`, `is_subscribed` and
	 * `is_unread` values, and a response built from the request would be right until
	 * the first time a filter or another device disagreed. Marked as a mutation so it
	 * is never stored by a shared cache.
	 *
	 * @since 0.6.0
	 *
	 * @param int              $topic_id Thread.
	 * @param \WP_REST_Request $request  Request.
	 * @return \WP_REST_Response
	 */
	private function current( int $topic_id, \WP_REST_Request $request ): \WP_REST_Response {
		return $this->responses->item(
			$this->topics->topic( $topic_id, $this->bounds->avatar_size( $request ) ),
			200,
			true
		);
	}

	/**
	 * One page of the threads carrying a tag.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_tag_collection( \WP_REST_Request $request ) {
		$enabled = $this->features->topic_tags();

		if ( true !== $enabled ) {
			return $enabled;
		}

		$page = $this->collections->tag_topics(
			(int) $request->get_param( 'tag' ),
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->topics->topics( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}

	/**
	 * One thread.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$allowed  = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		return $this->responses->item( $this->topics->topic( $topic_id, $this->bounds->avatar_size( $request ) ) );
	}
}
