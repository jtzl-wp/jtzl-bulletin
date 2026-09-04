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
 */
class ForumTopicsController implements ControllerInterface {

	private AccessPolicy $access;

	private CollectionRepository $collections;

	private TopicSerializer $topics;

	private RequestBounds $bounds;

	private WriteFields $fields;

	private ResponseFactory $responses;

	private TopicMutationService $mutations;

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
	 * Data contract.
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
	 * Data contract.
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
	 * Data contract.
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
