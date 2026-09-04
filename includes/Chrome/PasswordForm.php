<?php
/**
 * WordPress's password form, after a wrong password.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Autofocuses a rejected password on Bulletin screens so core's described error
 * is announced with the field. Protected replies cannot enter core's error state.
 */
class PasswordForm {

	private ScreenClassifier $screen;

	public function __construct( ScreenClassifier $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Focus the field when the form has come back carrying an error.
	 *
	 * @since 0.3.0
	 *
	 * @param string $form The password form markup WordPress generated.
	 * @return string
	 */
	public function filter_password_form( string $form ): string {
		if ( '' === $form || ScreenTier::None === $this->screen->tier() ) {
			return $form;
		}

		// The class core adds only in the wrong-password state. A first visit is not
		// a correction, and stealing the caret there would raise the keyboard over a
		// screen the reader may only be reading the name of.
		if ( ! str_contains( $form, 'password-form-error' ) ) {
			return $form;
		}

		return $this->autofocus( $form );
	}

	/**
	 * Add `autofocus` to the password input, once.
	 *
	 * @since 0.3.0
	 *
	 * @param string $form The password form markup.
	 * @return string
	 */
	private function autofocus( string $form ): string {
		$focused = preg_replace(
			'/<input\b(?![^>]*\sautofocus[\s=>\/])(?=[^>]*\btype\s*=\s*(["\']?)password\1)/i',
			'<input autofocus',
			$form,
			1
		);

		// Preserve the original form if the regular expression fails.
		return is_string( $focused ) ? $focused : $form;
	}
}
