<?php
/**
 * The bounds a request may ask for.
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
 * Page, page size and avatar size — read from a request, brought inside their limits,
 * and declared as route arguments.
 *
 * ## Bounds clamp; they do not reject
 *
 * `per_page` is 1–100 and `avatar_size` is 1–512, and a request outside those bounds
 * is served rather than refused. That is a deliberate contract choice — a mobile
 * client guessing at a page size should receive a page, not an error it has to learn
 * to parse — and it dictates something non-obvious about the schemas below: they carry
 * no `minimum`/`maximum` for those two values. WordPress validates schema bounds
 * *before* the callback runs, so declaring them would turn the documented clamp into a
 * 400, and nothing in a controller could undo it.
 *
 * `page` is different. An unusable page number is a mistake worth reporting, so it
 * keeps `minimum` and is validated; the accessor still clamps, as the floor beneath a
 * schema some future route forgets to declare.
 *
 * ⚠ **And a declared bound only exists if a `validate_callback` does** — see
 * `integer_arg()`. `register_rest_route()` supplies none, and
 * `WP_REST_Request::has_valid_params()` skips any argument without one, so a schema
 * that names `minimum` and stops there is decoration. That is why every argument here
 * is built through one method rather than written out where it is used.
 *
 * @since 0.6.0
 */
class RequestBounds {

	/**
	 * Default rows per page.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const PER_PAGE_DEFAULT = 10;

	/**
	 * Largest page a client may ask for.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const PER_PAGE_MAX = 100;

	/**
	 * Default avatar edge, in pixels.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const AVATAR_DEFAULT = 96;

	/**
	 * Largest avatar edge, in pixels.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const AVATAR_MAX = 512;

	/**
	 * The page a request asks for.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function page( \WP_REST_Request $request ): int {
		return max( 1, (int) $request->get_param( 'page' ) );
	}

	/**
	 * How many rows a request asks for, within bounds.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function per_page( \WP_REST_Request $request ): int {
		return $this->clamp( $request->get_param( 'per_page' ), self::PER_PAGE_DEFAULT, 1, self::PER_PAGE_MAX );
	}

	/**
	 * What size avatar a request asks for, within bounds.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function avatar_size( \WP_REST_Request $request ): int {
		return $this->clamp( $request->get_param( 'avatar_size' ), self::AVATAR_DEFAULT, 1, self::AVATAR_MAX );
	}

	/**
	 * Route arguments every collection accepts.
	 *
	 * @since 0.6.0
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
	 * An integer route argument, declared so WordPress will actually check it.
	 *
	 * ⚠ **`register_rest_route()` adds no `validate_callback` for you.** WordPress
	 * fills one in only through `rest_get_endpoint_args_for_schema()`, which is a
	 * `WP_REST_Controller` convenience and never runs for hand-written route args —
	 * and `WP_REST_Request::has_valid_params()` skips any argument that has not got
	 * one. So a schema declaring `type` and `minimum` and nothing else is *inert*: a
	 * `page` of 0 or `abc` reaches the callback exactly as 5 does. Every integer
	 * argument in this plugin is built here so that cannot be declared by accident.
	 *
	 * @since 0.6.0
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
	 * The avatar-size argument, accepted by every route that serializes a person.
	 *
	 * @since 0.6.0
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

	/**
	 * A requested number brought inside its bounds, or the default when absent.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $value    Whatever the request carried.
	 * @param int   $fallback Value for an absent parameter.
	 * @param int   $min      Lowest allowed.
	 * @param int   $max      Highest allowed.
	 * @return int
	 */
	private function clamp( $value, int $fallback, int $min, int $max ): int {
		if ( null === $value || '' === $value ) {
			return $fallback;
		}

		return max( $min, min( $max, (int) $value ) );
	}
}
