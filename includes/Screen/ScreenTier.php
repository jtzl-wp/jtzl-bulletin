<?php
/**
 * How Bulletin treats a given request.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Screen;

/**
 * Bulletin's two treatments for the pages it owns, and a default for everything else.
 *
 * @since 0.3.0
 */
enum ScreenTier {
	case Takeover;
	case Reskin;
	case None;
}
