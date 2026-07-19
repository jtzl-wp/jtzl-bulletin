<?php
/**
 * Theme takeover on the reading screens.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\Takeover;

use JTZL\Bulletin\Screen\ReadingScreen;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * On the three reading screens we render our own minimal document instead of the
 * active theme, through bbPress's own `bbp_template_include` filter (which sits
 * on WordPress core's `template_include`) — so wp_head()/wp_footer() still fire
 * and core plus other plugins keep working.
 */
class TemplateController {

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
	 * Absolute templates directory (trailing slash).
	 *
	 * @var string
	 */
	private string $templates_dir;

	/**
	 * Constructor.
	 *
	 * @param ContextInterface $wp            WordPress/bbPress seam.
	 * @param ReadingScreen    $screen        Reading-screen detector.
	 * @param string           $templates_dir Absolute templates directory (trailing slash).
	 */
	public function __construct( ContextInterface $wp, ReadingScreen $screen, string $templates_dir ) {
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
	 */
	public function prime_takeover(): void {
		if ( ! $this->screen->is_reading_screen() ) {
			return;
		}
		$this->wp->remove_filter( 'bbp_template_include', 'bbp_template_include_theme_compat', 4 );
	}

	/**
	 * Swap in our own document on the reading screens.
	 *
	 * @param string $template Template path WordPress/bbPress resolved.
	 * @return string
	 */
	public function filter_template_include( string $template ): string {
		if ( ! $this->screen->is_reading_screen() ) {
			return $template;
		}
		return $this->templates_dir . 'app.php';
	}
}
