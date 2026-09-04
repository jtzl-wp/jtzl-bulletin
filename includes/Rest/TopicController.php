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
 */
class TopicController implements ControllerInterface {

	private AccessPolicy $access;

	private TopicSerializer $topics;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	private WriteFields $fields;

	private TopicEditService $edits;

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
	 * Data contract.
	 *
	 * @return array<string,array<mixed>>
	 */
	public function routes(): array {
		return array(

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
	 * Data contract.
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
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function may_edit( \WP_REST_Request $request ) {
		$authenticated = $this->responses->authenticated();

		if ( true !== $authenticated ) {
			return $authenticated;
		}

		$topic_id = $this->bounds->id( $request );

		return true === $this->access->topic( $topic_id ) && $this->access->can_edit_topic( $topic_id )
			? true
			: new \WP_Error(
				'forbidden',
				__( 'You cannot edit this thread.', 'jtzl-bulletin' ),
				array( 'status' => 403 )
			);
	}

	/**
	 * Data contract.
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
