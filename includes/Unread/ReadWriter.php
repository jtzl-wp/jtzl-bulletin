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
 * @since 0.5.0
 */
class ReadWriter {

	private ContextInterface $wp;

	private ScreenClassifier $screen;

	private ReadState $reads;

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
