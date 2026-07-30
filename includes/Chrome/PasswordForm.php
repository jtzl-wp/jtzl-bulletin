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
 * Puts the caret back in the password field when the reader has just mistyped it.
 *
 * The wrong-password state is a full page load: the form posts to
 * `wp-login.php?action=postpass`, which sets the cookie and redirects back, and
 * WordPress then compares the hash and re-renders the prompt. So focus is on
 * `<body>` — the reader has to find and tap the field a second time to correct a
 * typo, on the one screen in the product where something has gone wrong.
 *
 * `autofocus` is what fixes it, and it is better here than a scripted `focus()`
 * for a reason specific to this state: WordPress 6.8+ already points the field's
 * `aria-describedby` at the error, so landing in the field announces the field
 * *and* "Invalid password." together. A live region alone announces the message
 * with the caret still nowhere.
 *
 * ## What core already does, and what it doesn't
 *
 * Core supplies the semantics in full — `<div class="post-password-form-invalid-
 * password" role="alert">`, the `aria-describedby`, and a `password-form-error`
 * class on the form. Nothing needs adding there, and a second live region would
 * only announce the same string twice.
 *
 * What core leaves entirely to the stylesheet is telling the error *apart*: it is
 * a bare `<p>`, and Bulletin's `.bltn .post-password-form p` rule styled it
 * identically to the instruction sentence beneath it — 13.5px, weight 400,
 * `--bltn-ink-2` — so a reader who mistyped saw one more grey line and no other
 * change. That half is fixed in the stylesheet; this class handles the caret.
 *
 * ## Where it applies, and one place it cannot
 *
 * Only on the two prompts Bulletin renders on purpose: the in-shell screen for a
 * protected forum or topic (issue #18). Off our screens (`ScreenTier::None`) the
 * form is left alone, and the empty string that View\ProtectedRowContent returns
 * for a loop row's description slot is passed straight back — a row has no prompt
 * to focus.
 *
 * ⚠ A protected **reply** mid-thread (issue #54) never reaches the error state at
 * all, and that is core's condition, not ours: `get_the_password_form()` shows the
 * message only when `wp_get_raw_referer() === get_permalink( $post->ID )`, and a
 * reply's form redirects to the *thread*. So a reader who mistypes a reply's
 * password gets the prompt back with no message. Fixing that means answering for
 * core's redirect target, which is a larger promise than naming a field.
 *
 * ## Why the autofocus cannot move the viewport
 *
 * Issue #66 names `autofocus` as one of the things that can scroll the root behind
 * our fixed shell, so this is worth stating rather than leaving to be rediscovered.
 * It cannot here, and for two independent reasons: the screens this fires on hold a
 * heading and a short form, so the root measures **0 overflow** on both (the reading
 * views are the only routes that overflow, at 1307–1544px); and the one long-content
 * case — a protected reply inside a thread — cannot reach the error class at all, per
 * the note above. If either of those changes, this becomes reachable.
 *
 * @since 0.3.0
 */
class PasswordForm {

	/**
	 * Screen-tier classifier.
	 *
	 * @var ScreenClassifier
	 */
	private ScreenClassifier $screen;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ScreenClassifier $screen Screen-tier classifier.
	 */
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
	 * Anchored on the `type` attribute rather than on the field's id, which core
	 * builds from the post ID, or on its name, which a filter may have changed. And
	 * tolerant of quoting and spacing around it, because what arrives here is not
	 * necessarily core's string — `the_password_form` is a shared filter, so a plugin
	 * ahead of us may have rewritten the markup — and a regex that missed for that
	 * reason would look exactly like one that had nothing to do (raised by Qodo).
	 *
	 * ## The "already focused" test is the lookahead, and it is scoped to the tag
	 *
	 * It was `str_contains( $form, 'autofocus' )`, which asks the wrong question: an
	 * `autofocus` on *any* element — a submit button, a honeypot field another plugin
	 * added — declined the rewrite and left the field unfocused, in exactly the shape
	 * of failure this class exists to remove (raised by Gitar). The lookahead below
	 * only sees inside the input tag it is about to rewrite, because `[^>]*` cannot
	 * cross a `>`.
	 *
	 * Position-independent inside that tag, which is why it is a lookahead rather than
	 * a second pattern appended after the `type` attribute: `<input autofocus … type=
	 * "password">` would otherwise be handed a duplicate attribute. And spelt
	 * `\sautofocus[\s=>\/]` rather than `\bautofocus\b` so that `class="js-autofocus"`
	 * or `data-autofocus` is not mistaken for the attribute — the residual case,
	 * `foo="autofocus"` on the field itself, would cost a duplicate attribute that
	 * browsers ignore rather than a reader losing the caret.
	 *
	 * One pattern for the field, shared by the lookahead and the match, so the two
	 * cannot drift into disagreeing about what a password input looks like.
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

		// A form we could not rewrite is returned as it came. `preg_replace()` answers
		// a failed match with the subject and a failed *run* with null, and the second
		// one must not reach the reader as a screen with no way in.
		return is_string( $focused ) ? $focused : $form;
	}
}
