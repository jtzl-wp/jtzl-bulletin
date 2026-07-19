<?php
/**
 * Plugin container holder.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin;

use DI\Container;

/**
 * Lazy singleton holder for the DI container, with setters that let tests inject
 * or reset it. Holds no business logic — the wiring lives in ContainerFactory
 * and the hook registration in Bootstrap.
 */
class Plugin {

	/**
	 * The shared container instance.
	 *
	 * @var Container|null
	 */
	private static ?Container $container = null;

	/**
	 * Get (building on first access) the DI container.
	 *
	 * @return Container
	 */
	public static function get_container(): Container {
		if ( null === self::$container ) {
			self::$container = ContainerFactory::create();
		}
		return self::$container;
	}

	/**
	 * Replace the container (test seam).
	 *
	 * @param Container $container Container to use.
	 */
	public static function set_container( Container $container ): void {
		self::$container = $container;
	}

	/**
	 * Forget the container (test seam).
	 */
	public static function reset_container(): void {
		self::$container = null;
	}
}
