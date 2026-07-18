<?php
/**
 * Asset loading and theme-style suppression.
 *
 * @package Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue Bulletin's own CSS/JS on the reading screens only.
 */
function bltn_enqueue_assets() {
	if ( ! bltn_is_reading_screen() ) {
		return;
	}

	wp_enqueue_style( 'bulletin', BLTN_URL . 'assets/bulletin.css', array(), BLTN_VERSION );
	wp_enqueue_script( 'bulletin', BLTN_URL . 'assets/bulletin.js', array(), BLTN_VERSION, true );

	// No nonce: this endpoint serves only already-public reply content and
	// changes no state, so there's no CSRF surface — and a per-page nonce would
	// break under full-page caching on JT's host (a cached page would ship a
	// nonce that's already expired for the next visitor). Access is gated on
	// forum visibility server-side instead (see includes/ajax.php).
	wp_localize_script(
		'bulletin',
		'BLTN',
		array(
			'ajaxUrl' => bbp_get_ajax_url(),
			'action'  => 'bulletin_load_replies',
			'i18n'    => array(
				'loadMore' => __( 'Load more replies', 'bulletin' ),
				'loading'  => __( 'Loading…', 'bulletin' ),
				'error'    => __( 'Could not load more. Tap to retry.', 'bulletin' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'bltn_enqueue_assets' );

/**
 * Suppress the active theme's (and other plugins') stylesheets on our screens.
 *
 * This is the load-bearing half of the takeover. wp_head() prints every enqueued
 * stylesheet, so without this our minimal document would inherit the whole theme
 * cascade — Genesis on JT's site — which is the opposite of what we're building.
 * We dequeue every style except an allowlist. The allowlist is filterable so the
 * integration pass on JT's real stack can add back any plugin handle that turns
 * out to matter, without touching this file.
 *
 * Runs late (priority 100) so it sees everything the theme and plugins enqueued.
 */
function bltn_suppress_foreign_styles() {
	if ( ! bltn_is_reading_screen() ) {
		return;
	}

	/**
	 * Style handles allowed to survive the takeover.
	 *
	 * Defaults: our own CSS, plus the admin bar and its icon font (so logged-in
	 * staff still get a working admin bar inside our document).
	 *
	 * @param string[] $allow Allowed style handles.
	 */
	$allow = apply_filters(
		'bltn_style_allowlist',
		array( 'bulletin', 'admin-bar', 'dashicons' )
	);

	$styles = wp_styles();

	// Snapshot the queue: wp_dequeue_style() mutates it as we go.
	foreach ( (array) $styles->queue as $handle ) {
		if ( ! in_array( $handle, $allow, true ) ) {
			wp_dequeue_style( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'bltn_suppress_foreign_styles', 100 );
