<?php
/**
 * The forum screen's "Start a thread" control and form.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders topic creation only when bbPress accepts it.
 * Categories need an explicit guard because bbPress's form-access helper allows them.
 */
class StartThread {

	private ContextInterface $wp;

	private BbPressForm $form;

	public function __construct( ContextInterface $wp, BbPressForm $form ) {
		$this->wp   = $wp;
		$this->form = $form;
	}

	/**
	 * The fragment link reaches the open form without JavaScript; the script upgrades it.
	 *
	 * @param int $forum_id The forum being read.
	 */
	public function render_bar( int $forum_id ): void {
		if ( $this->may_start( $forum_id ) ) {
			printf(
				'<div class="bltn-composebar"><a class="bltn-composebar__btn" href="#new-post" aria-controls="new-post" data-bltn-compose-open data-bltn-cancel="%s">%s</a></div>',
				esc_attr__( 'Cancel', 'jtzl-bulletin' ),
				esc_html__( 'Start a thread', 'jtzl-bulletin' )
			);
			return;
		}

		/*
		 * Logged out is the one refusal with something to offer: sign in, and the
		 * question becomes answerable. The others are answered already.
		 *
		 * Signing in cannot make a category accept a topic or reopen a closed forum.
		 */
		if ( ! $this->wp->is_user_logged_in()
			&& ! $this->wp->is_forum_closed( $forum_id )
			&& ! $this->wp->is_forum_category( $forum_id ) ) {
			printf(
				'<div class="bltn-composebar"><a class="bltn-composebar__btn" href="%s">%s</a></div>',
				esc_url( $this->wp->get_login_url( $this->wp->get_current_url() ) ),
				esc_html__( 'Sign in to start a thread', 'jtzl-bulletin' )
			);
		}
	}

	/**
	 * Forms with validation errors remain open so the error and entered content are visible.
	 *
	 * @param int $forum_id The forum being read.
	 */
	public function render_form( int $forum_id ): void {
		if ( $this->may_start( $forum_id ) ) {
			printf(
				'<div class="bltn-compose" data-bltn-compose="%s"><div class="bltn-compose__form">',
				esc_attr( $this->wp->has_errors() ? 'open' : 'collapsible' )
			);
			$this->form->render( 'topic' );
			echo '</div></div>';
			return;
		}

		if ( $this->wp->is_forum_closed( $forum_id ) ) {
			printf(
				'<div class="bltn-compose"><p class="bltn-compose__note">%s</p></div>',
				esc_html__( 'This forum is closed to new topics.', 'jtzl-bulletin' )
			);
		}
	}

	/**
	 * Whether a thread can actually be started here.
	 *
	 * Two questions, because bbPress's own helper only answers one of them — see the
	 * class note on categories.
	 *
	 * @since 0.5.0
	 *
	 * @param int $forum_id The forum being read.
	 * @return bool
	 */
	private function may_start( int $forum_id ): bool {
		return $this->wp->can_access_create_topic_form()
			&& ! $this->wp->is_forum_category( $forum_id );
	}
}
