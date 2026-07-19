<?php
/**
 * Uninstall routine.
 *
 * Bulletin is a stateless reading layer: it stores no options, custom tables, or
 * user meta. The only thing it ever writes is short-lived thread-order caches
 * (transients keyed `bltn_nav_*`), which expire on their own within the hour, so
 * there is deliberately nothing to clean up here — removing them would mean an
 * unbounded LIKE scan of the options table for no lasting benefit.
 *
 * @package JTZL\Bulletin
 */

// Exit if not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Intentionally empty: nothing persistent to remove.
