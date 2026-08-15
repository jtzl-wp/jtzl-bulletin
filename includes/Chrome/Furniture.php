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
 * composer.
 *
 * ## Why this is a class rather than another method on Bootstrap
 *
 * `Bootstrap` is the composition root and its coupling is exempted on purpose, but
 * its **length** is not — and it had spent three PRs a handful of lines under PHPMD's
 * ceiling, close enough that the next hook was going to be paid for by deleting an
 * explanation somewhere. `Ajax\Endpoints` was the first group lifted out along that
 * seam in PR 5; this is the second, and `Chrome\Namings` the third.
 *
 * ⚠ **The chrome group split in two rather than moving whole**, because nine
 * constructor arguments is itself a size rule (`ExcessiveParameterList`), and taking
 * the container instead is not open to a `Chrome` class — deptrac grants this layer
 * `Screen`, `Unread` and `WordPress`, and `Vendor_DI` to `Root` alone. The seam the
 * two halves were split on is not invented for the limit: three of the filters said
 * "name" in their own comments and went to `Namings`; what is left here is the group
 * that changes or removes something bbPress already renders.
 *
 * Every binding keeps the comment that was written beside it in `Bootstrap`. The
 * reason a hook has a priority is the part that is expensive to rediscover, and it
 * travelled with the hook.
 *
 * @since 0.5.0
 */
class Furniture {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * The admin bar's visibility on a screen we own.
	 *
	 * @var AdminBar
	 */
	private AdminBar $admin_bar;

	/**
	 * WordPress's "Protected:" title prefix.
	 *
	 * @var ProtectedTitle
	 */
	private ProtectedTitle $protected_title;

	/**
	 * The doubled full stop on an edit record.
	 *
	 * @var RevisionLogStop
	 */
	private RevisionLogStop $revision_stop;

	/**
	 * The inline handler bbPress puts on the per-reply "Reply To" link.
	 *
	 * @var ReplyToLink
	 */
	private ReplyToLink $reply_to;

	/**
	 * The caret in a mistyped password field.
	 *
	 * @var PasswordForm
	 */
	private PasswordForm $password_form;

	/**
	 * The editor bbPress hands the composer.
	 *
	 * @var ComposerSettings
	 */
	private ComposerSettings $composer;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface $wp              WordPress/bbPress seam.
	 * @param AdminBar         $admin_bar       Admin-bar visibility.
	 * @param ProtectedTitle   $protected_title Protected-title prefix.
	 * @param RevisionLogStop  $revision_stop   Edit-record punctuation.
	 * @param ReplyToLink      $reply_to        Reply-to link handler.
	 * @param PasswordForm     $password_form   Password-field focus.
	 * @param ComposerSettings $composer        Editor settings.
	 */
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
