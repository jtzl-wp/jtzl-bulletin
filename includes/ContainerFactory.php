<?php
/**
 * DI container factory.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin;

use DI\Container;
use DI\ContainerBuilder;
use JTZL\Bulletin\Asset\AssetManager;
use JTZL\Bulletin\Asset\BuiltAssets;
use JTZL\Bulletin\Takeover\TemplateController;
use JTZL\Bulletin\Unread\ReadCursor;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContext;
use JTZL\Bulletin\WordPress\RestContextInterface;
use JTZL\Bulletin\WordPress\WordPressContext;
use function DI\autowire;

/**
 * Builds and configures the PHP-DI container. All service definitions live in
 * one place; the composition root (Bootstrap) then resolves services and binds
 * them to WordPress hooks.
 *
 * @since 0.1.0
 */
class ContainerFactory {

	/**
	 * Build the container.
	 *
	 * Compilation is enabled in production (into var/cache) and disabled when
	 * WP_DEBUG is on, so local development never serves a stale compiled container.
	 *
	 * @since 0.1.0
	 *
	 * @param bool|null $enable_compilation Force compilation on/off (test seam).
	 * @return Container
	 */
	public static function create( ?bool $enable_compilation = null ): Container {
		$builder = new ContainerBuilder();

		$should_compile = $enable_compilation ?? ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG );
		if ( $should_compile && defined( 'JTZL_BLTN_DIR' ) ) {
			$cache_dir = JTZL_BLTN_DIR . 'var/cache';
			if ( ! file_exists( $cache_dir ) ) {
				wp_mkdir_p( $cache_dir );
			}
			$builder->enableCompilation( $cache_dir );
		}

		$builder->useAutowiring( true );
		$builder->addDefinitions( self::get_definitions() );

		return $builder->build();
	}

	/**
	 * The container definitions.
	 *
	 * Autowiring resolves everything that needs only other services; the few
	 * scalar constructor arguments (plugin paths, version, template dir) are
	 * supplied here.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string,mixed>
	 */
	private static function get_definitions(): array {
		$plugin_dir    = defined( 'JTZL_BLTN_DIR' ) ? JTZL_BLTN_DIR : '';
		$plugin_url    = defined( 'JTZL_BLTN_URL' ) ? JTZL_BLTN_URL : '';
		$plugin_ver    = defined( 'JTZL_BLTN_VERSION' ) ? JTZL_BLTN_VERSION : '0.0.0';
		$templates_dir = $plugin_dir . 'templates/';

		return array(
			ContextInterface::class     => autowire( WordPressContext::class ),

			// A second seam beside the first, for what only the API asks. Both are
			// boundary classes over the same globals; they are separate so the
			// browser's double does not have to grow the API's whole surface.
			RestContextInterface::class => autowire( RestContext::class ),

			// The one global the container cannot autowire: \wpdb is constructed by
			// WordPress before any of this runs, and there is exactly one of it.
			// Database\Schema and Unread\ReadState take it as a constructor argument
			// like any other dependency, so they stay testable against a handle a test
			// supplies rather than reaching for the global themselves.
			\wpdb::class                => static function (): \wpdb {
				global $wpdb;

				return $wpdb;
			},

			// The signing secret is a WordPress value, not a service, and asking for it
			// through the context seam would add a method with one caller. Bound here
			// instead, where every other scalar this container supplies is bound.
			ReadCursor::class           => static fn(): ReadCursor => new ReadCursor( wp_salt( 'auth' ) ),

			BuiltAssets::class          => autowire()
				->constructorParameter( 'plugin_dir', $plugin_dir )
				->constructorParameter( 'plugin_url', $plugin_url ),

			AssetManager::class         => autowire()
				->constructorParameter( 'version', $plugin_ver ),

			TemplateController::class   => autowire()
				->constructorParameter( 'templates_dir', $templates_dir ),
		);
	}
}
