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
		if ( '' === $this->wp->get_topic_moderation_links( $topic_id ) ) {
			return;
		}

		$this->open_group( __( 'Thread actions', 'jtzl-bulletin' ) );
		$this->wp->print_topic_moderation_links( $topic_id );
		echo '</div>';
	}

	/**
	 * One reply's actions — edit, move, split, trash, spam, approve.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply being rendered.
	 */
	public function render_for_reply( int $reply_id ): void {
		if ( '' === $this->wp->get_reply_moderation_links( $reply_id ) ) {
			return;
		}

		$this->open_group( __( 'Reply actions', 'jtzl-bulletin' ) );
		$this->wp->print_reply_moderation_links( $reply_id );
		echo '</div>';
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
	 * Open the wrapper for one group of bbPress's links.
	 *
	 * The links themselves are printed by bbPress, which keeps the inline confirm()
	 * on its permanent-delete link.
	 *
	 * @since 0.6.5
	 *
	 * @param string $label Accessible name for the group.
	 */
	private function open_group( string $label ): void {
		printf( '<div class="bltn-mod" role="group" aria-label="%s">', esc_attr( $label ) );
	}
}
