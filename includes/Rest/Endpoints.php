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
 *
 * ## Why a registrar rather than another group on Bootstrap
 *
 * The same seam `Ajax\Endpoints` was lifted out along in 0.5.0. `Bootstrap` is the
 * composition root and stays the one place hooks are registered, but a controller
 * group that owns a coherent surface owns its own bindings — so what the API binds is
 * readable in one short file instead of being a paragraph inside a class that also
 * wires assets, chrome and the takeover.
 *
 * ## `posts_where` at 20
 *
 * Priority is the whole of the collection scope's correctness. `posts_where` carries
 * four of Bulletin's filters:
 *
 * | Priority | Filter | Direction |
 * |---|---|---|
 * | 10 | Query\ProtectedStatusGuard, Query\SearchVisibility | narrow |
 * | 20 | Rest\CollectionVisibility | narrow |
 * | `PHP_INT_MAX` | Query\PendingVisibility | widen |
 *
 * Narrowing before widening is what keeps the widening meaningful: the pending clause
 * wraps whatever it is handed and OR-s one fully-bound predicate beside it, so a
 * narrowing that ran afterwards would subtract from outside the wrap and take the row
 * away again. 20 is unique on the hook, which is why registering this group last
 * costs nothing — WordPress sorts by priority before it sorts by registration order.
 *
 * ⚠ **Two accepted arguments, not one.** Rest\CollectionVisibility decides whether a
 * query is one of ours by reading a marker off the query object. Registered for one
 * argument it would never receive the query, take its `$query = null` early return,
 * and hand back the clause untouched — a silent no-op that removes the forum scope
 * from every collection the API serves. Tests\Rest\EndpointsTest asserts the number.
 *
 * ## Routes on `rest_api_init`, and nowhere earlier
 *
 * Route declaration builds every controller's argument schema. Doing it at plugin
 * load would pay for that on requests that are not REST requests, and would do it
 * before WordPress has resolved the current user — which is the input to every
 * visibility answer underneath.
 *
 * @since 0.6.0
 */
class Endpoints {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * The forum scope every collection is narrowed to.
	 *
	 * @var CollectionVisibility
	 * @since 0.6.0
	 */
	private CollectionVisibility $visibility;

	/**
	 * The API's routes.
	 *
	 * @var Routes
	 * @since 0.6.0
	 */
	private Routes $routes;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp         WordPress/bbPress seam.
	 * @param CollectionVisibility $visibility Collection scope.
	 * @param Routes               $routes     The API's routes.
	 */
	public function __construct( ContextInterface $wp, CollectionVisibility $visibility, Routes $routes ) {
		$this->wp         = $wp;
		$this->visibility = $visibility;
		$this->routes     = $routes;
	}

	/**
	 * Bind the API.
	 *
	 * @since 0.6.0
	 */
	public function register(): void {
		$this->wp->add_filter( 'posts_where', array( $this->visibility, 'restrict' ), 20, 2 );
		$this->wp->add_action( 'rest_api_init', array( $this->routes, 'register' ) );
	}
}
