<?php
/**
 * Reply reads.
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
 * `GET /topics/{id}/replies` and `GET /replies/{id}` — a thread's posts, and one post.
 *
 * ## Everything that returns a reply lives here
 *
 * Including the collection anchored to a topic, which is the opposite of where the
 * forum-anchored thread list sits: `GET /forums/{id}/topics` is declared by
 * Rest\ForumController, beside the forum it opens. The two rules are not in conflict
 * because they are the same rule seen from either end — a controller owns one
 * resource, and a route belongs to whichever end of it the code is about. A thread
 * list is mostly a forum question (which forum, may you open it, what is pinned into
 * it); a reply list is mostly a reply question (what order, what may be counted, whose
 * held post is in it), and it is about to be joined here by `POST` on the same path.
 * A path answered by one controller on GET and another on POST is worse than either
 * arrangement.
 *
 * ## The thread is ruled on before its replies are queried
 *
 * ⚠ Rest\AccessPolicy::topic() first, and its refusal returned unchanged — so this
 * collection inherits the singular thread route's 404 and its 403 behind a password.
 * That call is also load-bearing for something less obvious: Query\PendingVisibility
 * widens the reply query for its author's own held reply in this thread, and without a
 * ruling on the thread that widening would apply under a topic the caller may not
 * open. The gate is what keeps the widening confined to threads the caller is already
 * reading.
 *
 * ## Flat, always
 *
 * Threading changes the *order* to parent-before-child and nothing else. The response
 * is a flat array either way, and the relationship travels in each row's `reply_to` —
 * a nested body would be unpageable at exactly the depth that makes it worth having.
 *
 * @since 0.6.0
 */
class ReplyController implements ControllerInterface {

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
	 * Reply rows.
	 *
	 * @var ReplySerializer
	 * @since 0.6.0
	 */
	private ReplySerializer $replies;

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
	 * @param ReplySerializer      $replies     Reply rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		ReplySerializer $replies,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->replies     = $replies;
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
			'/topics/(?P<id>[\d]+)/replies' => $this->bounds->readable_route(
				array( $this, 'get_collection' ),
				$this->bounds->collection_args() + array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				),
			),
			// A reply has an author, so the singular route takes the avatar size its
			// collection takes — unlike a forum's, which has nobody to draw.
			'/replies/(?P<id>[\d]+)'        => $this->bounds->readable_route(
				array( $this, 'get_item' ),
				array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				) + $this->bounds->avatar_args()
			),
		);
	}

	/**
	 * One page of a thread's replies.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_collection( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$allowed  = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$page = $this->collections->topic_replies(
			$topic_id,
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->replies->replies( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}

	/**
	 * One reply, for a deep link.
	 *
	 * The route the app resolves a permalink through: a notification, a shared URL, or
	 * the `link` it was handed on a row. Rest\AccessPolicy::reply() asks the thread
	 * first and returns its answer unchanged, so a deep link into a locked thread is
	 * refused in the same words as the thread itself.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		$reply_id = $this->bounds->id( $request );
		$allowed  = $this->access->reply( $reply_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		return $this->responses->item( $this->replies->reply( $reply_id, $this->bounds->avatar_size( $request ) ) );
	}
}
