<?php
/**
 * Finding a member by name.
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
 * `GET /users?q=` — resolving a display name or slug to a member.
 */
class UserSearchController implements ControllerInterface {

	public const MIN_TERM_LENGTH = 2;

	private UserSearchRepository $members;

	private UserSerializer $users;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	public function __construct(
		UserSearchRepository $members,
		UserSerializer $users,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->members   = $members;
		$this->users     = $users;
		$this->bounds    = $bounds;
		$this->responses = $responses;
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
		return array(
			'/users' => $this->bounds->authenticated_route(
				array( $this, 'get_collection' ),
				array( $this->responses, 'authenticated' ),
				$this->bounds->collection_args() + array(
					'q' => $this->bounds->string_arg( array( 'required' => true ) ),
				)
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
		$terms = $this->bounds->terms( $request );

		if ( mb_strlen( $terms ) < self::MIN_TERM_LENGTH ) {
			return $this->short_term();
		}

		$page = $this->members->search(
			$terms,
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->users->summaries( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}

	private function short_term(): \WP_Error {
		return new \WP_Error(
			'rest_invalid_param',
			/* translators: %s: name of the invalid request parameter. */
			sprintf( __( 'Invalid parameter(s): %s', 'jtzl-bulletin' ), 'q' ),
			array(
				'status' => 400,
				'params' => array(
					'q' => sprintf(
						/* translators: %d: minimum number of characters a search term must have. */
						__( 'A search term must be at least %d characters.', 'jtzl-bulletin' ),
						self::MIN_TERM_LENGTH
					),
				),
			)
		);
	}
}
