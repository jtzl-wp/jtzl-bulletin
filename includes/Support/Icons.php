<?php
/**
 * Inline SVG icons.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\Support;

/**
 * Static, self-contained SVG glyphs used by the reading chrome.
 *
 * These use `stroke="currentColor"` — never `stroke="var(--x)"`, which iOS
 * Safari silently drops inside SVG presentation attributes (see CLAUDE.md).
 */
class Icons {

	/**
	 * Account glyph.
	 *
	 * @return string
	 */
	public static function account(): string {
		return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.4"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>';
	}

	/**
	 * Back chevron (large).
	 *
	 * @return string
	 */
	public static function chevron_left(): string {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
	}

	/**
	 * Prev chevron (small, for the thread nav bar).
	 *
	 * @return string
	 */
	public static function nav_prev(): string {
		return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
	}

	/**
	 * Next chevron (small, for the thread nav bar).
	 *
	 * @return string
	 */
	public static function nav_next(): string {
		return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';
	}
}
