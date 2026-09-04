<?php
/**
 * Takeover script suppression.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Asset;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Remove scripts whose markup is absent from Bulletin's takeover document.
 *
 * The policy is deliberately conservative: remove active-theme scripts and bbPress
 * behavior this tier's markup cannot use, while preserving unknown plugin scripts
 * because wp_head() and wp_footer() remain compatibility boundaries. Signed-in
 * forum/topic readers keep engagements for the Subscribe control — and, since the
 * composer landed, the editor script that rides on the same jQuery.
 *
 * @since 0.5.0
 */
class TakeoverScriptSuppressor {

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

	public function __construct( ContextInterface $wp, ScreenClassifier $screen ) {
		$this->wp     = $wp;
		$this->screen = $screen;
	}

	/**
	 * Suppress dead scripts on takeover screens.
	 *
	 * Leave jQuery to WordPress's dependency resolver so surviving integrations keep it.
	 *
	 * @since 0.5.0
	 */
	public function suppress(): void {
		if ( ScreenTier::Takeover !== $this->screen->tier() ) {
			return;
		}

		/*
		 * `bbpress-reply` moves the form beside a reply, while Bulletin keeps it at the
		 * thread foot. Engagements and editor scripts survive only where their controls
		 * need them; sites can override suppression through `bltn_script_allowlist`.
		 */
		$dead = array( 'bbpress-reply' );
		if ( ! $this->needs_engagements() ) {
			$dead[] = 'bbpress-engagements';
			$dead[] = 'bbpress-editor';
		}

		$allow    = $this->allowlist();
		$prefixes = $this->theme_url_prefixes();
		foreach ( $this->wp->get_enqueued_script_handles() as $handle ) {
			if ( in_array( $handle, $allow, true ) ) {
				continue;
			}
			if ( in_array( $handle, $dead, true ) || $this->is_theme_script( $handle, $prefixes ) ) {
				$this->wp->dequeue_script( $handle );
			}
		}
	}

	/**
	 * Suppress GeneratePress's source-less navigation script on takeovers.
	 *
	 * GeneratePress prints this directly from wp_footer(), outside WP_Scripts, so
	 * the queue suppression above cannot see it. Bulletin renders no theme menu.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $print Whether GeneratePress should print the script.
	 * @return bool
	 */
	public function filter_generatepress_a11y( $print ): bool {
		return ScreenTier::Takeover === $this->screen->tier() ? false : (bool) $print;
	}

	/**
	 * Script handles that always survive takeover suppression.
	 *
	 * @since 0.5.0
	 *
	 * @return string[]
	 */
	private function allowlist(): array {
		/**
		 * Script handles that always survive Bulletin's takeover suppression.
		 *
		 * @param string[] $allow Allowed script handles.
		 */
		$allow = $this->wp->apply_filters(
			'bltn_script_allowlist',
			array( 'jtzl-bulletin', 'admin-bar' )
		);
		return is_array( $allow ) ? $allow : array();
	}

	/**
	 * Whether the takeover renders a control wired by bbPress engagements.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	private function needs_engagements(): bool {
		if ( ! $this->wp->is_user_logged_in() || ! $this->wp->is_subscriptions_active() ) {
			return false;
		}
		if ( $this->screen->is_single_forum() ) {
			return ! $this->wp->is_password_required( $this->wp->get_forum_id() );
		}
		if ( $this->screen->is_single_topic() ) {
			return ! $this->wp->is_password_required( $this->wp->get_topic_id() );
		}
		return false;
	}

	/**
	 * Normalised path prefixes of the active parent and child theme directories.
	 *
	 * @since 0.5.0
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
	 * Whether an enqueued script belongs to the active theme.
	 *
	 * @since 0.5.0
	 *
	 * @param string   $handle   Script handle.
	 * @param string[] $prefixes Normalised theme directory prefixes.
	 * @return bool
	 */
	private function is_theme_script( string $handle, array $prefixes ): bool {
		$src = $this->normalize_url( $this->wp->get_script_src( $handle ) );
		if ( '' === $src ) {
			return false;
		}

		foreach ( $prefixes as $prefix ) {
			if ( str_starts_with( $src, $prefix . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Reduce a URL to its path for source-prefix comparison.
	 *
	 * @since 0.5.0
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
}
