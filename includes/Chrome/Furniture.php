<?php
/**
 * What the shell paints around a screen, and what it takes off one.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Binds the corrections and suppressions that apply to the furniture rather than to
 * the content: the admin bar, a title's prefix, an edit record's punctuation, a
 * link's inline handler, a password field's caret, and the editor bbPress hands the
 *
 * @since 0.5.0
 */
class Furniture {

	private ContextInterface $wp;

	private AdminBar $admin_bar;

	private ProtectedTitle $protected_title;

	private RevisionLogStop $revision_stop;

	private ReplyToLink $reply_to;

	private PasswordForm $password_form;

	private ComposerSettings $composer;

	public function __construct(
		ContextInterface $wp,
		AdminBar $admin_bar,
		ProtectedTitle $protected_title,
		RevisionLogStop $revision_stop,
		ReplyToLink $reply_to,
		PasswordForm $password_form,
		ComposerSettings $composer
	) {
		$this->wp              = $wp;
		$this->admin_bar       = $admin_bar;
		$this->protected_title = $protected_title;
		$this->revision_stop   = $revision_stop;
		$this->reply_to        = $reply_to;
		$this->password_form   = $password_form;
		$this->composer        = $composer;
	}

	/**
	 * Bind every piece of furniture.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		$this->wp->add_filter( 'show_admin_bar', array( $this->admin_bar, 'filter_show_admin_bar' ), 100 );

		// And keep WordPress's "Protected:" prefix out of a forum's name on those
		// same screens. Late for the same reason: ours is the last word on the shell
		// we render, and off it (ScreenTier::None) the default passes through.
		$this->wp->add_filter( 'protected_title_format', array( $this->protected_title, 'filter_protected_title_format' ), 100 );

		// And take the second full stop off an edit record whose author's display
		// name already ends in one — "by Mara K..". Every tier, because it is a
		// correction rather than a restyle (Chrome\RevisionLogStop).
		$this->wp->add_filter( 'bbp_get_reply_revision_log', array( $this->revision_stop, 'filter_revision_log' ), 20 );
		$this->wp->add_filter( 'bbp_get_topic_revision_log', array( $this->revision_stop, 'filter_revision_log' ), 20 );

		// And take bbPress's inline handler off the per-reply "Reply To" link on the
		// takeover tier, where the script that would answer it is suppressed and the
		// href is the whole mechanism (see Chrome\ReplyToLink).
		$this->wp->add_filter( 'bbp_get_reply_to_link', array( $this->reply_to, 'filter_reply_to_link' ), 100 );

		// And put the caret back in the password field when the reader has just
		// mistyped it — the wrong-password state is a full page load, so focus is on
		// <body> and the field has to be found again (see Chrome\PasswordForm). At 20,
		// after View\ProtectedRowContent's withholding at 10 on the same filter: what
		// that returns for a loop row is the empty string, which has no field to focus.
		$this->wp->add_filter( 'the_password_form', array( $this->password_form, 'filter_password_form' ), 20 );

		// And trim the editor bbPress hands the composer: one button off the formatting
		// strip, and a textarea that does not spend two-thirds of a phone screen being
		// empty (see Chrome\ComposerSettings).
		$this->wp->add_filter( 'bbp_get_quicktags_settings', array( $this->composer, 'filter_quicktags' ) );
		$this->wp->add_filter( 'bbp_after_get_the_content_parse_args', array( $this->composer, 'filter_content_args' ) );
	}
}
