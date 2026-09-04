<?php
/**
 * The caller's own profile.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Rest;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * `GET /me` and `PATCH /me` — reading and editing your own profile.
 */
class ProfileController implements ControllerInterface {

	private ProfilePresenter $profiles;

	private ProfileEditService $edits;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	public function __construct(
		ProfilePresenter $profiles,
		ProfileEditService $edits,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->profiles  = $profiles;
		$this->edits     = $edits;
		$this->bounds    = $bounds;
		$this->responses = $responses;
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,array<mixed>>
	 */
	public function routes(): array {
		return array(
			'/me' => array(
				$this->bounds->authenticated_route(
					array( $this, 'get_item' ),
					array( $this->responses, 'authenticated' ),
					$this->bounds->avatar_args()
				),
				$this->bounds->authenticated_route(
					array( $this, 'update_item' ),
					array( $this->responses, 'authenticated' ),
					array(

						'name' => $this->bounds->string_arg(),
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
		return $this->profiles->present(
			$this->bounds->caller(),
			$this->bounds->avatar_size( $request )
		);
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( \WP_REST_Request $request ) {
		$changes = array();

		if ( $request->has_param( 'name' ) ) {
			$sent = $request->get_param( 'name' );

			$changes['name'] = is_scalar( $sent ) ? (string) $sent : '';
		}

		$caller  = $this->bounds->caller();
		$applied = $this->edits->update( $caller, $changes );

		if ( true !== $applied ) {
			return $applied;
		}

		return $this->profiles->present( $caller, $this->bounds->avatar_size( $request ) );
	}
}
