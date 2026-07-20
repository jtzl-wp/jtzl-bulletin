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
		return $this->is_forums_index()
			|| $this->is_single_forum()
			|| $this->is_single_topic();
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
	 * Declines the takeover when threaded replies are active: bbp_has_replies()
	 * forces posts_per_page to -1 for a hierarchical query, so the whole thread
	 * would load unpaginated and "load more replies" would never appear. Falling
	 * through to the theme's own bbPress templates — which render threading
	 * correctly — beats shipping that performance cliff (see issue #11).
	 *
	 * @return bool
	 */
	public function is_single_topic(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_single_topic() && ! $this->wp->is_thread_replies_active();
	}
}
