<?php
/**
 * A single reply, rendered as a Bulletin post.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders the reply currently in the replies loop. Shared by the reading view
 * and the load-more AJAX handler so the markup is byte-identical whether a reply
 * arrives with the page or is appended later. The id="post-{reply_id}" anchor
 * matches bbp_get_reply_url(), which is what makes deep-links resolvable.
 *
 * @since 0.1.0
 */
class ReplyView {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Echo the current reply.
	 *
	 * @since 0.1.0
	 */
	public function render(): void {
		$reply_id = $this->wp->get_reply_id();

		printf( '<article class="bltn-post" id="post-%s">', esc_attr( (string) $reply_id ) );
		echo '<div class="bltn-byline">';
		printf(
			'<span class="bltn-byline__name">%s</span>',
			esc_html( $this->wp->get_reply_author_display_name( $reply_id ) )
		);
		printf(
			'<span class="bltn-byline__time">%s</span>',
			esc_html( $this->wp->get_reply_post_date( $reply_id, true ) )
		);
		echo '</div>';
		echo '<div class="bltn-post__body">';
		$this->wp->the_reply_content( $reply_id ); // Echoes filtered post HTML (code/tables/images held in-column).
		echo '</div>';
		echo '</article>';
	}
}
