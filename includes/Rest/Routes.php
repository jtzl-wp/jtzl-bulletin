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
 *
 * Thin on purpose. It exists so that "what does this plugin expose over HTTP" is a
 * question with one short answer, and so that the namespace is a constant rather than
 * a string repeated in every controller — a versioned namespace whose version is
 * written out seven times is a version that gets bumped six.
 *
 * Controllers declare their own routes — paths, methods and argument schemas belong
 * beside the callbacks that answer them — but they do not register them. This class
 * does, so it is the only thing in the API that binds a route to WordPress, and a
 * controller cannot answer under some other namespace by accident. It also keeps the
 * seam out of every controller, which would otherwise carry a dependency it used for
 * one line.
 *
 * @since 0.6.0
 */
class Routes {

	/**
	 * The versioned namespace every Bulletin route answers on.
	 *
	 * ⚠ `jtzl-bulletin/v1`, matching the plugin's slug and text domain rather than
	 * its CSS prefix. The app team has this string in writing; it is part of the
	 * contract, not an implementation detail.
	 *
	 * @var string
	 * @since 0.6.0
	 */
	public const NAMESPACE_V1 = 'jtzl-bulletin/v1';

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * Forum reads.
	 *
	 * @var ForumController
	 * @since 0.6.0
	 */
	private ForumController $forums;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface $wp     WordPress/bbPress seam.
	 * @param ForumController  $forums Forum reads.
	 */
	public function __construct( ContextInterface $wp, ForumController $forums ) {
		$this->wp     = $wp;
		$this->forums = $forums;
	}

	/**
	 * Register every route. Runs on `rest_api_init`.
	 *
	 * @since 0.6.0
	 */
	public function register(): void {
		foreach ( $this->controllers() as $controller ) {
			foreach ( $controller->routes() as $route => $args ) {
				$this->wp->register_rest_route( self::NAMESPACE_V1, $route, $args );
			}
		}
	}

	/**
	 * Every controller the API answers through, in the order they are registered.
	 *
	 * The one list to add to when a resource is added. Order is not significant to
	 * WordPress — routes are matched by pattern — but it is what the tests assert
	 * against, so it stays the order a reader would expect: the hierarchy first.
	 *
	 * @since 0.6.0
	 *
	 * @return ControllerInterface[]
	 */
	private function controllers(): array {
		return array( $this->forums );
	}
}
