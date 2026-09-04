<?php
/**
 * The bounds a request may ask for.
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
 * Page, page size and avatar size — read from a request, brought inside their limits,
 * and declared as route arguments.
 */
class RequestBounds {

	private const PER_PAGE_DEFAULT = 10;

	private const PER_PAGE_MAX = 100;

	private const AVATAR_DEFAULT = 96;

	private const AVATAR_MAX = 512;

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Path IDs must use get_url_params(); get_param() lets a query value override the route ID,
	 * potentially exposing another member's private collection.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function id( \WP_REST_Request $request ): int {
		return (int) ( $request->get_url_params()['id'] ?? 0 );
	}

	public function caller(): int {
		return $this->wp->get_current_user_id();
	}

	/**
	 * Test path-ID presence rather than truthiness. /users/0 must remain a missing user
	 * instead of falling back to the current member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function member( \WP_REST_Request $request ): int {
		$path = $request->get_url_params();

		return array_key_exists( 'id', $path ) ? (int) $path['id'] : $this->caller();
	}

	public function page( \WP_REST_Request $request ): int {
		return max( 1, (int) $request->get_param( 'page' ) );
	}

	public function per_page( \WP_REST_Request $request ): int {
		return $this->clamp( $request->get_param( 'per_page' ), self::PER_PAGE_DEFAULT, 1, self::PER_PAGE_MAX );
	}

	public function avatar_size( \WP_REST_Request $request ): int {
		return $this->clamp( $request->get_param( 'avatar_size' ), self::AVATAR_DEFAULT, 1, self::AVATAR_MAX );
	}

	/**
	 * Accept scalar q values only; casting ?q[]=x would search for the string "Array".
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	public function terms( \WP_REST_Request $request ): string {
		$raw = $request->get_param( 'q' );

		return is_scalar( $raw ) ? trim( (string) $raw ) : '';
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function collection_args(): array {
		return array(
			'page'     => $this->integer_arg(
				array(
					'default' => 1,
					'minimum' => 1,
				)
			),
			'per_page' => $this->integer_arg(
				array(
					'default'           => self::PER_PAGE_DEFAULT,
					'sanitize_callback' => fn( $value ): int => $this->clamp( $value, self::PER_PAGE_DEFAULT, 1, self::PER_PAGE_MAX ),
				)
			),
		) + $this->avatar_args();
	}

	/**
	 * Hand-written REST arguments need a validate_callback; without one WordPress does not enforce schema type or bounds.
	 *
	 * @param array<string,mixed> $overrides Argument-specific keys; they win.
	 * @return array<string,mixed>
	 */
	public function integer_arg( array $overrides = array() ): array {
		return $overrides + array(
			'type'              => 'integer',
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * The validation callback makes required effective and prevents an empty search from reaching bbPress.
	 *
	 * @param array<string,mixed> $overrides Argument-specific keys; they win.
	 * @return array<string,mixed>
	 */
	public function string_arg( array $overrides = array() ): array {
		return $overrides + array(
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Data contract.
	 *
	 * @param array{0:object,1:string}          $callback Controller method to answer with.
	 * @param array<string,array<string,mixed>> $args     Argument schema.
	 * @return array<string,mixed>
	 */
	public function readable_route( array $callback, array $args = array() ): array {
		return array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => $callback,
			'permission_callback' => '__return_true',
			'args'                => $args,
		);
	}

	/**
	 * Data contract.
	 *
	 * @param array{0:object,1:string}          $callback   Controller method to answer with.
	 * @param array{0:object,1:string}          $permission Permission callback.
	 * @param array<string,array<string,mixed>> $args       Argument schema.
	 * @param string                            $methods    HTTP verbs, comma separated.
	 * @return array<string,mixed>
	 */
	public function authenticated_route(
		array $callback,
		array $permission,
		array $args = array(),
		string $methods = \WP_REST_Server::READABLE
	): array {
		return array(
			'methods'             => $methods,
			'callback'            => $callback,
			'permission_callback' => $permission,
			'args'                => $args,
		);
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function avatar_args(): array {
		return array(
			'avatar_size' => $this->integer_arg(
				array(
					'default'           => self::AVATAR_DEFAULT,
					'sanitize_callback' => fn( $value ): int => $this->clamp( $value, self::AVATAR_DEFAULT, 1, self::AVATAR_MAX ),
				)
			),
		);
	}

	private function clamp( $value, int $fallback, int $min, int $max ): int {
		if ( null === $value || '' === $value ) {
			return $fallback;
		}

		return max( $min, min( $max, (int) $value ) );
	}
}
