<?php
/**
 * The API's route table.
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
 * One place that knows every route the API answers, and the namespace they answer on.
 */
class Routes {

	public const NAMESPACE_V1 = 'jtzl-bulletin/v1';

	private ContextInterface $wp;

	private array $controllers;

	public function __construct( ContextInterface $wp, array $controllers ) {
		$this->wp          = $wp;
		$this->controllers = $controllers;
	}

	public function register(): void {
		foreach ( $this->controllers as $controller ) {
			foreach ( $controller->routes() as $route => $args ) {
				$this->wp->register_rest_route( self::NAMESPACE_V1, $route, $args );
			}
		}
	}
}
