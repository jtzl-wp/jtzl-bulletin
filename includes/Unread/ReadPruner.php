<?php
/**
 * Removing read state about things that no longer exist.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Unread;

use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Deletes read rows whose topic or whose member has been deleted.
 *
 * @since 0.5.0
 */
class ReadPruner {

	private ContextInterface $wp;

	private ReadState $reads;

	public function __construct( ContextInterface $wp, ReadState $reads ) {
		$this->wp    = $wp;
		$this->reads = $reads;
	}

	/**
	 * Forget a deleted topic.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $post_id Deleted post ID.
	 * @param mixed $post    The post object WordPress has just deleted.
	 */
	public function forget_deleted_topic( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		$type    = is_object( $post ) ? (string) ( $post->post_type ?? '' ) : '';

		if ( $post_id <= 0 || $type !== $this->wp->get_topic_post_type() ) {
			return;
		}

		$this->reads->forget_topic( $post_id );
	}

	/**
	 * Forget a deleted member.
	 *
	 * Post reassignment must not transfer the deleted member's read state.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $user_id Deleted user ID.
	 */
	public function forget_deleted_user( $user_id ): void {
		$this->reads->forget_user( (int) $user_id );
	}
}
