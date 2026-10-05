<?php
/**
 * The author's own Edit control, in the byline of their own post.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders an author's edit link outside moderation mode while the post is public.
 * Moderators are excluded because bbPress bypasses their edit-lock window.
 */
class AuthorEdit {

	private ContextInterface $wp;

	private ModerationActions $moderation;

	public function __construct( ContextInterface $wp, ModerationActions $moderation ) {
		$this->wp         = $wp;
		$this->moderation = $moderation;
	}

	/**
	 * The opening post's control — the topic itself.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Topic being read.
	 */
	public function render_for_topic( int $topic_id ): void {
		if ( ! $this->offered( $topic_id, $topic_id, $this->wp->get_public_topic_statuses() ) ) {
			return;
		}

		if ( '' === $this->wp->get_topic_edit_link( $topic_id ) ) {
			return;
		}

		echo '<span class="bltn-byline__edit">';
		$this->wp->print_topic_edit_link( $topic_id );
		echo '</span>';
	}

	/**
	 * The topic ID keeps the moderation decision consistent across all replies.
	 *
	 * @param int $topic_id Thread the reply belongs to.
	 * @param int $reply_id Reply being rendered.
	 */
	public function render_for_reply( int $topic_id, int $reply_id ): void {
		if ( ! $this->offered( $topic_id, $reply_id, $this->wp->get_public_reply_statuses() ) ) {
			return;
		}

		if ( '' === $this->wp->get_reply_edit_link( $reply_id ) ) {
			return;
		}

		echo '<span class="bltn-byline__edit">';
		$this->wp->print_reply_edit_link( $reply_id );
		echo '</span>';
	}

	/**
	 * Reject moderators before requesting a link because bbPress bypasses their
	 * edit window.
	 *
	 * @since 0.5.0
	 *
	 * @param int               $topic_id Thread being read, for the moderation test.
	 * @param int               $post_id  Topic or reply whose status is tested.
	 * @param array<int,string> $statuses Public statuses for that kind.
	 * @return bool
	 */
	private function offered( int $topic_id, int $post_id, array $statuses ): bool {
		if ( $this->moderation->available( $topic_id ) ) {
			return false;
		}

		return in_array( $this->wp->get_post_status( $post_id ), $statuses, true );
	}
}
