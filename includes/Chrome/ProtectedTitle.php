<?php
/**
 * WordPress's "Protected:" title prefix, inside Bulletin's shell.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Owns protected-title formatting on Bulletin screens.
 *
 * Filters are scoped to rendering because bbPress hooks can run during output;
 * cleanup in `finally` prevents title filters leaking into the rest of the request.
 */
class ProtectedTitle {

	private ScreenClassifier $screen;

	public function __construct( ScreenClassifier $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Drop the prefix on screens Bulletin owns.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $format The `sprintf()` format core will apply to the title.
	 * @return string
	 */
	public function filter_protected_title_format( $format ): string {
		if ( ScreenTier::None === $this->screen->tier() ) {
			return is_string( $format ) ? $format : '%s';
		}
		return '%s';
	}
}
