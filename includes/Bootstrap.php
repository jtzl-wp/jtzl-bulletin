<?php
/**
 * Runtime hook registration (composition root).
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin;

use DI\Container;
use JTZL\Bulletin\Ajax\LoadForumsController;
use JTZL\Bulletin\Ajax\LoadRepliesController;
use JTZL\Bulletin\Ajax\LoadSubscribedForumsController;
use JTZL\Bulletin\Ajax\LoadSearchController;
use JTZL\Bulletin\Ajax\LoadTopicsController;
use JTZL\Bulletin\Asset\AssetManager;
use JTZL\Bulletin\Chrome\AdminBar;
use JTZL\Bulletin\Query\SearchVisibility;
use JTZL\Bulletin\Query\StableOrder;
use JTZL\Bulletin\Query\StickyHoisting;
use JTZL\Bulletin\Query\SubscribedForumQuery;
use JTZL\Bulletin\Takeover\TemplateController;
use JTZL\Bulletin\View\ProfileIdentity;
use JTZL\Bulletin\View\ProtectedRowContent;
use JTZL\Bulletin\View\SubscribedForumsMore;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Resolves services from the container and binds them to WordPress/bbPress
 * hooks through the context seam. This is the only place hooks are registered.
 *
 * Coupling is exempted deliberately, the same way ContainerFactory is excluded in
 * phpmd.xml: this is the composition root — deptrac's Root layer grants it every
 * internal layer on purpose — so its coupling counts the services the plugin has,
 * which is the thing it exists to wire. Honouring the cap would mean either moving
 * hook registration out of the one place that does it, or declining to add a
 * service. The size and complexity rules still apply, so register_hooks() cannot
 * quietly grow into something unreadable behind this.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 *
 * @since 0.1.0
 */
class Bootstrap {

	/**
	 * The DI container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Container $container The DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register every runtime hook.
	 *
	 * Grouped by what each set of hooks is for rather than kept in one run: the list
	 * grows with every feature, and one method that registers all of it eventually
	 * says nothing about which hooks belong together. The groups are private and
	 * called from here alone, so this is still the one place hooks are registered.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks(): void {
		$this->register_takeover();
		$this->register_assets();
		$this->register_ajax();
		$this->register_reskin();
		$this->register_search_visibility();
		$this->register_chrome();
	}

	/**
	 * Takeover: redirect single replies early, strip theme-compat, swap our document.
	 *
	 * @since 0.3.0
	 */
	private function register_takeover(): void {
		$wp       = $this->wp();
		$takeover = $this->container->get( TemplateController::class );
		assert( $takeover instanceof TemplateController );

		$wp->add_action( 'template_redirect', array( $takeover, 'redirect_single_reply' ), 9 );
		$wp->add_action( 'template_redirect', array( $takeover, 'prime_takeover' ) );
		$wp->add_filter( 'bbp_template_include', array( $takeover, 'filter_template_include' ), 20 );
	}

	/**
	 * Assets: enqueue ours, then suppress foreign styles late.
	 *
	 * @since 0.3.0
	 */
	private function register_assets(): void {
		$wp     = $this->wp();
		$assets = $this->container->get( AssetManager::class );
		assert( $assets instanceof AssetManager );

		$wp->add_action( 'wp_enqueue_scripts', array( $assets, 'enqueue' ) );
		$wp->add_action( 'wp_enqueue_scripts', array( $assets, 'suppress_foreign_styles' ), 100 );
	}

	/**
	 * Load-more over bbPress's front-end AJAX router: replies inside a thread,
	 * threads inside a forum, forums inside the index or a parent forum — and the
	 * first continuation on a reskin screen, the Subscribed Forums list on a member
	 * profile, which bbPress renders in its own markup and truncates at the same
	 * 50-forum ceiling (issue #50) — and results inside a search (issue #35), the
	 * only one of the five whose subject is a set of terms rather than a post.
	 *
	 * @since 0.3.0
	 */
	private function register_ajax(): void {
		$wp      = $this->wp();
		$replies = $this->container->get( LoadRepliesController::class );
		assert( $replies instanceof LoadRepliesController );
		$topics = $this->container->get( LoadTopicsController::class );
		assert( $topics instanceof LoadTopicsController );
		$forums = $this->container->get( LoadForumsController::class );
		assert( $forums instanceof LoadForumsController );
		$subscribed = $this->container->get( LoadSubscribedForumsController::class );
		assert( $subscribed instanceof LoadSubscribedForumsController );
		$search = $this->container->get( LoadSearchController::class );
		assert( $search instanceof LoadSearchController );

		$wp->add_action( 'bbp_ajax_bulletin_load_replies', array( $replies, 'handle' ) );
		$wp->add_action( 'bbp_ajax_bulletin_load_topics', array( $topics, 'handle' ) );
		$wp->add_action( 'bbp_ajax_bulletin_load_forums', array( $forums, 'handle' ) );
		$wp->add_action( 'bbp_ajax_bulletin_load_subscribed_forums', array( $subscribed, 'handle' ) );
		$wp->add_action( 'bbp_ajax_bulletin_load_search', array( $search, 'handle' ) );
	}

