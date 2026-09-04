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
 */
class FeatureGate {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Data contract.
	 *
	 * @return true|\WP_Error
	 */
	public function topic_tags() {
		return $this->rest->topic_tags_enabled()
			? true
			: $this->disabled( 'tags_disabled', __( 'Topic tags are not enabled on this forum.', 'jtzl-bulletin' ) );
	}

	/**
	 * Data contract.
	 *
	 * @return true|\WP_Error
	 */
	public function search() {
		return $this->wp->allow_search()
			? true
			: $this->disabled( 'search_disabled', __( 'Search is not enabled on this forum.', 'jtzl-bulletin' ) );
	}

	/**
	 * Data contract.
	 *
	 * @return true|\WP_Error
	 */
	public function favorites() {
		return $this->rest->favorites_enabled()
			? true
			: $this->disabled( 'favorites_disabled', __( 'Favourites are not enabled on this forum.', 'jtzl-bulletin' ) );
	}

	/**
	 * Data contract.
	 *
	 * @return true|\WP_Error
	 */
	public function subscriptions() {
		return $this->wp->is_subscriptions_active()
			? true
			: $this->disabled( 'subscriptions_disabled', __( 'Subscriptions are not enabled on this forum.', 'jtzl-bulletin' ) );
	}

	private function disabled( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => 403 ) );
	}
}
