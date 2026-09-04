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
 * @since 0.3.0
 */
enum ScreenTier {
	case Takeover;
	case Reskin;
	case None;
}
