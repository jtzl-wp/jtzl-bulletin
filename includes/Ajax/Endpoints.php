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
 * ⚠ **Routed by bbPress, not by `admin-ajax.php`.** `bbp_ajax_{action}` is dispatched
 * from `bbp_do_ajax()` on `bbp_post_request`, off `bbp_template_redirect`
 * (`common/ajax.php:68`) — a front-end request, reached at `bbp_get_ajax_url()`.
 * That is worth stating where the binding happens, because `is_admin()` is **false**
 * on this route and **true** on the admin one, and WP_Query hands a status-less query
 * a wider status list under `is_admin()`. `Query\ProtectedStatusGuard` carries the
 * full account; the short version is that moving these five to `admin-ajax.php` would
 * change what the queries behind them return.
 *
 * ## Why this is a class rather than another method on Bootstrap
 *
 * `Bootstrap` had grown to fifteen `register_*` groups and sat one edit under PHPMD's
 * `ExcessiveClassLength` for three PRs — long enough that the next hook to be added
 * was going to be paid for by deleting an explanation somewhere, which is the wrong
 * trade every time. This is the first group lifted out along that seam: five
 * endpoints, one shape, nothing else reaches them. The rest is PR 6's.
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

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface               $wp         WordPress/bbPress seam.
	 * @param LoadRepliesController          $replies    Replies continuation.
	 * @param LoadTopicsController           $topics     Threads continuation.
	 * @param LoadForumsController           $forums     Forums continuation.
	 * @param LoadSubscribedForumsController $subscribed Subscribed-forums continuation.
	 * @param LoadSearchController           $search     Search continuation.
	 */
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
	 * Action names written out rather than composed from a slug: they are the plugin's
	 * public routes, and a grep for one has to find it.
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
