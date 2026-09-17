<?php
/**
 * Shared REST response construction.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * The parts of a response that must be the same on every route.
 */
class ResponseFactory {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Data contract.
	 *
	 * @param array<int,array<string,mixed>> $items Serialized rows.
	 * @param int                            $total Rows after visibility filtering.
	 * @param int                            $pages Pages at the requested size.
	 * @return \WP_REST_Response
	 */
	public function collection( array $items, int $total, int $pages ): \WP_REST_Response {
		$response = new \WP_REST_Response( array_values( $items ) );
		$response->header( 'X-WP-Total', (string) max( 0, $total ) );
		$response->header( 'X-WP-TotalPages', (string) max( 0, $pages ) );

		return $this->isolate( $response, false );
	}

	/**
	 * Data contract.
	 *
	 * @param array<string,mixed> $item     Serialized entity.
	 * @param int                 $status   HTTP status.
	 * @param bool                $mutation Whether this answers a write.
	 * @return \WP_REST_Response
	 */
	public function item( array $item, int $status = 200, bool $mutation = false ): \WP_REST_Response {
		return $this->isolate( new \WP_REST_Response( $item, $status ), $mutation );
	}

	public function accepted(): \WP_REST_Response {
		return $this->isolate( new \WP_REST_Response( array( 'accepted' => true ), 202 ), true );
	}

	/**
	 * Data contract.
	 *
	 * @param MutationResult|\WP_Error $written   What the mutation service reported.
	 * @param callable                 $serialize Turns the written ID into a row.
	 * @param int                      $status    Success code: 201 for a create, 200 for an edit.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function written( $written, callable $serialize, int $status = 201 ) {
		if ( ! $written instanceof MutationResult ) {
			return $written;
		}

		return $written->is_accepted()
			? $this->accepted()
			: $this->item( $serialize( (int) $written->id() ), $status, true );
	}

	/**
	 * Data contract.
	 *
	 * @return true|\WP_Error
	 */
	public function authenticated() {
		if ( $this->wp->is_user_logged_in() && $this->wp->get_current_user_id() > 0 ) {
			return true;
		}

		return new \WP_Error(
			'rest_not_logged_in',
			__( 'You are not currently logged in.', 'jtzls-bulletin-for-bbpress' ),
			array( 'status' => 401 )
		);
	}

	private function isolate( \WP_REST_Response $response, bool $mutation ): \WP_REST_Response {

		$response->header( 'Vary', 'Authorization, Cookie' );

		if ( $mutation || $this->wp->is_user_logged_in() ) {
			$response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
		}

		return $response;
	}
}
