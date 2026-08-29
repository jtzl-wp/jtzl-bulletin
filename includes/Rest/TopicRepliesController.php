<?php
/**
 * A thread's replies: reading them, and adding one.
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
 * `GET|POST /topics/{id}/replies` — the posts under a thread, and answering it.
 *
 * ## Why this left Rest\ReplyController
 *
 * That class used to argue, in as many words, that everything returning a reply
 * belonged in one place — and it was right about GET and POST, which is why they moved
 * together and still sit on one class. What it could not absorb was the edit: adding
 * `PATCH /replies/{id}` put it at coupling 10 against a gate of 10.
 *
 * The cut restores a symmetry the API had lost. `GET|POST /forums/{id}/topics` became
 * Rest\ForumTopicsController in the previous task for the same reason, and a forum's
 * own record stayed behind; here a thread's replies are the collection and one reply is
 * the record. Both anchored collections are now their own resource, and neither parent
 * carries its children's dependencies — this class needs the repository, the write
 * fields and the create service, and the singular route needs none of them.
 *
 * ## The thread is ruled on before its replies are queried
 *
 * ⚠ Rest\AccessPolicy::topic() first, and its refusal returned unchanged — so this
 * collection inherits the singular thread route's 404 and its 403 behind a password.
 * That call is also load-bearing for something less obvious: Query\PendingVisibility
 * widens the reply query for its author's own held reply in this thread, and without a
 * ruling on the thread that widening would apply under a topic the caller may not open.
 * The gate is what keeps the widening confined to threads the caller is already reading.
 *
 * ## Flat, always
 *
 * Threading changes the *order* to parent-before-child and nothing else. The response is
 * a flat array either way, and the relationship travels in each row's `reply_to` — a
 * nested body would be unpageable at exactly the depth that makes it worth having.
 *
 * @since 0.6.0
 */
class TopicRepliesController implements ControllerInterface {

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
	 * Reply rows, and where each one sits in the thread.
	 *
	 * @var ReplyPresenter
	 * @since 0.6.1
	 */
	private ReplyPresenter $replies;

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
	 * The fields a write carries.
	 *
	 * @var WriteFields
	 * @since 0.6.0
	 */
	private WriteFields $fields;

	/**
	 * Answering a thread.
	 *
	 * @var ReplyMutationService
	 * @since 0.6.0
	 */
	private ReplyMutationService $mutations;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param AccessPolicy         $access      Who may read what.
	 * @param CollectionRepository $collections Collection arguments.
	 * @param ReplyPresenter       $replies     Reply rows, and where each one sits.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 * @param WriteFields          $fields      The fields a write carries.
	 * @param ReplyMutationService $mutations   Answering a thread.
	 */
	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		ReplyPresenter $replies,
		RequestBounds $bounds,
		ResponseFactory $responses,
		WriteFields $fields,
		ReplyMutationService $mutations
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->replies     = $replies;
		$this->bounds      = $bounds;
		$this->responses   = $responses;
		$this->fields      = $fields;
		$this->mutations   = $mutations;
	}

	/**
	 * The routes this controller answers.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<mixed>>
	 */
	public function routes(): array {
		return array(
			// Two handlers, one path: reading a thread's replies and adding one to it.
			'/topics/(?P<id>[\d]+)/replies' => array(
				$this->bounds->readable_route(
					array( $this, 'get_collection' ),
					$this->bounds->collection_args() + array(
						'id'     => $this->bounds->integer_arg( array( 'required' => true ) ),
						'around' => $this->bounds->integer_arg(),
					),
				),
				$this->bounds->authenticated_route(
					array( $this, 'create_item' ),
					array( $this->responses, 'authenticated' ),
					array(
						'id'       => $this->bounds->integer_arg( array( 'required' => true ) ),
						'content'  => $this->fields->content_arg(),
						'reply_to' => $this->fields->reply_to_arg(),
					) + $this->bounds->avatar_args(),
					'POST'
				),
			),
		);
	}

	/**
	 * Answer this thread.
	 *
	 * ⚠ **201 with the reply, or 202 with nothing.** A published reply and one held for
	 * review both come back as the created entity — its `status` says which — because in
	 * both cases their author can read them. Every other way the write could have ended
	 * is the fixed acknowledgement, identical between outcomes.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( \WP_REST_Request $request ) {
		$size = $this->bounds->avatar_size( $request );

		return $this->responses->written(
			$this->mutations->create(
				$this->bounds->id( $request ),
				$this->bounds->caller(),
				$this->fields->content( $request ),
				$this->fields->reply_to( $request )
			),
			// ⚠ The position is read *after* the write, and nothing on this path asks for
			// the thread's order before it — so the order is built fresh and the new reply
			// is in it, held for review or not. Pinned by a test, because "nothing asks
			// first" is a property of today's call graph rather than of this line: a
			// future pre-insert `ordered_ids()` would warm the memo and serve back an
			// order the reply is missing from, silently and only on a create.
			fn( int $reply_id ): array => $this->replies->reply( $reply_id, $size )
		);
	}

	/**
	 * One page of a thread's replies.
	 *
	 * Which page that is can be asked for two ways — `page`, or `around={reply_id}` for
	 * whichever page holds a particular reply. Rest\ReplyPresenter settles it, including
	 * the refusal when `around` names something this reader cannot be paged to.
	 *
	 * @since 0.6.0
	 * @since 0.6.1 Positions on every row, and `around`.
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

		$per_page = $this->bounds->per_page( $request );
		$number   = $this->replies->page_number(
			$topic_id,
			// Read here rather than through Rest\RequestBounds, which holds what *every*
			// collection may ask for; `around` belongs to this one route, exactly as
			// `parent` belongs to Rest\ForumController and `tag` to Rest\TagController.
			//
			// ⚠ **Absent, 0 and a negative all arrive as "no target".** The argument
			// carries no schema `minimum` on purpose: a bound is checked before any
			// callback runs, so `around=0` would come back as WordPress's
			// `rest_invalid_param` while `around=99999` came back as `invalid_around` —
			// two refusals for one mistake, and the pair says which IDs are shaped like
			// replies.
			max( 0, (int) $request->get_param( 'around' ) ),
			$this->bounds->page( $request ),
			$per_page
		);

		if ( ! is_int( $number ) ) {
			return $number;
		}

		$page = $this->collections->topic_replies( $topic_id, $number, $per_page );

		return $this->responses->collection(
			$this->replies->page( $page['ids'], $this->bounds->avatar_size( $request ), ( $number - 1 ) * $per_page ),
			$page['total'],
			$page['total_pages']
		);
	}
}
