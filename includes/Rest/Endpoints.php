<?php
/**
 * What the API binds to WordPress.
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
 * The API's registrar: two bindings, and both of them are decisions.
 */
class Endpoints {

	private ContextInterface $wp;

	private CollectionVisibility $visibility;

	private Routes $routes;

	public function __construct( ContextInterface $wp, CollectionVisibility $visibility, Routes $routes ) {
		$this->wp         = $wp;
		$this->visibility = $visibility;
		$this->routes     = $routes;
	}

	public function register(): void {
		$this->wp->add_filter( 'posts_where', array( $this->visibility, 'restrict' ), 20, 2 );
		$this->wp->add_action( 'rest_api_init', array( $this->routes, 'register' ) );
	}
}
