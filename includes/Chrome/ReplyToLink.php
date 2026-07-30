<?php
/**
 * The per-reply "Reply" link, in a release with no composer.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Withholds the per-reply "Reply" link, because in this release it cannot work.
 *
 * `bbp_get_reply_to_link()` points at `{topic}/?bbp_reply_to={id}#new-post`, which is
 * a single-topic URL — and since issue #37 removed the last decline, **every**
 * single-topic URL is a takeover screen, which renders no reply form until the
 * posting phase (P4). Followed on the fixture, the destination reports zero forms and
 * zero textareas: the reader taps Reply, arrives at the thread, and nothing has
 * happened. Measured, not assumed.
 *
 * It reached a reader on the profile's Replies Created tab, once per row.
 *
 * This is a **withholding of a broken control, not of a field.** The reskin tier's
 * standing rule is to preserve every field bbPress renders — that rule is about
 * information (counts, freshness, authors), and a link whose destination cannot
 * honour it carries none. Nothing else about the row changes.
 *
 * ⚠ **Delete this class when P4 lands a composer.** It is a statement about a
 * capability this release lacks, not a design decision about the control — and
 * DESIGN.md's #36 note already records the neighbouring case (the `reply` admin link
 * dropped from the moderation tray for the same reason). Both come back together.
 *
 * @since 0.3.0
 */
class ReplyToLink {

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
	 * Return nothing for the link, on screens Bulletin owns.
	 *
	 * Off our screens (`ScreenTier::None`) the link is left exactly as it is: another
	 * theme's bbPress pages are not ours to edit, and there the destination may well
	 * render a form.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $link The link markup bbPress assembled.
	 * @return mixed
	 */
	public function filter_reply_to_link( $link ) {
		if ( ScreenTier::None === $this->screen->tier() ) {
			return $link;
		}
		return '';
	}
}
