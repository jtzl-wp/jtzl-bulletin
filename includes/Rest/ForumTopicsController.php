<?php
/**
 * A forum's thread list, read and written.
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
 * `GET|POST /forums/{id}/topics` — what is in a forum, and putting something in it.
 *
 * ## One path, two handlers
 *
 * This is one of the two routes in the API whose value is a numerically keyed *list* of
 * handler groups rather than a single one: reading a forum's threads and starting one
 * are the same resource seen from either side, and WordPress dispatches between them by
 * verb. (The state routes look different — `PUT`/`DELETE` on a subscription share one
 * callback, so they are a single group naming both verbs.)
 *
 * ⚠ **The forum is ruled on before its threads are queried**, and its refusal is
 * returned unchanged, so this collection inherits the singular forum route's 404 and its
 * 403 behind a password. A thread list under a forum the reader may not know about is a
 * table of contents for it, and the titles are most of what its privacy was protecting.
 *
 * ## Its own controller, not Rest\ForumController's fourth route
 *
 * A forum's own record — the hierarchy, the singular route, the subscription — is one
 * thing; its contents are another, and the write half makes that plain: creating a
 * thread is a *topic* lifecycle that happens to be addressed through a forum. Keeping
 * both halves of the path together is what matters, because a path answered by one class
 * on GET and another on POST is worse than either arrangement.
 *
 * @since 0.6.0
 */
class ForumTopicsController implements ControllerInterface {

	/**
	 * Who may have this forum.
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
	 * What a write carries.
	 *
	 * @var WriteFields
	 * @since 0.6.0
	 */
	private WriteFields $fields;

	/**
	 * What comes back.
	 *
	 * @var ResponseFactory
	 * @since 0.6.0
	 */
	private ResponseFactory $responses;

	/**
	 * Starting a thread.
	 *
	 * @var TopicMutationService
	 * @since 0.6.0
	 */
	private TopicMutationService $mutations;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param AccessPolicy         $access      Who may have this forum.
	 * @param CollectionRepository $collections Collection arguments.
	 * @param TopicSerializer      $topics      Topic rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param WriteFields          $fields      What a write carries.
	 * @param ResponseFactory      $responses   What comes back.
	 * @param TopicMutationService $mutations   Starting a thread.
	 */
	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		TopicSerializer $topics,
		RequestBounds $bounds,
		WriteFields $fields,
		ResponseFactory $responses,
		TopicMutationService $mutations
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->topics      = $topics;
		$this->bounds      = $bounds;
		$this->fields      = $fields;
		$this->responses   = $responses;
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
			'/forums/(?P<id>[\d]+)/topics' => array(
				$this->bounds->readable_route(
					array( $this, 'get_collection' ),
					$this->bounds->collection_args() + array(
						'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
					),
				),
				$this->bounds->authenticated_route(
					array( $this, 'create_item' ),
					array( $this->responses, 'authenticated' ),
					array(
						'id'      => $this->bounds->integer_arg( array( 'required' => true ) ),
						'title'   => $this->bounds->string_arg( array( 'required' => true ) ),
						'content' => $this->fields->content_arg(),
						'tags'    => $this->fields->tags_arg(),
					) + $this->bounds->avatar_args(),
					'POST'
				),
			),
		);
	}

	/**
	 * One page of a forum's thread list.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_collection( \WP_REST_Request $request ) {
		$forum_id = $this->bounds->id( $request );
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

	/**
	 * Start a thread in this forum.
	 *
	 * ⚠ **The ID comes off the path, never off `get_param()`.** WordPress resolves the
	 * query string *ahead* of the path, so a write reading the parameter would create the
	 * thread in whichever forum `?id=` named. `Rest\RequestBounds::id()` is the only
	 * reader that cannot be steered that way.
	 *
	 * Every gate — capability, category, closed, private, hidden, password — is
	 * Rest\TopicMutationService's, because they are the *write* handler's gates and the
	 * point of this route is that it refuses exactly what bbPress would refuse.
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
				$this->fields->title( $request ),
				$this->fields->content( $request ),
				$this->fields->tags( $request )
			),
			fn( int $topic_id ): array => $this->topics->topic( $topic_id, $size )
		);
	}
}
