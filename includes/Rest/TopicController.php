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
 * `GET|PATCH /topics/{id}` — one thread, and its author changing it.
 *
 * ## What used to be here
 *
 * A tag's threads and the three personal-state routes. Adding the edit put this class
 * at coupling 11 and 355 lines against gates of 10 and 350, and both moved: `GET
 * /topics?tag=` to Rest\TagController, whose collaborators it already shared and whose
 * resource it always was, and the state routes to Rest\TopicStateController. What is
 * left is one thread: the document, and the one person entitled to change it.
 *
 * ## The singular route carries the opening post; a collection does not
 *
 * A list of fifty threads that each carried their opening post would be a large response
 * to render a title. `content` is a property of reading one thread, which is this route.
 *
 * ## Public to read, author-only to write
 *
 * The read is declared through `Rest\RequestBounds::readable_route()`, which owns the
 * open permission callback and the reason for it; `Rest\AccessPolicy::topic()` answers
 * per-row instead. The edit is the only route on this class with a real permission
 * callback, and it is `Rest\AccessPolicy::can_edit_topic()` — **not**
 * `bbp_get_topic_edit_link()` alone, which a moderator also satisfies. v1 has no
 * moderation surface, so a moderator editing somebody else's thread has no route here.
 *
 * ⚠ **An author cannot edit their own *held* thread**, because that policy admits only a
 * post whose stored status is public. Releasing a pending post is a moderator's
 * decision, and an edit that could be used to make it is a way around one.
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
	 * The fields an edit carries.
	 *
	 * @var WriteFields
	 * @since 0.6.0
	 */
	private WriteFields $fields;

	/**
	 * Editing a thread, as its author.
	 *
	 * @var TopicEditService
	 * @since 0.6.0
	 */
	private TopicEditService $edits;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param AccessPolicy     $access      Who may read what.
	 * @param TopicSerializer  $topics      Topic rows.
	 * @param RequestBounds    $bounds      What a request may ask for.
	 * @param ResponseFactory  $responses   What comes back.
	 * @param WriteFields      $fields      The fields an edit carries.
	 * @param TopicEditService $edits       Editing a thread, as its author.
	 */
	public function __construct(
		AccessPolicy $access,
		TopicSerializer $topics,
		RequestBounds $bounds,
		ResponseFactory $responses,
		WriteFields $fields,
		TopicEditService $edits
	) {
		$this->access    = $access;
		$this->topics    = $topics;
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
			// Two handlers, one path: reading a thread and its author editing it.
			'/topics/(?P<id>[\d]+)' => array(
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
						'title'   => $this->bounds->string_arg(),
						'content' => $this->fields->patch_content_arg(),
						'tags'    => $this->fields->patch_tags_arg(),
					) + $this->bounds->avatar_args(),
					'PATCH'
				),
			),
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

	/**
	 * Whether this reader may edit this thread.
	 *
	 * ⚠ **A thread that does not exist answers 403, where reading one answers 404**, and
	 * the asymmetry is deliberate. `Rest\AccessPolicy::can_edit_topic()` gives a plain
	 * yes or no, and every no this route can produce — not signed in, not the author, a
	 * moderator, the edit window shut, a thread that was never there — comes back as the
	 * same refusal. Telling those apart would let anybody enumerate which IDs exist and
	 * which of them they wrote, by watching the status change.
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

		return $this->access->can_edit_topic( $this->bounds->id( $request ) )
			? true
			: new \WP_Error(
				'forbidden',
				__( 'You cannot edit this thread.', 'jtzl-bulletin' ),
				array( 'status' => 403 )
			);
	}

	/**
	 * Edit a thread, as its author.
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
				$this->fields->changes( $request, array( 'title', 'content', 'tags' ) )
			),
			fn( int $topic_id ): array => $this->topics->topic( $topic_id, $size ),
			200
		);
	}
}
