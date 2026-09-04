<?php
/**
 * Admin-bar visibility inside Bulletin's shell.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Keeps WordPress's admin bar off the screens Bulletin owns, for everyone who
 * cannot administrate the site.
 *
 * @since 0.3.0
 * @since 0.5.0 Only forum create/edit is left to the theme, so only it keeps the
 *              default bar for a non-administrator.
 */
class AdminBar {

	private const CAPABILITY = 'manage_options';

	private ContextInterface $wp;

	private ScreenClassifier $screen;

	public function __construct( ContextInterface $wp, ScreenClassifier $screen ) {
		$this->wp     = $wp;
		$this->screen = $screen;
	}

	/**
	 * Decide whether the admin bar shows for this request.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $show Whether WordPress (and any earlier filter) would show it.
	 * @return bool
	 */
	public function filter_show_admin_bar( $show ): bool {
		$showing = (bool) $show;
		if ( ScreenTier::None === $this->screen->tier() ) {
			return $showing;
		}
		return $showing && $this->wp->current_user_can( self::CAPABILITY );
	}
}
