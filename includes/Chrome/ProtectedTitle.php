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
 * Stops WordPress's `Protected: %s` prefix becoming part of a forum's name.
 *
 * `get_the_title()` prepends it whenever a post carries a password — and Bulletin
 * renders `the_title`-filtered forum titles (`bbp_get_forum_title()`), so on a site
 * with one password-protected forum the string arrived as UI copy in the document
 * title, the app bar, the forum heading, a sub-forum row, the reading view's forum
 * kicker, and mid-sentence on every reskin card ("in: Protected: Espresso
 * Machines"). Measured on the fixture: 48 instances of that last one across three
 * archive routes.
 *
 * Two things make it worth removing rather than living with:
 *
 * 1. **It is permanently wrong for the people who have access.** Core keys the
 *    prefix on `post_password` being *set*, not on whether the visitor has
 *    *supplied* it — verified by unlocking the fixture forum and re-probing, where
 *    every screen beneath it still read "Protected:". So the members for whom the
 *    forum is simply a forum are the ones told otherwise, forever.
 * 2. **It is admin vocabulary on a reading screen.** The prefix exists for an
 *    editor scanning a post list. This design system already states a post's
 *    status the way it wants to — one word in a meta line that exists, which is
 *    what `Closed` does (see DESIGN.md, "Closed is a fact, not a demotion"). A
 *    prefix welded to the name says it in the one place that cannot be styled,
 *    truncated, or translated as a status.
 *
 * The protection itself is untouched: this changes a label, never a capability.
 * WordPress still gates the content, `post_password_required()` still answers the
 * same, and the in-shell password form (issue #18) still renders — the screen just
 * names the forum instead of narrating its configuration.
 *
 * Scoped to `ScreenTier::None` passing through, on the same principle as
 * Chrome\AdminBar: off our screens — every non-bbPress page, and the edit forms the
 * posting phase excludes, which is exactly where an editor *wants* the admin
 * vocabulary — WordPress's default is left alone.
 *
 * @since 0.3.0
 */
class ProtectedTitle {

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
	 * Drop the prefix on screens Bulletin owns.
	 *
	 * Returns the bare `%s` placeholder rather than an empty string: this is a
	 * `sprintf()` format that core applies to the title, so emptying it would erase
	 * the title along with the prefix.
	 *
	 * The incoming value is untyped for the reason Chrome\AdminBar gives — a filter
	 * chain guarantees nothing about what an earlier callback returned, and a
	 * `string` parameter would turn somebody else's missing `return` into a fatal on
	 * every forum page. A non-string is replaced rather than passed on, since the
	 * only thing core can do with one is fail.
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
