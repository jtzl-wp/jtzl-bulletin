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
 * `GET|PATCH /replies/{id}` — one post, and its author changing it.
 *
 * ## One resource, both verbs
 *
 * This class used to hold a thread's reply collection as well, on the argument that
 * everything returning a reply belonged in one place. Adding the edit route put it at
 * coupling 10 against a gate of 10, and the collection moved to
 * Rest\TopicRepliesController — which restored a symmetry the API had lost, since
 * `GET|POST /forums/{id}/topics` had already left Rest\ForumController for the same
 * reason. What the argument was actually right about is kept: a path answered by one
 * controller on GET and another on PATCH would be worse than either arrangement, so
 * both handlers on this path are here.
 *
 * ## The edit is the author's, and only the author's
 *
 * ⚠ Rest\AccessPolicy::can_edit_reply() is the permission callback — **not**
 * `bbp_get_reply_edit_link()` alone, which a moderator also satisfies. v1 has no
 * moderation surface, so a moderator editing somebody else's post has no route here at
 * all; they have the website. What an author may change is the body, and nothing else:
 * the thread, the forum, the author, the status and the reply being answered are all
 * preserved by Rest\ReplyEditService.
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
	 * What a write carries.
	 *
	 * @var WriteFields
	 * @since 0.6.0
	 */
	private WriteFields $fields;

	/**
	 * Editing a reply, as its author.
	 *
	 * @var ReplyEditService
	 * @since 0.6.0
	 */
	private ReplyEditService $edits;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param AccessPolicy     $access      Who may read what.
	 * @param ReplyPresenter   $replies     Reply rows, and where each one sits.
	 * @param RequestBounds    $bounds      What a request may ask for.
	 * @param ResponseFactory  $responses   What comes back.
	 * @param WriteFields      $fields      What a write carries.
	 * @param ReplyEditService $edits       Editing a reply, as its author.
	 */
	public function __construct(
		AccessPolicy $access,
		ReplyPresenter $replies,
		RequestBounds $bounds,
		ResponseFactory $responses,
		WriteFields $fields,
		ReplyEditService $edits
	) {
		$this->access    = $access;
		$this->replies   = $replies;
		$this->bounds    = $bounds;
		$this->responses = $responses;
		$this->fields    = $fields;
		$this->edits     = $edits;
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
			// Two handlers, one path: reading one reply and its author editing it. A
			// reply has an author, so the route takes the avatar size its collection
			// takes — unlike a forum's, which has nobody to draw.
			'/replies/(?P<id>[\d]+)' => array(
				$this->bounds->readable_route(
					array( $this, 'get_item' ),
					array(
						'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
					) + $this->bounds->avatar_args()
				),
				$this->bounds->authenticated_route(
					array( $this, 'update_item' ),
					array( $this, 'may_edit' ),
					array(
						'id'      => $this->bounds->integer_arg( array( 'required' => true ) ),
						'content' => $this->fields->patch_content_arg(),
					) + $this->bounds->avatar_args(),
					'PATCH'
				),
			),
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
	 * ⚠ **This is the one read route where `position` is worth its cost.** Resolving a
	 * permalink is precisely the case that used to make an app walk a thread page by page
	 * until the reply appeared, so the row says where it is — and the app can then ask
	 * for that page directly, or pass the same ID back as `?around=`. It costs one
	 * reading-order build; see Rest\ReplyPositions.
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

	/**
	 * Whether this reader may edit this reply.
	 *
	 * ⚠ Uniformly 403, for the reason Rest\TopicController::may_edit() gives: every no
	 * this route can produce looks the same from outside, so a reply that does not exist
	 * cannot be told from one somebody else wrote.
	 *
	 * ⚠ And readability is asked first, for the other reason that method gives: bbPress's
	 * `edit_reply` capability never looks at the forum, because the website reaches an
	 * edit form through a thread that has already rendered. This route is reached by ID.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function may_edit( \WP_REST_Request $request ) {
		$authenticated = $this->responses->authenticated();

		if ( true !== $authenticated ) {
			return $authenticated;
		}

		$reply_id = $this->bounds->id( $request );

		return true === $this->access->reply( $reply_id ) && $this->access->can_edit_reply( $reply_id )
			? true
			: new \WP_Error(
				'forbidden',
				__( 'You cannot edit this reply.', 'jtzl-bulletin' ),
				array( 'status' => 403 )
			);
	}

	/**
	 * Edit a reply, as its author.
	 *
	 * ⚠ **200 with the reply, or 202 with nothing** — the same pair a create answers
	 * with, one code down. An edit held for review still comes back as the entity,
	 * because its author can read it; every other way the write could have ended is the
	 * fixed acknowledgement, identical between outcomes.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( \WP_REST_Request $request ) {
		$size = $this->bounds->avatar_size( $request );

		return $this->responses->written(
			$this->edits->update(
				$this->bounds->id( $request ),
				$this->bounds->caller(),
				$this->fields->changes( $request, array( 'content' ) )
			),
			fn( int $reply_id ): array => $this->replies->reply( $reply_id, $size ),
			200
		);
	}
}
