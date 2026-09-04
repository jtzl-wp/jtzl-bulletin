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
 * `GET /forums`, `GET /forums/{id}` and the forum's own subscription — one level of the
 * hierarchy, one forum, and a member's relationship to it.
 */
class ForumController implements ControllerInterface {

	private AccessPolicy $access;

	private CollectionRepository $collections;

	private ForumSerializer $forums;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	private StateService $state;

	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		ForumSerializer $forums,
		RequestBounds $bounds,
		ResponseFactory $responses,
		StateService $state
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->forums      = $forums;
		$this->bounds      = $bounds;
		$this->responses   = $responses;
		$this->state       = $state;
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,array<mixed>>
	 */
	public function routes(): array {
		return array(
			'/forums'                            => $this->bounds->readable_route(
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

			'/forums/(?P<id>[\d]+)'              => $this->bounds->readable_route(
				array( $this, 'get_item' ),
				array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				)
			),

			'/forums/(?P<id>[\d]+)/subscription' => $this->bounds->authenticated_route(
				array( $this, 'subscription' ),
				array( $this->responses, 'authenticated' ),
				array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				),
				'PUT, DELETE'
			),
		);
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function subscription( \WP_REST_Request $request ) {
		$forum_id = $this->bounds->id( $request );
		$written  = $this->state->set_forum_subscription(
			$forum_id,
			$this->bounds->caller(),
			'DELETE' !== strtoupper( $request->get_method() )
		);

		if ( true !== $written ) {
			return $written;
		}

		return $this->responses->item( $this->forums->forum( $forum_id ), 200, true );
	}

	/**
	 * Data contract.
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
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		$forum_id = $this->bounds->id( $request );
		$allowed  = $this->access->forum( $forum_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		return $this->responses->item( $this->forums->forum( $forum_id ) );
	}
}
