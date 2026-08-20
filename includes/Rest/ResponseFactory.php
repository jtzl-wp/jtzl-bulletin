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
 *
 * Totals, cache policy and the shape of an error are not per-controller decisions —
 * they are the contract the app was handed. A controller that built its own would be
 * right until the day it was edited, so controllers ask this class and there is one
 * place to read to know what every route does. What a request may *ask* for is
 * Rest\RequestBounds' half of the same job; this class is only what comes back.
 *
 * ## A personalised response is never shared-cacheable
 *
 * Bulletin's public routes return per-reader values — `is_unread`, `is_favorite`,
 * `is_subscribed`, an author's own held reply. The route is public; the *answer* is
 * not. A shared cache that stored one reader's answer under the URL would hand it to
 * the next reader, so every response varies on both authentication channels and every
 * authenticated or mutating one refuses shared storage outright. Stating it here means
 * no route can forget it.
 *
 * @since 0.6.0
 */
class ResponseFactory {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * A paginated collection: a top-level array, with its totals in headers.
	 *
	 * @since 0.6.0
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
	 * One entity.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $item     Serialized entity.
	 * @param int                 $status   HTTP status.
	 * @param bool                $mutation Whether this answers a write.
	 * @return \WP_REST_Response
	 */
	public function item( array $item, int $status = 200, bool $mutation = false ): \WP_REST_Response {
		return $this->isolate( new \WP_REST_Response( $item, $status ), $mutation );
	}

	/**
	 * The acknowledgement that names nothing.
	 *
	 * Returned when a write ends somewhere the caller is not told about. It carries no
	 * ID, no `Location`, no status and no reason — deliberately, and identically for
	 * every such outcome, because the difference between them is exactly what somebody
	 * probing the spam filter would tune against.
	 *
	 * @since 0.6.0
	 *
	 * @return \WP_REST_Response
	 */
	public function accepted(): \WP_REST_Response {
		return $this->isolate( new \WP_REST_Response( array( 'accepted' => true ), 202 ), true );
	}

	/**
	 * True when somebody is signed in, and the standard refusal when not.
	 *
	 * The code is WordPress's own `rest_not_logged_in`, not a Bulletin invention: a
	 * client already has to handle it from core routes.
	 *
	 * @since 0.6.0
	 *
	 * @return true|\WP_Error
	 */
	public function authenticated() {
		if ( $this->wp->is_user_logged_in() && $this->wp->get_current_user_id() > 0 ) {
			return true;
		}

		return new \WP_Error(
			'rest_not_logged_in',
			__( 'You are not currently logged in.', 'jtzl-bulletin' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Say who a response is for, and whether it may be stored.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Response $response Response to mark.
	 * @param bool              $mutation Whether this answers a write.
	 * @return \WP_REST_Response
	 */
	private function isolate( \WP_REST_Response $response, bool $mutation ): \WP_REST_Response {
		// Both channels: a bearer token and a cookie identify different readers of the
		// same URL, and a cache keyed on only one of them would serve across the other.
		$response->header( 'Vary', 'Authorization, Cookie' );

		if ( $mutation || $this->wp->is_user_logged_in() ) {
			$response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
		}

		return $response;
	}
}
