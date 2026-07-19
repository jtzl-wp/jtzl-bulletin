<?php
/**
 * Reading-screen detection.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\Screen;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Decides which pages Bulletin takes over. Everything else — non-bbPress pages,
 * and the bbPress pages we have no v1 design for (profiles, topic tags, edit
 * forms, search) — is left on the active theme.
 */
class ReadingScreen {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Whether the current request is one of the three reading screens.
	 *
	 * @return bool
	 */
	public function is_reading_screen(): bool {
		return $this->wp->is_bbpress() && (
			$this->wp->is_forum_archive()
			|| $this->wp->is_single_forum()
			|| $this->wp->is_single_topic()
		);
	}

	/**
	 * Whether the current request is the forums index (home).
	 *
	 * @return bool
	 */
	public function is_forums_index(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_forum_archive();
	}

	/**
	 * Whether the current request is a single forum's thread list.
	 *
	 * @return bool
	 */
	public function is_single_forum(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_single_forum();
	}

	/**
	 * Whether the current request is a single topic's reading view.
	 *
	 * @return bool
	 */
	public function is_single_topic(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_single_topic();
	}
}
