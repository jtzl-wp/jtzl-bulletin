<?php
/**
 * Recording that a thread has been read.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Unread;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Marks a thread read when a member opens it.
 *
 * This is the only place Bulletin writes during a GET, so its scope is drawn as
 * tightly as the feature allows:
 *
 * - **The reading screen only.** Not the forums index, not a thread list — seeing a
 *   row is not reading the thread. And not the load-more endpoints either: paging
 *   further into a thread already open must not re-stamp it, or a reply that arrived
 *   while it was open is marked read by the act of scrolling past where it will
 *   appear. Hooking `template_redirect` gives that for free, since bbPress's AJAX
 *   router never reaches it.
 * - **Signed in only.** Read state is per member and there is nowhere to keep it for
 *   anyone else — logged-out readers get no accent at all (Yoren, 2026-08-11).
 * - **Not while the door is shut.** A password-protected thread renders WordPress's
 *   password form in place of its replies, so arriving at the URL is not reading it.
 *
 * @since 0.5.0
 */
class ReadWriter {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.5.0
	 */
	private ContextInterface $wp;

	/**
	 * Screen-tier classifier.
	 *
	 * @var ScreenClassifier
	 * @since 0.5.0
	 */
	private ScreenClassifier $screen;

	/**
	 * Read state.
	 *
	 * @var ReadState
	 * @since 0.5.0
	 */
	private ReadState $reads;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface $wp     WordPress/bbPress seam.
	 * @param ScreenClassifier $screen Screen-tier classifier.
	 * @param ReadState        $reads  Read state.
	 */
	public function __construct( ContextInterface $wp, ScreenClassifier $screen, ReadState $reads ) {
		$this->wp     = $wp;
		$this->screen = $screen;
		$this->reads  = $reads;
	}

	/**
	 * Record the current request's thread as read, when it is one.
	 *
	 * Hooked on `template_redirect`.
	 *
	 * @since 0.5.0
	 */
	public function record(): void {
		if ( ! $this->screen->is_single_topic() || ! $this->wp->is_user_logged_in() ) {
			return;
		}

		$topic_id = $this->wp->get_topic_id();
		if ( $topic_id <= 0 || $this->wp->is_password_required( $topic_id ) ) {
			return;
		}

		$this->reads->mark_topic_read(
			$topic_id,
			$this->wp->get_current_user_id(),
			$this->wp->get_topic_last_active_datetime( $topic_id ),
			$this->wp->get_topic_last_active_id( $topic_id )
		);
	}
}
