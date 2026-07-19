<?php
/**
 * Asset loading and theme-style suppression.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\Asset;

use JTZL\Bulletin\Screen\ReadingScreen;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Enqueues Bulletin's own built CSS/JS on the reading screens, and — the
 * load-bearing half of the takeover — dequeues every other enqueued stylesheet
 * so the theme's cascade (Genesis, on JT's site) can't bleed into our minimal
 * document. The allowlist is filterable so the integration pass on the real
 * stack can add back any plugin handle that matters.
 */
class AssetManager {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Reading-screen detector.
	 *
	 * @var ReadingScreen
	 */
	private ReadingScreen $screen;

	/**
	 * Absolute plugin directory path (trailing slash).
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Plugin base URL (trailing slash).
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Asset version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Constructor.
	 *
	 * @param ContextInterface $wp         WordPress/bbPress seam.
	 * @param ReadingScreen    $screen     Reading-screen detector.
	 * @param string           $plugin_dir Absolute plugin directory (trailing slash).
	 * @param string           $plugin_url Plugin base URL (trailing slash).
	 * @param string           $version    Asset version.
	 */
	public function __construct(
		ContextInterface $wp,
		ReadingScreen $screen,
		string $plugin_dir,
		string $plugin_url,
		string $version
	) {
		$this->wp         = $wp;
		$this->screen     = $screen;
		$this->plugin_dir = $plugin_dir;
		$this->plugin_url = $plugin_url;
		$this->version    = $version;
	}

	/**
	 * Enqueue Bulletin's CSS/JS on the reading screens only.
	 */
	public function enqueue(): void {
		if ( ! $this->screen->is_reading_screen() ) {
			return;
		}

		$style = $this->style_url();
		if ( '' !== $style ) {
			$this->wp->enqueue_style( 'jtzl-bulletin', $style, array(), $this->version );
		}

		$script = $this->script_url();
		if ( '' !== $script ) {
			$this->wp->enqueue_script( 'jtzl-bulletin', $script, array(), $this->version, true );

			// No nonce: this endpoint serves only already-public reply content and
			// changes no state, so there's no CSRF surface — and a per-page nonce
			// would break under full-page caching (a cached page would ship an
			// already-expired nonce). Access is gated on forum visibility
			// server-side instead (see Ajax\LoadRepliesController).
			$this->wp->localize_script(
				'jtzl-bulletin',
				'BLTN',
				array(
					'ajaxUrl' => $this->wp->get_ajax_url(),
					'action'  => 'bulletin_load_replies',
					'i18n'    => array(
						'loadMore' => __( 'Load more replies', 'jtzl-bulletin' ),
						'loading'  => __( 'Loading…', 'jtzl-bulletin' ),
						'error'    => __( 'Could not load more. Tap to retry.', 'jtzl-bulletin' ),
					),
				)
			);
		}
	}

	/**
	 * Dequeue the active theme's (and other plugins') stylesheets on our screens.
	 *
	 * Runs late so it sees everything the theme and plugins enqueued.
	 */
	public function suppress_foreign_styles(): void {
		if ( ! $this->screen->is_reading_screen() ) {
			return;
		}

		/**
		 * Style handles allowed to survive the takeover.
		 *
		 * Defaults: our own CSS, plus the admin bar and its icon font (so
		 * logged-in staff still get a working admin bar inside our document).
		 *
		 * @param string[] $allow Allowed style handles.
		 */
		$allow = $this->wp->apply_filters(
			'bltn_style_allowlist',
			array( 'jtzl-bulletin', 'admin-bar', 'dashicons' )
		);
		if ( ! is_array( $allow ) ) {
			$allow = array();
		}

		foreach ( $this->wp->get_enqueued_style_handles() as $handle ) {
			if ( ! in_array( $handle, $allow, true ) ) {
				$this->wp->dequeue_style( $handle );
			}
		}
	}

	/**
	 * Resolved URL of the hashed reading-view script, or '' if unbuilt.
	 *
	 * @return string
	 */
	private function script_url(): string {
		$manifest = $this->read_manifest();
		$file     = $manifest['jtzl-bltn-reading.js'] ?? '';
		return '' !== $file ? $this->plugin_url . 'build/' . $file : '';
	}

	/**
	 * Resolved URL of the built stylesheet (hashed if present), or '' if unbuilt.
	 *
	 * @return string
	 */
	private function style_url(): string {
		$hashed = glob( $this->plugin_dir . 'build/jtzl-bltn.*.css' );
		if ( is_array( $hashed ) && array() !== $hashed ) {
			return $this->plugin_url . 'build/' . basename( $hashed[0] );
		}
		$plain = $this->plugin_dir . 'build/jtzl-bltn.css';
		return file_exists( $plain ) ? $this->plugin_url . 'build/jtzl-bltn.css' : '';
	}

	/**
	 * Read build/asset-manifest.json as a name => hashed-filename map.
	 *
	 * @return array<string,string>
	 */
	private function read_manifest(): array {
		$path = $this->plugin_dir . 'build/asset-manifest.json';
		if ( ! file_exists( $path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local build artifact, not a remote request.
		$json = file_get_contents( $path );

		// A read failure ((string) false === '') or malformed JSON both decode to a
		// non-array, so one guard covers both.
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		$map = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) ) {
				$map[ $key ] = $value;
			}
		}
		return $map;
	}
}
