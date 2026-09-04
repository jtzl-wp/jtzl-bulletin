<?php
/**
 * What a password-protected post contributes to a loop row.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

/**
 * Withholds password forms only while bbPress renders a loop-row content slot.
 * The narrow action bracket preserves forms on protected screens and replies.
 */
class ProtectedRowContent {

	private bool $in_row_slot = false;

	public function open_row_slot(): void {
		$this->in_row_slot = true;
	}

	public function close_row_slot(): void {
		$this->in_row_slot = false;
	}

	/**
	 * Answer WordPress's password form with nothing when it is standing in for a
	 * loop row's description.
	 *
	 * @since 0.3.0
	 *
	 * @param string $form The password form markup WordPress generated.
	 * @return string
	 */
	public function filter_password_form( string $form ): string {
		return $this->in_row_slot ? '' : $form;
	}
}
