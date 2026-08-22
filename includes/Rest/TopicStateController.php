<?php
/**
 * A member's own state on a thread.
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
 * Read position, favourite and subscription, for the thread in the path.
 *
 * ## Why these are not on Rest\TopicController
 *
 * They were, until this task, and PHPMD is what moved them: adding the edit route put
 * that controller at coupling 11 and 355 lines against gates of 10 and 350. The cut it
 * forced is one the code was already shaped for — the three lived behind a private
 * `state_routes()` and shared no collaborator with the thread itself.
 *
 * It is also the honest split. `/topics/{id}` is a thread, the same document for every
 * reader who can see it. These three are a *relationship* between one member and that
 * thread: nobody else's answer changes when one of them is written, and a reader who is
 * not signed in has no answer at all. Every one is declared differently from a read
 * route in every way that matters — a real permission callback, verbs that are not
 * `GET`, and no caller who is not signed in.
 *
 * ## The three state routes carry no policy
 *
 * What a member may do to their own state is `Rest\StateService`'s, and it is settled
 * there in one place for all four state routes on the API — see that class for the
 * reasoning, including why a thread the reader cannot see refuses before it writes.
 *
 * @since 0.6.0
 */
class TopicStateController implements ControllerInterface {

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
	 * @param TopicSerializer $topics    Topic rows.
	 * @param RequestBounds   $bounds    What a request may ask for.
	 * @param ResponseFactory $responses What comes back.
	 * @param StateService    $state     Personal state changes.
	 */
	public function __construct(
		TopicSerializer $topics,
		RequestBounds $bounds,
		ResponseFactory $responses,
		StateService $state
	) {
		$this->topics    = $topics;
		$this->bounds    = $bounds;
		$this->responses = $responses;
		$this->state     = $state;
	}

	/**
	 * The three routes a member uses to change their own relationship to a thread.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
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
}
