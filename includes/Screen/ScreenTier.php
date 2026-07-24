<?php
/**
 * How Bulletin treats a given request.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Screen;

/**
 * The screen inventory has exactly two treatments for the pages we own, plus a
 * default for everything else (see docs/p2-screen-inventory.md):
 *
 * - Takeover — we render our own minimal document (templates/app.php). A small,
 *   enumerated set of hero reading screens; the active theme's markup and styles
 *   are left out entirely.
 * - Reskin — bbPress renders its OWN markup, but inside Bulletin's chrome, with
 *   the theme's styles suppressed and bbPress's own CSS kept. This is the default
 *   for every reader-reachable bbPress screen that isn't a takeover, which is what
 *   guarantees a reader never drops into the site theme mid-flow. ("Shell-wrap" is
 *   the name of this mechanism — it is not a separate tier.)
 * - None — not one of ours. Non-bbPress pages, and the few bbPress screens owned
 *   by another phase (the posting composer and edit forms), are left untouched for
 *   the active theme to render.
 *
 * @since 0.3.0
 */
enum ScreenTier {
	case Takeover;
	case Reskin;
	case None;
}
