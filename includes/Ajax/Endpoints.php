<?php
/**
 * The five load-more endpoints, and the routes they answer on.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Ajax;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Binds each continuation controller to the bbPress AJAX action it answers.
 *
 * These use bbPress's front-end router, not admin-ajax.php. Moving them would change
 * `is_admin()` and could change the post statuses selected by WP_Query. See
 * Query\ProtectedStatusGuard.
 *
 * @since 0.5.0
 */
class Endpoints {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Replies continuation.
	 *
	 * @var LoadRepliesController
	 */
	private LoadRepliesController $replies;

	/**
	 * Threads continuation.
	 *
	 * @var LoadTopicsController
	 */
	private LoadTopicsController $topics;

	/**
	 * Forums continuation.
	 *
	 * @var LoadForumsController
	 */
	private LoadForumsController $forums;

	/**
	 * Subscribed-forums continuation.
	 *
	 * @var LoadSubscribedForumsController
	 */
	private LoadSubscribedForumsController $subscribed;

	/**
	 * Search continuation.
	 *
	 * @var LoadSearchController
	 */
	private LoadSearchController $search;

	public function __construct(
		ContextInterface $wp,
		LoadRepliesController $replies,
		LoadTopicsController $topics,
		LoadForumsController $forums,
		LoadSubscribedForumsController $subscribed,
		LoadSearchController $search
	) {
		$this->wp         = $wp;
		$this->replies    = $replies;
		$this->topics     = $topics;
		$this->forums     = $forums;
		$this->subscribed = $subscribed;
		$this->search     = $search;
	}

	/**
	 * Bind every endpoint.
	 *
	 * Action names are explicit so each public route is searchable.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		$this->wp->add_action( 'bbp_ajax_bulletin_load_replies', array( $this->replies, 'handle' ) );
		$this->wp->add_action( 'bbp_ajax_bulletin_load_topics', array( $this->topics, 'handle' ) );
		$this->wp->add_action( 'bbp_ajax_bulletin_load_forums', array( $this->forums, 'handle' ) );
		$this->wp->add_action( 'bbp_ajax_bulletin_load_subscribed_forums', array( $this->subscribed, 'handle' ) );
		$this->wp->add_action( 'bbp_ajax_bulletin_load_search', array( $this->search, 'handle' ) );
	}
}
