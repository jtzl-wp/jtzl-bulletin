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
 * `GET /forums` and `GET /forums/{id}` — one level of the hierarchy, and one forum.
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
 * The permission callback is `__return_true` on both routes. That is not an oversight
 * and it is not "no access control": Bulletin's forums are public-read, so refusing
 * an unauthenticated request outright would be wrong, and *what this reader may have*
 * is a per-row answer that a permission callback with only the route in hand cannot
 * give. Rest\AccessPolicy answers it for the singular route and
 * Rest\CollectionVisibility answers it inside the query for the collection — before
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
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		ForumSerializer $forums,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->forums      = $forums;
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
			'/forums'               => array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_collection' ),
				'permission_callback' => '__return_true',
				'args'                => $this->bounds->collection_args() + array(
					'parent' => $this->bounds->integer_arg(
						array(
							'default' => 0,
							'minimum' => 0,
						)
					),
				),
			),
			'/forums/(?P<id>[\d]+)' => array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => '__return_true',
				// Only the ID. A forum has no author, so `avatar_size` would be an
				// argument this route declares and then ignores; the collection
				// carries it because it takes the shared bundle whole, and the
				// bundle is shared with the collections that do serialize people.
				'args'                => array(
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
}
