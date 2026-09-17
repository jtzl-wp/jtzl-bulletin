<?php
/**
 * A single reply, rendered as a Bulletin post.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\Support\Icons;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders loop replies identically for initial and appended pages.
 * IDs match bbPress reply URLs; the client loads forward to resolve deep links.
 */
class ReplyView {

	private ContextInterface $wp;

	private ModerationActions $moderation;

	private AuthorEdit $author_edit;

	public function __construct( ContextInterface $wp, ModerationActions $moderation, AuthorEdit $author_edit ) {
		$this->wp          = $wp;
		$this->moderation  = $moderation;
		$this->author_edit = $author_edit;
	}

	/**
	 * Echo the current reply.
	 *
	 * Pass the thread explicitly because AJAX rendering has no ambient topic context.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Thread the reply belongs to.
	 */
	public function render( int $topic_id ): void {
		$reply_id = $this->wp->get_reply_id();

		$held = $this->wp->get_post_status( $reply_id ) === $this->wp->get_pending_status_id();

		printf(
			'<article class="%1$s" id="post-%2$s">',
			esc_attr( $held ? 'bltn-post bltn-post--pending' : 'bltn-post' ),
			esc_attr( (string) $reply_id )
		);
		$this->render_reply_context( $reply_id, $topic_id );
		echo '<div class="bltn-byline">';
		printf(
			'<span class="bltn-byline__name">%s</span>',
			esc_html( $this->wp->get_reply_author_display_name( $reply_id ) )
		);
		printf(
			// Keep the decorative separator outside the link and prefix the visible
			// date with a useful accessible name.
			'<span class="bltn-byline__time"><a class="bltn-permalink" href="%1$s"><span class="bltn-sr-only">%2$s </span>%3$s</a></span>',
			esc_url( $this->wp->get_reply_url( $reply_id ) ),
			esc_html__( 'Permalink to reply', 'jtzl-bulletin' ),
			esc_html( $this->wp->get_reply_post_date( $reply_id, true ) )
		);
		// Only the author can see a held reply; the chip explains its distinct state.
		if ( $held ) {
			printf(
				'<span class="bltn-chip bltn-chip--pending">%s</span>',
				esc_html__( 'Awaiting review', 'jtzl-bulletin' )
			);
		}

		// Render here so AJAX-appended replies receive the same action.
		$this->author_edit->render_for_reply( $topic_id, $reply_id );
		echo '</div>';
		echo '<div class="bltn-post__body">';
		$this->wp->the_reply_content( $reply_id ); // Echoes filtered post HTML (code/tables/images held in-column).
		echo '</div>';

		// Render here so AJAX-appended replies receive the same moderation controls.
		if ( $this->moderation->available( $topic_id ) ) {
			$this->moderation->render_for_reply( $reply_id );
		}

		echo '</article>';
	}

	/**
	 * Render the parent-reply link when threading is enabled.
	 *
	 * Reject parents outside this topic or unpublished parents to avoid disclosing
	 * inaccessible posts. A fragment is used because Bulletin pagination differs
	 * from bbPress's lead-topic page counting.
	 */
	private function render_reply_context( int $reply_id, int $topic_id ): void {
		if ( ! $this->wp->is_thread_replies_active() ) {
			return;
		}

		$parent = $this->wp->get_reply_to( $reply_id );
		if ( $parent <= 0 || $this->wp->get_reply_topic_id( $parent ) !== $topic_id ) {
			return;
		}
		if ( $this->wp->get_post_status( $parent ) !== $this->wp->get_public_status_id() ) {
			return;
		}

		printf(
			// The full phrase names the link meaningfully outside its surrounding text.
			'<p class="bltn-replyto"><a href="#post-%1$s">%2$s<span>%3$s</span></a></p>',
			esc_attr( (string) $parent ),
			Icons::reply_to(), // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
			esc_html(
				sprintf(
					/* translators: %s: display name of the member being replied to. */
					__( 'In reply to %s', 'jtzl-bulletin' ),
					$this->wp->get_reply_author_display_name( $parent )
				)
			)
		);
	}
}
