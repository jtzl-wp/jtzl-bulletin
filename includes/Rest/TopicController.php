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
 * ## Public route, private answer
 *
 * Both routes are declared through `Rest\RequestBounds::readable_route()`, which owns
 * the open permission callback and the reason for it. Which class answers instead is
 * per-route and belongs here: `Rest\AccessPolicy` for the singular route,
 * `Rest\CollectionVisibility` inside the query for the collection — before
 * `found_posts`, so the total describes the rows.
 *
 * @since 0.6.0
 */
class TopicController implements ControllerInterface {

	/**
	 * Who may read what.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

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
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param AccessPolicy         $access      Who may read what.
	 * @param CollectionRepository $collections Collection arguments.
	 * @param TopicSerializer      $topics      Topic rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		TopicSerializer $topics,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->topics      = $topics;
		$this->bounds      = $bounds;
		$this->responses   = $responses;
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
		$enabled = $this->access->topic_tags();

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
		$topic_id = (int) $request->get_param( 'id' );
		$allowed  = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		return $this->responses->item( $this->topics->topic( $topic_id, $this->bounds->avatar_size( $request ) ) );
	}
}
