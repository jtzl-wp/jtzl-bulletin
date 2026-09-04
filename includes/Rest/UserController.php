<?php
/**
 * Public profile reads.
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
 * `GET /users/{id}` and the two collections it counts — a member, and what they wrote.
 */
class UserController implements ControllerInterface {

	private AccessPolicy $access;

	private UnanchoredRepository $collections;

	private ProfilePresenter $profiles;

	private TopicSerializer $topics;

	private ReplySerializer $replies;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	public function __construct(
		AccessPolicy $access,
		UnanchoredRepository $collections,
		ProfilePresenter $profiles,
		TopicSerializer $topics,
		ReplySerializer $replies,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->profiles    = $profiles;
		$this->topics      = $topics;
		$this->replies     = $replies;
		$this->bounds      = $bounds;
		$this->responses   = $responses;
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,array<mixed>>
	 */
	public function routes(): array {
		return array(
			'/users/(?P<id>[\d]+)'         => $this->bounds->readable_route(
				array( $this, 'get_item' ),
				array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				) + $this->bounds->avatar_args()
			),
			'/users/(?P<id>[\d]+)/topics'  => $this->bounds->readable_route(
				array( $this, 'get_topics' ),
				$this->bounds->collection_args() + array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				),
			),
			'/users/(?P<id>[\d]+)/replies' => $this->bounds->readable_route(
				array( $this, 'get_replies' ),
				$this->bounds->collection_args() + array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
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
		return $this->profiles->present(
			$this->bounds->id( $request ),
			$this->bounds->avatar_size( $request )
		);
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_topics( \WP_REST_Request $request ) {
		$user_id = $this->bounds->member( $request );
		$allowed = $this->access->user( $user_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$page = $this->collections->user_topics(
			$user_id,
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
	public function get_replies( \WP_REST_Request $request ) {
		$user_id = $this->bounds->member( $request );
		$allowed = $this->access->user( $user_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$page = $this->collections->user_replies(
			$user_id,
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->replies->replies( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}
}
