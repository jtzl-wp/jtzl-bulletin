<?php
/**
 * Removes Bulletin's read-state table and schema-version option.
 *
 * Navigation transients expire naturally; deleting them requires an unbounded
 * options scan. Multisite cleanup is outside the plugin's supported scope.
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
// asserts this statement still names the table Database\Schema creates.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- an uninstall drops its own table.
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}jtzl_bltn_topic_reads`" );

delete_option( 'jtzl_bltn_db_version' );
