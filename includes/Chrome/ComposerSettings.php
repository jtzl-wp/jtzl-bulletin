<?php
/**
 * The editor bbPress hands the composer, on the screens Bulletin owns.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Adjusts the plain textarea and WordPress quicktags editor used by bbPress.
 *
 * @since 0.5.0
 */
class ComposerSettings {

	private const TAKEOVER_ROWS = 6;

	private ScreenClassifier $screen;

	public function __construct( ScreenClassifier $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Drop the `img` button from the formatting strip.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $settings Quicktags settings, as bbPress assembled them.
	 * @return mixed
	 */
	public function filter_quicktags( $settings ) {
		if ( ScreenTier::None === $this->screen->tier() || ! is_array( $settings ) ) {
			return $settings;
		}

		$buttons = isset( $settings['buttons'] ) && is_string( $settings['buttons'] )
			? $settings['buttons']
			: '';
		if ( '' === $buttons ) {
			return $settings;
		}

		$kept = array_diff( explode( ',', $buttons ), array( 'img' ) );

		$settings['buttons'] = implode( ',', $kept );

		return $settings;
	}

	/**
	 * Shrink the composer's textarea on the takeover tier.
	 *
	 * Takeover only. The reskin tier's edit forms are a different job — there a reader
	 * is looking at a post they already wrote, so the field wants to show it.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $args Parsed `bbp_get_the_content()` arguments.
	 * @return mixed
	 */
	public function filter_content_args( $args ) {
		if ( ScreenTier::Takeover !== $this->screen->tier() || ! is_array( $args ) ) {
			return $args;
		}

		$args['textarea_rows'] = (string) self::TAKEOVER_ROWS;

		return $args;
	}
}
