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
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => self::PER_PAGE_DEFAULT,
				'sanitize_callback' => fn( $value ): int => $this->clamp( $value, self::PER_PAGE_DEFAULT, 1, self::PER_PAGE_MAX ),
			),
		) + $this->avatar_args();
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
			'avatar_size' => array(
				'type'              => 'integer',
				'default'           => self::AVATAR_DEFAULT,
				'sanitize_callback' => fn( $value ): int => $this->clamp( $value, self::AVATAR_DEFAULT, 1, self::AVATAR_MAX ),
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
