<?php
/**
 * Plugin Name:       Bulletin for bbPress
 * Plugin URI:        https://github.com/jtzl-wp/jtzl-bulletin
 * Description:       A mobile-first, decluttered reading layer for bbPress. The post is the hero; navigation is deliberately secondary. Renders its own minimal document on the reading screens, wraps every other bbPress screen in the same chrome, and leaves the rest of the site on its own theme.
 * Version:           0.5.3
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Requires Plugins:  bbpress
 * Author:            JTZL
 * Author URI:        https://github.com/jtzl-wp
 * Text Domain:       jtzl-bulletin
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Bulletin is a companion plugin — it never edits bbPress core. On the four
 * reading screens (forums index, a single forum, a single topic, search) it takes
 * over the `bbp_template_include` filter and renders its own minimal document,
 * while still firing wp_head()/wp_footer() so WordPress core and other plugins
 * keep working. Every other reader-reachable bbPress screen keeps bbPress's own
 * markup and renders inside the same chrome; the only ones left on the active
 * theme are bbPress's topic, reply and forum edit forms, which
 * Screen\ScreenClassifier excludes by name. Non-bbPress pages are untouched.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JTZL_BLTN_VERSION', '0.5.3' );
define( 'JTZL_BLTN_FILE', __FILE__ );
define( 'JTZL_BLTN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JTZL_BLTN_URL', plugin_dir_url( __FILE__ ) );
define( 'JTZL_BLTN_BASENAME', plugin_basename( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Boot the plugin after all plugins have loaded, so bbPress is guaranteed to be
 * present and its conditional tags (is_bbpress(), etc.) are defined.
 *
 * Priority 20 keeps us safely after bbPress's own default-priority bootstrap.
 *
 * @since 0.1.0
 */
function jtzl_bltn_boot() {
	// Companion plugin: with no bbPress there is nothing to declutter. Fail
	// loudly in the admin, quietly on the front end. The test harness always loads
	// bbPress, so this defensive branch — like a direct-access guard — cannot run
	// under coverage.
	if ( ! function_exists( 'is_bbpress' ) ) {
		// @codeCoverageIgnoreStart
		add_action( 'admin_notices', 'jtzl_bltn_notice_missing_bbpress' );
		return;
		// @codeCoverageIgnoreEnd
	}

	$bootstrap = new \JTZL\Bulletin\Bootstrap( \JTZL\Bulletin\Plugin::get_container() );
	$bootstrap->register_hooks();
}
add_action( 'plugins_loaded', 'jtzl_bltn_boot', 20 );

/**
 * Install the schema on activation.
 *
 * Not the only path that installs it — Bootstrap checks the stored schema version on
 * every request, because a plugin can arrive at a new version without this hook ever
 * firing (an unzip over the directory, a file-copy deploy). This is the ordinary
 * route; that one is the guarantee.
 *
 * Resolved straight from the container rather than through Bootstrap: activation runs
 * before `plugins_loaded` on the activating request, so no hooks are registered yet
 * and none should be.
 *
 * @since 0.5.0
 */
function jtzl_bltn_activate() {
	$migrator = \JTZL\Bulletin\Plugin::get_container()->get( \JTZL\Bulletin\Database\Migrator::class );
	$migrator->upgrade();
}
register_activation_hook( __FILE__, 'jtzl_bltn_activate' );

/**
 * Admin notice shown when bbPress is not active.
 *
 * @since 0.1.0
 */
function jtzl_bltn_notice_missing_bbpress() {
	$message = __( '<strong>Bulletin for bbPress</strong> needs bbPress to be installed and active. It has no effect on its own.', 'jtzl-bulletin' );
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		wp_kses( $message, array( 'strong' => array() ) )
	);
}
