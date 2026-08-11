<?php
/**
 * Uninstall routine.
 *
 * Bulletin was a stateless reading layer until unread arrived (issue #102), and this
 * file said so. It now owns exactly two persistent things, and removes both:
 *
 * - `{prefix}jtzl_bltn_topic_reads` — which member has read which thread.
 * - `jtzl_bltn_db_version` — the schema version that table was built at.
 *
 * Still not removed, and still deliberately: the short-lived thread-order caches
 * (transients keyed `bltn_nav_*`), which expire on their own within the hour. Finding
 * them means an unbounded LIKE scan of the options table to delete rows the database
 * is about to drop anyway.
 *
 * Single-site only. Multisite is an explicit exclusion in the SoW, so a network
 * uninstall is not walked here — doing it half-correctly (this site's tables, nobody
 * else's) would be worse than not claiming the support at all.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

// Exit if not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// The table name is repeated rather than read from Database\Schema: uninstall.php is
// loaded by WordPress on its own, with no container and no guarantee the autoloader
// has run, so reaching for a class here is how an uninstall routine fails silently on
// the one run it gets. A repeated literal drifts, so tests/Integration/UninstallTest.php
// asserts this one still names the table Database\Schema creates.
$jtzl_bltn_table = $wpdb->prefix . 'jtzl_bltn_topic_reads';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$jtzl_bltn_table}`" );

delete_option( 'jtzl_bltn_db_version' );