	/**
	 * Reskin: what we add to, or correct in, the markup bbPress renders itself.
	 *
	 * @since 0.3.0
	 */
	private function register_reskin(): void {
		$wp       = $this->wp();
		$identity = $this->container->get( ProfileIdentity::class );
		assert( $identity instanceof ProfileIdentity );
		$order = $this->container->get( StableOrder::class );
		assert( $order instanceof StableOrder );
		$stickies = $this->container->get( StickyHoisting::class );
		assert( $stickies instanceof StickyHoisting );
		$subscriptions = $this->container->get( SubscribedForumQuery::class );
		assert( $subscriptions instanceof SubscribedForumQuery );
		$subscribed_more = $this->container->get( SubscribedForumsMore::class );
		assert( $subscribed_more instanceof SubscribedForumsMore );
		$protected = $this->container->get( ProtectedRowContent::class );
		assert( $protected instanceof ProtectedRowContent );

		// Give the member-profile header a coherent identity block (name + @handle +
		// role beside the avatar). The hook fires only inside bbPress's user-details
		// template — i.e. the reskinned profile screens — so it never touches the
		// takeover documents.
		$wp->add_action( 'bbp_template_before_user_details_menu_items', array( $identity, 'render' ) );

		// Hang a continuation off the Subscribed Forums list, the one reskin loop
		// bbPress leaves with no way past its first page. Both halves are scoped to
		// the subscriptions tab: the control by the hook and the conditional (see
		// View\SubscribedForumsMore), the query by the conditional alone, since
		// bbPress's own template is what calls it and passes no arguments to intercept.
		$wp->add_action( 'bbp_template_after_forums_loop', array( $subscribed_more, 'render' ) );
		$wp->add_filter( 'bbp_after_has_forums_parse_args', array( $subscriptions, 'filter_forum_args' ) );

		// Give the loops bbPress queries for us a deterministic order. These fire in
		// the moment before bbPress runs the query, on every call — including the
		// profile tabs, which reach bbp_has_topics() with args of their own.
		$wp->add_filter( 'bbp_after_has_topics_parse_args', array( $order, 'filter_topic_args' ) );
		$wp->add_filter( 'bbp_after_has_replies_parse_args', array( $order, 'filter_reply_args' ) );

		// And decline bbPress's sticky hoisting there, which serves a sticky twice and
		// miscounts the page it hoisted onto. Same hook, separate decision.
		$wp->add_filter( 'bbp_after_has_topics_parse_args', array( $stickies, 'filter_topic_args' ), 11 );

		// Keep WordPress's password form out of the description slot of a loop row,
		// where bbPress's own row templates would otherwise print it as though it were
		// what the forum is about (issue #54). Armed by the four actions bbPress fires
		// around those two slots and by nothing else, so the forms Bulletin renders on
		// purpose — the in-shell prompt (#18), and a protected reply's own prompt in the
		// reading view — cannot be reached by it. See View\ProtectedRowContent.
		$wp->add_action( 'bbp_theme_before_forum_description', array( $protected, 'open_row_slot' ) );
		$wp->add_action( 'bbp_theme_after_forum_description', array( $protected, 'close_row_slot' ) );
		$wp->add_action( 'bbp_theme_before_reply_content', array( $protected, 'open_row_slot' ) );
		$wp->add_action( 'bbp_theme_after_reply_content', array( $protected, 'close_row_slot' ) );
		$wp->add_filter( 'the_password_form', array( $protected, 'filter_password_form' ) );
	}

	/**
	 * Search visibility: give a search query back the post statuses bbPress
	 * computed for the reader running it and then overwrote (issue #68) — which
	 * loses every `closed` topic and admits `private` and `hidden` ones.
	 *
	 * Its own group because it belongs to neither tier. The captured list rides
	 * the query object, so these fire on any query built by
	 * `bbp_has_search_results()` and on nothing else — bbPress's own search
	 * template included, the defect being upstream of our takeover.
	 *
	 * `pre_get_posts` at 5 lands immediately after bbPress's normalizer at 4, the
	 * narrowest way to undo one substitution. See Query\SearchVisibility.
	 *
	 * @since 0.3.0
	 */
	private function register_search_visibility(): void {
		$wp     = $this->wp();
		$search = $this->container->get( SearchVisibility::class );
		assert( $search instanceof SearchVisibility );

		// Last on the arguments filter, so what is copied is what the query will
		// actually use: a site that widens or narrows post_status through the same
		// hook has had its say by then, and a copy taken before it would restore
		// bbPress's answer over the site's own.
		$wp->add_filter( 'bbp_after_has_search_results_parse_args', array( $search, 'capture_statuses' ), PHP_INT_MAX );
		$wp->add_action( 'pre_get_posts', array( $search, 'widen_statuses' ), 5 );
		$wp->add_filter( 'posts_where', array( $search, 'restrict_statuses' ), 10, 2 );
	}

	/**
	 * Chrome: keep WordPress's admin bar off our screens for readers who cannot
	 * administrate. Late, so ours is the last word on the shell we render — an
	 * administrator's own preference still passes through (see Chrome\AdminBar).
	 *
	 * @since 0.3.0
	 */
	private function register_chrome(): void {
		$admin_bar = $this->container->get( AdminBar::class );
		assert( $admin_bar instanceof AdminBar );

		$this->wp()->add_filter( 'show_admin_bar', array( $admin_bar, 'filter_show_admin_bar' ), 100 );
	}

	/**
	 * The WordPress/bbPress seam every group binds through.
	 *
	 * @since 0.3.0
	 *
	 * @return ContextInterface
	 */
	private function wp(): ContextInterface {
		$wp = $this->container->get( ContextInterface::class );
		assert( $wp instanceof ContextInterface );

		return $wp;
	}
}
