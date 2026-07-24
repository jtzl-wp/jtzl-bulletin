<?php
/**
 * Asset loading and theme-style suppression.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Asset;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Enqueues Bulletin's own built CSS/JS on the screens we own, and — the
 * load-bearing half of the takeover — suppresses the styles that would otherwise
 * bleed into our chrome. The suppression differs by tier:
 *
 * - Takeover renders our own document, so every non-allowlisted stylesheet is
 *   dequeued (the theme's cascade AND bbPress's own CSS — neither is wanted).
 * - Reskin renders bbPress's own markup, which needs bbPress's CSS to stay
 *   legible, so only the active theme's stylesheets are suppressed; bbPress's
 *   bbp-default and ours survive.
 *
 * The `bltn_style_allowlist` filter names handles that always survive suppression
 * (either tier), so the integration pass on the real stack can protect a plugin
 * handle that matters.
 *
 * @since 0.1.0
 */
class AssetManager {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Screen-tier classifier.
	 *
	 * @var ScreenClassifier
	 */
	private ScreenClassifier $screen;

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
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp         WordPress/bbPress seam.
	 * @param ScreenClassifier $screen     Screen-tier classifier.
	 * @param string           $plugin_dir Absolute plugin directory (trailing slash).
	 * @param string           $plugin_url Plugin base URL (trailing slash).
	 * @param string           $version    Asset version.
	 */
	public function __construct(
		ContextInterface $wp,
		ScreenClassifier $screen,
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
	 * Enqueue Bulletin's CSS/JS on the screens we own.
	 *
	 * Our stylesheet loads on both takeover and reskin screens — it styles the
	 * chrome (app bar) common to both. The reading-view script, though, only drives
	 * the inline load-more controls that the takeover screens render; a reskin
	 * screen shows bbPress's own markup, which has nothing for it to bind, so the
	 * script and its localisation are scoped to takeover.
	 *
	 * @since 0.1.0
	 */
	public function enqueue(): void {
		$tier = $this->screen->tier();
		if ( ScreenTier::None === $tier ) {
			return;
		}

		$style = $this->style_url();
		if ( '' !== $style ) {
			$this->wp->enqueue_style( 'jtzl-bulletin', $style, array(), $this->version );
		}

		if ( ScreenTier::Takeover !== $tier ) {
			return;
		}

		$script = $this->script_url();
		if ( '' !== $script ) {
			$this->wp->enqueue_script( 'jtzl-bulletin', $script, array(), $this->version, true );

			// No nonce: these endpoints serve only already-public forum content
			// and change no state, so there's no CSRF surface — and a per-page
			// nonce would break under full-page caching (a cached page would ship
			// an already-expired nonce). Access is gated on forum visibility
			// server-side instead (see the Ajax controllers).
			//
			// Only the two transient labels are localised here. Which endpoint to
			// call, and the idle label naming what it loads, belong to the control
			// the screen rendered (see View\LoadMore).
			$this->wp->localize_script(
				'jtzl-bulletin',
				'BLTN',
				array(
					'ajaxUrl' => $this->wp->get_ajax_url(),
					'i18n'    => array(
						'loading' => __( 'Loading…', 'jtzl-bulletin' ),
						'error'   => __( 'Could not load more. Tap to retry.', 'jtzl-bulletin' ),
					),
				)
			);
		}
	}

	/**
	 * Suppress the styles that would bleed into our chrome, per tier.
	 *
	 * Runs late so it sees everything the theme and plugins enqueued. Takeover
	 * dequeues every non-allowlisted stylesheet (we render our own document);
	 * reskin dequeues only the active theme's, keeping bbPress's own CSS so its
	 * markup stays legible.
	 *
	 * @since 0.1.0
	 */
	public function suppress_foreign_styles(): void {
		$tier = $this->screen->tier();
		if ( ScreenTier::None === $tier ) {
			return;
		}

		if ( ScreenTier::Takeover === $tier ) {
			$this->suppress_for_takeover();
			return;
		}

		$this->suppress_for_reskin();
	}

	/**
	 * Takeover suppression: dequeue every stylesheet not on the allowlist, since
	 * our own document needs neither the theme's cascade nor bbPress's CSS.
	 *
	 * @since 0.3.0
	 */
	private function suppress_for_takeover(): void {
		$allow = $this->allowlist();
		foreach ( $this->wp->get_enqueued_style_handles() as $handle ) {
			if ( ! in_array( $handle, $allow, true ) ) {
				$this->wp->dequeue_style( $handle );
			}
		}
	}

	/**
	 * Reskin suppression: dequeue only the active theme's stylesheets, so
	 * bbPress's own bbp-default CSS (and ours) survive to style the markup it
	 * renders inside our chrome.
	 *
	 * A stylesheet is the theme's when its source path sits under the parent or
	 * child theme directory; block themes additionally print their theme.json
	 * styling inline under the `global-styles` handle, which has no URL to test,
	 * so it is named explicitly. Allowlisted handles always survive.
	 *
	 * Residual gap: a CDN or URL-rewrite plugin that rewrites theme assets onto a
	 * different *path* (not merely a different host) moves them out from under the
	 * theme directory, so the path test misses and that theme stylesheet survives —
	 * a real-host condition wp-env can't reproduce, to check during integration.
	 *
	 * @since 0.3.0
	 */
	private function suppress_for_reskin(): void {
		$allow    = $this->allowlist();
		$prefixes = $this->theme_url_prefixes();

		foreach ( $this->wp->get_enqueued_style_handles() as $handle ) {
			if ( in_array( $handle, $allow, true ) ) {
				continue;
			}
			if ( $this->is_theme_style( $handle, $prefixes ) ) {
				$this->wp->dequeue_style( $handle );
			}
		}
	}

	/**
	 * Handles that always survive Bulletin's style suppression, on either tier.
	 *
	 * Defaults: our own CSS, plus the admin bar and its icon font (so logged-in
	 * staff keep a working admin bar inside our chrome).
	 *
	 * @since 0.3.0
	 *
	 * @return string[]
	 */
	private function allowlist(): array {
		/**
		 * Style handles that always survive Bulletin's suppression.
		 *
		 * @param string[] $allow Allowed style handles.
		 */
		$allow = $this->wp->apply_filters(
			'bltn_style_allowlist',
			array( 'jtzl-bulletin', 'admin-bar', 'dashicons' )
		);
		return is_array( $allow ) ? $allow : array();
	}

	/**
	 * Normalised (host + path) URL prefixes of the active theme's directories.
	 *
	 * @since 0.3.0
	 *
	 * @return string[]
	 */
	private function theme_url_prefixes(): array {
		$uris = array(
			$this->wp->get_template_directory_uri(),
			$this->wp->get_stylesheet_directory_uri(),
		);

		$prefixes = array();
		foreach ( $uris as $uri ) {
			$normalized = $this->normalize_url( $uri );
			if ( '' !== $normalized ) {
				$prefixes[] = $normalized;
			}
		}
		return array_values( array_unique( $prefixes ) );
	}

	/**
	 * Whether an enqueued stylesheet belongs to the active theme.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $handle   Style handle.
	 * @param string[] $prefixes Normalised theme directory prefixes.
	 * @return bool
	 */
	private function is_theme_style( string $handle, array $prefixes ): bool {
		// Block themes emit theme.json styling inline under this handle with no
		// src, so the URL test below can't catch it; it is the one theme-originated
		// style we name outright.
		if ( 'global-styles' === $handle ) {
			return true;
		}

		$src = $this->normalize_url( $this->wp->get_style_src( $handle ) );
		if ( '' === $src ) {
			return false;
		}

		foreach ( $prefixes as $prefix ) {
			// The trailing slash keeps the match on a path boundary, so a sibling
			// directory (e.g. "…/themes/twentytwentyfive-child/") isn't mistaken
			// for the theme's own "…/themes/twentytwentyfive/".
			if ( str_starts_with( $src, $prefix . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Reduce a URL to its path alone for prefix comparison. wp_parse_url() extracts
	 * the path from an absolute, protocol-relative, or host-relative URL and drops
	 * any query or fragment — so scheme (http/https), host (same-origin, a CDN, or
	 * none at all), and a trailing `?ver=` can none of them defeat the test. A
	 * theme asset is thus matched by where it lives under the theme directory,
	 * whatever host serves it.
	 *
	 * @since 0.3.0
	 *
	 * @param string $url URL to normalise.
	 * @return string
	 */
	private function normalize_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) ? $path : '';
	}

	/**
	 * Resolved URL of the hashed reading-view script, or '' if unbuilt.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
