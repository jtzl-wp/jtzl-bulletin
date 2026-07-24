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
 * Swaps the template on bbPress screens through bbPress's own `bbp_template_include`
 * filter (which sits on WordPress core's `template_include`): a takeover screen gets
 * our own minimal document (app.php), a reskin screen gets a chrome wrapper around
 * bbPress's own markup (reskin.php). Either way wp_head()/wp_footer() still fire, so
 * core plus other plugins keep working. Screens we don't own are returned untouched.
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

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp            WordPress/bbPress seam.
	 * @param ScreenClassifier $screen        Screen-tier classifier.
	 * @param string           $templates_dir Absolute templates directory (trailing slash).
	 */
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
	 * Because bbPress attaches its theme-compat wrapper to `bbp_template_include`
	 * at priority 4 — before our priority-20 override — removing it from inside
	 * our override would be too late. We strip it here on template_redirect, which
	 * runs before the template_include chain. (bbPress's own code sanctions this.)
	 *
	 * Only takeover screens strip theme-compat: they build their content from our
	 * own loops, so bbPress's content injection is redundant. Reskin screens rely
	 * on it — it is what buffers bbPress's own content-*.php part into the post
	 * that reskin.php then prints — so it must stay.
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
