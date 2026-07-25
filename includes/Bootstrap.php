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
use JTZL\Bulletin\Ajax\LoadTopicsController;
use JTZL\Bulletin\Asset\AssetManager;
use JTZL\Bulletin\Chrome\AdminBar;
use JTZL\Bulletin\Query\StableOrder;
use JTZL\Bulletin\Query\StickyHoisting;
use JTZL\Bulletin\Takeover\TemplateController;
use JTZL\Bulletin\View\ProfileIdentity;
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
	 * @since 0.1.0
	 */
	public function register_hooks(): void {
		$wp = $this->container->get( ContextInterface::class );
		assert( $wp instanceof ContextInterface );
		$takeover = $this->container->get( TemplateController::class );
		assert( $takeover instanceof TemplateController );
		$assets = $this->container->get( AssetManager::class );
		assert( $assets instanceof AssetManager );
		$replies = $this->container->get( LoadRepliesController::class );
		assert( $replies instanceof LoadRepliesController );
		$topics = $this->container->get( LoadTopicsController::class );
		assert( $topics instanceof LoadTopicsController );
		$forums = $this->container->get( LoadForumsController::class );
		assert( $forums instanceof LoadForumsController );
		$identity = $this->container->get( ProfileIdentity::class );
		assert( $identity instanceof ProfileIdentity );
		$admin_bar = $this->container->get( AdminBar::class );
		assert( $admin_bar instanceof AdminBar );
		$order = $this->container->get( StableOrder::class );
		assert( $order instanceof StableOrder );
		$stickies = $this->container->get( StickyHoisting::class );
		assert( $stickies instanceof StickyHoisting );

		// Takeover: redirect single replies early, strip theme-compat, swap our doc.
		$wp->add_action( 'template_redirect', array( $takeover, 'redirect_single_reply' ), 9 );
		$wp->add_action( 'template_redirect', array( $takeover, 'prime_takeover' ) );
		$wp->add_filter( 'bbp_template_include', array( $takeover, 'filter_template_include' ), 20 );

		// Assets: enqueue ours, then suppress foreign styles late.
		$wp->add_action( 'wp_enqueue_scripts', array( $assets, 'enqueue' ) );
		$wp->add_action( 'wp_enqueue_scripts', array( $assets, 'suppress_foreign_styles' ), 100 );

		// Load-more over bbPress's front-end AJAX router: replies inside a thread,
		// threads inside a forum, forums inside the index or a parent forum.
		$wp->add_action( 'bbp_ajax_bulletin_load_replies', array( $replies, 'handle' ) );
		$wp->add_action( 'bbp_ajax_bulletin_load_topics', array( $topics, 'handle' ) );
		$wp->add_action( 'bbp_ajax_bulletin_load_forums', array( $forums, 'handle' ) );

		// Reskin: give the member-profile header a coherent identity block (name +
		// @handle + role beside the avatar). The hook fires only inside bbPress's
		// user-details template — i.e. the reskinned profile screens — so it never
		// touches the takeover documents.
		$wp->add_action( 'bbp_template_before_user_details_menu_items', array( $identity, 'render' ) );

		// Chrome: keep WordPress's admin bar off our screens for readers who cannot
		// administrate. Late, so ours is the last word on the shell we render — an
		// administrator's own preference still passes through (see Chrome\AdminBar).
		$wp->add_filter( 'show_admin_bar', array( $admin_bar, 'filter_show_admin_bar' ), 100 );

		// Reskin: give the loops bbPress queries for us a deterministic order. These
		// fire in the moment before bbPress runs the query, on every call — including
		// the profile tabs, which reach bbp_has_topics() with args of their own.
		$wp->add_filter( 'bbp_after_has_topics_parse_args', array( $order, 'filter_topic_args' ) );
		$wp->add_filter( 'bbp_after_has_replies_parse_args', array( $order, 'filter_reply_args' ) );

		// And decline bbPress's sticky hoisting there, which serves a sticky twice and
		// miscounts the page it hoisted onto. Same hook, separate decision.
		$wp->add_filter( 'bbp_after_has_topics_parse_args', array( $stickies, 'filter_topic_args' ), 11 );
	}
}
