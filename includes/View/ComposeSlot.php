<?php
/**
 * The reading view's compose slot.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders the thread's reply form or the applicable closed/sign-in state.
 * JavaScript collapses the complete server-rendered form, preserving no-JS access.
 */
class ComposeSlot {

	private ContextInterface $wp;

	private BbPressForm $form;

	private HeldNotice $held;

	public function __construct( ContextInterface $wp, BbPressForm $form, HeldNotice $held ) {
		$this->wp   = $wp;
		$this->form = $form;
		$this->held = $held;
	}

	/**
	 * Echo the slot for a topic.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Topic being read.
	 * @param int $forum_id Forum it belongs to.
	 */
	public function render( int $topic_id, int $forum_id ): void {
		// Before the branch, not inside it. The acknowledgement is about the reply
		// just written, and it is owed whatever the slot now shows — a moderator
		// closing the thread between the submit and the redirect must not swallow it.
		$this->held->render();

		if ( ! $this->wp->current_user_can_moderate( $topic_id ) ) {
			if ( $this->wp->is_forum_closed( $forum_id ) ) {
				$this->note( __( 'This forum is closed to new posts.', 'jtzl-bulletin' ) );
				return;
			}

			if ( $this->wp->is_topic_closed( $topic_id ) ) {
				$this->note( __( 'This thread is closed to new replies.', 'jtzl-bulletin' ) );
				return;
			}
		}

		if ( $this->wp->can_access_create_reply_form() ) {
			$this->composer();
			return;
		}

		if ( ! $this->wp->is_user_logged_in() ) {
			$this->sign_in();
		}
	}

	/**
	 * The reply form itself — bbPress's — wrapped so the script can collapse it.
	 *
	 * @since 0.5.0
	 */
	private function composer(): void {
		$state = $this->opens_expanded() ? 'open' : 'collapsible';

		printf(
			'<div class="bltn-compose" data-bltn-compose="%s">',
			esc_attr( $state )
		);

		/*
		 * The trigger. Hidden by default and revealed by the collapse class, so the
		 * no-JS document shows the form and never a button that cannot open anything.
		 * `aria-expanded` starts true for the same reason: what is rendered IS open.
		 */
		printf(
			'<button type="button" class="bltn-compose__open" aria-expanded="true" aria-controls="new-post" data-bltn-compose-open data-bltn-cancel="%s">%s</button>',
			esc_attr__( 'Cancel', 'jtzl-bulletin' ),
			esc_html__( 'Write a reply', 'jtzl-bulletin' )
		);

		echo '<div class="bltn-compose__form">';
		$this->form->render( 'reply' );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Whether the composer must arrive open rather than at rest.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	private function opens_expanded(): bool {
		return $this->wp->has_errors() || $this->wp->get_requested_reply_to() > 0;
	}

	/**
	 * One quiet line: a fact about this thread, stated where a reader meets it.
	 *
	 * @since 0.5.0
	 *
	 * @param string $text The line.
	 */
	private function note( string $text ): void {
		printf(
			'<div class="bltn-compose"><p class="bltn-compose__note">%s</p></div>',
			esc_html( $text )
		);
	}

	/**
	 * The sign-in control, carrying this URL — query string included — back.
	 *
	 * @since 0.5.0
	 */
	private function sign_in(): void {
		$here = $this->wp->get_current_url();

		printf(
			'<div class="bltn-compose"><a class="bltn-compose__open" href="%s">%s</a></div>',
			esc_url( $this->wp->get_login_url( $here ) ),
			esc_html__( 'Sign in to reply', 'jtzl-bulletin' )
		);
	}
}
