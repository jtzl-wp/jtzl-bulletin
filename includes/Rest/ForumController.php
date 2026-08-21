<?php
/**
 * Forum reads.
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
 * `GET /forums`, `GET /forums/{id}` and `GET /forums/{id}/topics` — one level of the
 * hierarchy, one forum, and one forum's thread list.
 *
 * The thread list lives here rather than beside the other topic routes because what it
 * is anchored to is a forum: it takes the forum's ID out of the path, refuses on the
 * forum's own terms, and is the second half of what "open this forum" means. Its rows
 * are serialized by the topic serializer all the same.
 *
 * ## The hierarchy is requested a level at a time
 *
 * There is no nested `subforums[]` array. A forum with three hundred children would
 * make one response unbounded and unpageable, and the app would have no way to ask
 * for the rest of it. `?parent={id}` pages every level with the same two arguments,
 * and repeating the request traverses to any depth.
 *
 * ⚠ **The parent is ruled on before its children are queried.** A child list under a
 * forum the reader may not know about is a directory of that forum's contents — the
 * titles of everything inside it, which is most of what the forum's privacy was
 * protecting. So `Rest\AccessPolicy::forum()` answers first and its refusal is
 * returned unchanged, which also gives the collection the singular route's 403 on a
 * password-protected branch.
 *
 * Parent 0 is exempt, and it is not a missing value: it is the top-level index. The
 * guard keys off "is this zero" before "is this a forum" for the reason
 * `Ajax\LoadForumsController` does — asking bbPress whether the reader may view forum
 * 0 would be a question about nothing.
 *
 * ## Public route, private answer
 *
 * Every route here is declared through `Rest\RequestBounds::readable_route()`, which
 * is where the open permission callback lives and why. What is worth saying *here* is
 * which class answers instead: Rest\AccessPolicy for the singular route, and
 * Rest\CollectionVisibility inside the query for the collection — before
 * `found_posts`, so the total describes the rows.
 *
 * @since 0.6.0
 */
class ForumController implements ControllerInterface {

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
	 * Forum rows.
	 *
	 * @var ForumSerializer
	 * @since 0.6.0
	 */
	private ForumSerializer $forums;

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
	 * @param ForumSerializer      $forums      Forum rows.
	 * @param TopicSerializer      $topics      Topic rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		ForumSerializer $forums,
		TopicSerializer $topics,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->forums      = $forums;
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
			'/forums'                      => $this->bounds->readable_route(
				array( $this, 'get_collection' ),
				$this->bounds->collection_args() + array(
					'parent' => $this->bounds->integer_arg(
						array(
							'default' => 0,
							'minimum' => 0,
						)
					),
				),
			),
			// Only the ID. A forum has no author, so `avatar_size` would be an argument
			// this route declares and then ignores; the collection carries it because it
			// takes the shared bundle whole, and the bundle is shared with the
			// collections that do serialize people.
			'/forums/(?P<id>[\d]+)'        => $this->bounds->readable_route(
				array( $this, 'get_item' ),
				array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				)
			),
			'/forums/(?P<id>[\d]+)/topics' => $this->bounds->readable_route(
				array( $this, 'get_topics' ),
				$this->bounds->collection_args() + array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				),
			),
		);
	}

	/**
	 * One page of one level of the hierarchy.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_collection( \WP_REST_Request $request ) {
		$parent = max( 0, (int) $request->get_param( 'parent' ) );

		if ( $parent > 0 ) {
			$allowed = $this->access->forum( $parent );

			if ( true !== $allowed ) {
				return $allowed;
			}
		}

		$page = $this->collections->forums( $parent, $this->bounds->page( $request ), $this->bounds->per_page( $request ) );

		return $this->responses->collection(
			$this->forums->forums( $page['ids'] ),
			$page['total'],
			$page['total_pages']
		);
	}

	/**
	 * One forum.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		$forum_id = (int) $request->get_param( 'id' );
		$allowed  = $this->access->forum( $forum_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		return $this->responses->item( $this->forums->forum( $forum_id ) );
	}

	/**
	 * One page of a forum's thread list.
	 *
	 * ⚠ **The forum is ruled on before its threads are queried**, for the reason the
	 * child forum list is: a thread list under a forum the reader may not know about
	 * is a table of contents for it, and the titles are most of what its privacy was
	 * protecting. The refusal is returned unchanged, so this route inherits both the
	 * singular route's 404 and its 403 on a protected branch.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_topics( \WP_REST_Request $request ) {
		$forum_id = (int) $request->get_param( 'id' );
		$allowed  = $this->access->forum( $forum_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$page = $this->collections->forum_topics(
			$forum_id,
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->topics->topics( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}
}
