<?php
/**
 * What every REST controller declares.
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
 * A controller says which routes it answers; Rest\Routes is what registers them.
 *
 * Splitting it that way keeps a controller out of the business of talking to
 * WordPress at all. It declares paths, methods, permission callbacks and argument
 * schemas — data — and never holds the seam it would need to bind them, so the
 * versioned namespace stays in one place and a controller cannot register a route
 * under a different one by accident.
 *
 * @since 0.6.0
 */
interface ControllerInterface {

	/**
	 * The routes this controller answers, keyed by route pattern.
	 *
	 * Each value is exactly what `register_rest_route()` takes for one route,
	 * minus the namespace.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array;
}
