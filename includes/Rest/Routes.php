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
	 * Every controller the API answers through, in registration order.
	 *
	 * ## Why this is a list and not nine named collaborators
	 *
	 * It was nine, and PHPMD is what changed it: an eighth controller put this class at
	 * coupling 10 and its constructor at 9 parameters, against gates of 10 and 9. The
	 * fix is not a suppression, because the gate was reporting something true — a class
	 * that names every controller in the API acquires a dependency on each of them, and
	 * that is one edge per resource for ever.
	 *
	 * What this class actually does needs none of those names. It registers whatever
	 * controllers it is handed under one namespace; *which* controllers exist is a
	 * composition decision, and composition is `ContainerFactory`'s — the one place in
	 * the plugin already exempt from these gates, and exempt for exactly this reason.
	 * The list lives there now, and adding a resource is a line in it.
	 *
	 * ⚠ **Order is not significant to WordPress** — routes are matched by pattern — but
	 * it is what `Rest\EndpointsTest` asserts against, so the container keeps it in the
	 * order a reader would expect: the hierarchy first, then the two ways in that begin
	 * nowhere in it.
	 *
	 * @var ControllerInterface[]
	 * @since 0.6.0
	 */
	private array $controllers;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface      $wp          WordPress/bbPress seam.
	 * @param ControllerInterface[] $controllers Every controller the API answers through.
	 */
	public function __construct( ContextInterface $wp, array $controllers ) {
		$this->wp          = $wp;
		$this->controllers = $controllers;
	}

	/**
	 * Register every route. Runs on `rest_api_init`.
	 *
	 * @since 0.6.0
	 */
	public function register(): void {
		foreach ( $this->controllers as $controller ) {
			foreach ( $controller->routes() as $route => $args ) {
				$this->wp->register_rest_route( self::NAMESPACE_V1, $route, $args );
			}
		}
	}
}
