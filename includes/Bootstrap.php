<?php
/**
 * Runtime hook registration (composition root).
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin;

use DI\Container;
use JTZL\Bulletin\Ajax\LoadRepliesController;
use JTZL\Bulletin\Ajax\LoadTopicsController;
use JTZL\Bulletin\Asset\AssetManager;
use JTZL\Bulletin\Takeover\TemplateController;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Resolves services from the container and binds them to WordPress/bbPress
 * hooks through the context seam. This is the only place hooks are registered.
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

		// Takeover: redirect single replies early, strip theme-compat, swap our doc.
		$wp->add_action( 'template_redirect', array( $takeover, 'redirect_single_reply' ), 9 );
		$wp->add_action( 'template_redirect', array( $takeover, 'prime_takeover' ) );
		$wp->add_filter( 'bbp_template_include', array( $takeover, 'filter_template_include' ), 20 );

		// Assets: enqueue ours, then suppress foreign styles late.
		$wp->add_action( 'wp_enqueue_scripts', array( $assets, 'enqueue' ) );
		$wp->add_action( 'wp_enqueue_scripts', array( $assets, 'suppress_foreign_styles' ), 100 );

		// Load-more over bbPress's front-end AJAX router: replies inside a thread,
		// threads inside a forum.
		$wp->add_action( 'bbp_ajax_bulletin_load_replies', array( $replies, 'handle' ) );
		$wp->add_action( 'bbp_ajax_bulletin_load_topics', array( $topics, 'handle' ) );
	}
}
