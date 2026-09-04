<?php
/**
 * Moderation actions on the reading view.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders bbPress's own moderation links for a topic or a reply, inside a wrapper the
 * reading view can hide as one.
 *
 * @since 0.3.0
 */
class ModerationActions {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Whether this reader moderates the thread.
	 *
	 * Topic-level capability keeps moderation mode consistent across reply rows.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic being read.
	 * @return bool
	 */
	public function available( int $topic_id ): bool {
		return $this->wp->current_user_can_moderate( $topic_id );
	}

	/**
	 * The thread's own actions — close, pin, merge, trash, spam, approve, edit.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic being read.
	 */
	public function render_for_topic( int $topic_id ): void {
		$this->render_group(
			$this->wp->get_topic_moderation_links( $topic_id ),
			__( 'Thread actions', 'jtzl-bulletin' )
		);
	}

	/**
	 * One reply's actions — edit, move, split, trash, spam, approve.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply being rendered.
	 */
	public function render_for_reply( int $reply_id ): void {
		$this->render_group(
			$this->wp->get_reply_moderation_links( $reply_id ),
			__( 'Reply actions', 'jtzl-bulletin' )
		);
	}

	/**
	 * Render the moderation-mode toggle.
	 *
	 * `aria-expanded` carries state. `aria-controls` is omitted because the toggle
	 * controls the thread group and every post's action row.
	 *
	 * @since 0.3.0
	 */
	public function render_toggle(): void {
		printf(
			'<button type="button" class="bltn-modtoggle" aria-expanded="false" data-bltn-modtoggle data-bltn-label-on="%1$s">%2$s</button>',
			esc_attr__( 'Done', 'jtzl-bulletin' ),
			esc_html__( 'Moderate', 'jtzl-bulletin' )
		);
	}

	/**
	 * One group of links, or nothing at all.
	 *
	 * @since 0.3.0
	 *
	 * @param string $links bbPress's link markup, or ''.
	 * @param string $label Accessible name for the group.
	 */
	private function render_group( string $links, string $label ): void {
		if ( '' === $links ) {
			return;
		}

		printf(
			'<div class="bltn-mod" role="group" aria-label="%1$s">%2$s</div>',
			esc_attr( $label ),
			$links // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bbPress-generated markup; each link is escaped at source (wp_nonce_url + esc_url/esc_attr in bbp_get_*_link).
		);
	}
}
