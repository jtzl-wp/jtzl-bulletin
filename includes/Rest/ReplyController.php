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
 */
class ReplyController implements ControllerInterface {

	private AccessPolicy $access;

	private ReplyPresenter $replies;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	private WriteFields $fields;

	private ReplyEditService $edits;

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
	 * Data contract.
	 *
	 * @return array<string,array<mixed>>
	 */
	public function routes(): array {
		return array(

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
	 * Data contract.
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
				$this->fields->changes( $request, array( 'content' ) )
			),
			fn( int $reply_id ): array => $this->replies->reply( $reply_id, $size ),
			200
		);
	}
}
