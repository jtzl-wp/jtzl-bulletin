<?php
/**
 * Inline SVG icons.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Support;

/**
 * Static, self-contained SVG glyphs used by the reading chrome.
 *
 * These use `stroke="currentColor"` — never `stroke="var(--x)"`, which iOS
 * Safari silently drops inside SVG presentation attributes (see CLAUDE.md).
 *
 * @since 0.1.0
 */
class Icons {

	/**
	 * Account glyph.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public static function account(): string {
		return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.4"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>';
	}

	/**
	 * Back chevron (large).
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public static function chevron_left(): string {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
	}

	/**
	 * Prev chevron (small, for the thread nav bar).
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public static function nav_prev(): string {
		return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
	}

	/**
	 * Next chevron (small, for the thread nav bar).
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public static function nav_next(): string {
		return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';
	}

	/**
	 * Magnifier.
	 *
	 * Used twice, and never at once: as the app bar's entry point into the search
	 * screen, and as that screen's own submit control. The bar's copy is suppressed
	 * on the search screen precisely so one glyph never means two things at the same
	 * time (issue #35).
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public static function search(): string {
		return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.4 15.4L20 20"/></svg>';
	}

	/**
	 * Reply-context arrow — turns up and back to the left.
	 *
	 * Points the way the link goes. The parent of a reply is always older, and the
	 * reading view renders oldest-first, so the post it names is always further up
	 * the screen (issue #37).
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public static function reply_to(): string {
		return '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14l-5-5 5-5"/><path d="M4 9h10a5 5 0 0 1 5 5v5"/></svg>';
	}
}
