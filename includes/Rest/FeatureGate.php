<?php
/**
 * Which of bbPress's optional features this forum has switched on.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Tags, search, favourites, subscriptions — the four refusals that are not about a
 * reader.
 *
 * ## A different question from Rest\AccessPolicy's, asked in the same shape
 *
 * That class answers "may *this* reader have *this* thing", and every answer it gives
 * depends on who is asking. These four do not: a forum with tagging switched off has
 * it switched off for the keymaster too. They lived there first, apologising for
 * themselves in their own docblocks, and they moved out when the class ran out of
 * room — which is the useful kind of pressure, because the concept was already there
 * waiting to be named.
 *
 * The shape is deliberately identical: `true`, or the `WP_Error` a route can return
 * without translating it. A controller asks one of these exactly where it asks the
 * policy, and cannot tell from the call site which kind of refusal it is holding.
 *
 * ## Why a 403 and not a 404
 *
 * Because the feature's absence is not a secret and the resource is not hidden. bbPress
 * leaves the tag taxonomy registered and the search template reachable when their
 * settings are off, and a favourites-less forum still has topics; answering 404 would
 * tell the app the route does not exist, and an app that believed it would stop asking
 * on a forum that turns the setting back on tomorrow. `403` with a named code says the
 * right thing: the address is real, the feature is not here.
 *
 * ## Read from bbPress every time, never cached
 *
 * All four are options a keymaster can change mid-session, and none of them is
 * expensive — bbPress reads them through `get_option()`, which is memoised for the
 * request anyway. Holding a copy here would be a copy that can be stale.
 *
 * @since 0.6.0
 */
class FeatureGate {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp   WordPress/bbPress seam.
	 * @param RestContextInterface $rest REST seam.
	 */
	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Is there a tag vocabulary to ask about at all?
	 *
	 * @since 0.6.0
	 *
	 * @return true|\WP_Error
	 */
	public function topic_tags() {
		return $this->rest->topic_tags_enabled()
			? true
			: $this->disabled( 'tags_disabled', __( 'Topic tags are not enabled on this forum.', 'jtzl-bulletin' ) );
	}

	/**
	 * Is there a search to run at all?
	 *
	 * @since 0.6.0
	 *
	 * @return true|\WP_Error
	 */
	public function search() {
		return $this->wp->allow_search()
			? true
			: $this->disabled( 'search_disabled', __( 'Search is not enabled on this forum.', 'jtzl-bulletin' ) );
	}

	/**
	 * Is there anything to favourite with?
	 *
	 * The first of the four that gates a *write*, which changes nothing about the
	 * answer and everything about what a wrong one costs: a member whose forum has
	 * favourites switched off must be told so, not handed a 200 for a relationship
	 * that was never stored.
	 *
	 * @since 0.6.0
	 *
	 * @return true|\WP_Error
	 */
	public function favorites() {
		return $this->rest->favorites_enabled()
			? true
			: $this->disabled( 'favorites_disabled', __( 'Favourites are not enabled on this forum.', 'jtzl-bulletin' ) );
	}

	/**
	 * Is there anything to subscribe with?
	 *
	 * ⚠ Asked of WordPress\ContextInterface, not the REST seam. The website's Subscribe
	 * control has consulted `is_subscriptions_active()` since 0.3.0, and one setting
	 * read through two adapters is one setting that can come to disagree with itself —
	 * the app would offer a control the same forum's website does not.
	 *
	 * @since 0.6.0
	 *
	 * @return true|\WP_Error
	 */
	public function subscriptions() {
		return $this->wp->is_subscriptions_active()
			? true
			: $this->disabled( 'subscriptions_disabled', __( 'Subscriptions are not enabled on this forum.', 'jtzl-bulletin' ) );
	}

	/**
	 * The refusal all four share.
	 *
	 * @since 0.6.0
	 *
	 * @param string $code    Stable, lowercase, snake case.
	 * @param string $message What is off, in a sentence with no bbPress in it.
	 * @return \WP_Error
	 */
	private function disabled( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => 403 ) );
	}
}
