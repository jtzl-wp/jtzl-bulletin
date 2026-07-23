<?php
/**
 * Plugin Name:       Bulletin for bbPress
 * Plugin URI:        https://github.com/jtzl-wp/jtzl-bulletin
 * Description:       A mobile-first, decluttered reading layer for bbPress. The post is the hero; navigation is deliberately secondary. Renders its own minimal document on the reading screens and leaves every other page on the site's own theme.
 * Version:           0.2.0
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
 * Bulletin is a companion plugin — it never edits bbPress core. On the three
 * reading screens (forums index, a single forum, a single topic) it takes over
 * the `bbp_template_include` filter and renders its own minimal document, while
 * still firing wp_head()/wp_footer() so WordPress core and other plugins keep
 * working. Non-forum pages — and, in v1, bbPress pages we have no design for —
 * are left untouched on the active theme.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JTZL_BLTN_VERSION', '0.2.0' );
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
