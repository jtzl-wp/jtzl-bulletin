<?php
/**
 * Theme takeover on the reading screens.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Takeover;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Swaps templates through `bbp_template_include`. Takeover screens use app.php;
 * reskin screens wrap bbPress markup with reskin.php. Both retain wp_head() and
 * wp_footer(); unowned screens remain untouched.
 *
 * @since 0.1.0
 */
class TemplateController {

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
	 * Absolute templates directory (trailing slash).
	 *
	 * @var string
	 */
	private string $templates_dir;

	public function __construct( ContextInterface $wp, ScreenClassifier $screen, string $templates_dir ) {
		$this->wp            = $wp;
		$this->screen        = $screen;
		$this->templates_dir = $templates_dir;
	}

	/**
	 * Send single-reply permalinks into the reading view.
	 *
	 * A raw /reply/{slug}/ URL isn't one of our three screens, so without this it
	 * would render in the site's theme. bbp_get_reply_url() resolves to the
	 * parent topic with the correct page and #post-N anchor, so there's no
	 * redirect loop, and our JS resolves the anchor even past page 1.
	 *
	 * @since 0.1.0
	 */
	public function redirect_single_reply(): void {
		if ( ! $this->wp->is_bbpress() || ! $this->wp->is_single_reply() ) {
			return;
		}
		$reply_id = $this->wp->get_reply_id();
		$url      = $reply_id > 0 ? $this->wp->get_reply_url( $reply_id ) : '';
		if ( '' !== $url ) {
			$this->wp->safe_redirect( $url, 301 );
			$this->wp->terminate();
		}
	}

	/**
	 * Prime the takeover before the template is chosen.
	 *
	 * Theme compatibility attaches at priority 4, before our priority-20
	 * override. Remove it on template_redirect, before the filter chain starts.
	 *
	 * Reskin screens retain theme compatibility because it supplies their content.
	 *
	 * @since 0.1.0
	 */
	public function prime_takeover(): void {
		if ( ScreenTier::Takeover !== $this->screen->tier() ) {
			return;
		}
		$this->wp->remove_filter( 'bbp_template_include', 'bbp_template_include_theme_compat', 4 );
	}

	/**
	 * Swap the template per tier: our own document on takeover screens, a chrome
	 * wrapper around bbPress's markup on reskin screens, untouched otherwise.
	 *
	 * @since 0.1.0
	 *
	 * @param string $template Template path WordPress/bbPress resolved.
	 * @return string
	 */
	public function filter_template_include( string $template ): string {
		return match ( $this->screen->tier() ) {
			ScreenTier::Takeover => $this->templates_dir . 'app.php',
			ScreenTier::Reskin   => $this->templates_dir . 'reskin.php',
			ScreenTier::None     => $template,
		};
	}
}
