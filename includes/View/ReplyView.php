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
 * Each reply carries that URL as a visible "#{id}" permalink, which is bbPress's
 * own affordance and its own label (loop-single-reply.php). The number is the
 * post ID bbPress uses everywhere else, not a position in the thread. The URL's
 * page segment is bbPress's: it counts the opening post as the first item of the
 * replies loop when bbp_show_lead_topic() is off, and our loop is replies-only,
 * so a boundary reply can name a later page than the one it would sit on here.
 * Immaterial on this screen — the reading view always renders page 1 and walks
 * forward until the anchor exists (src/reading.ts) — and worth the consistency:
 * this is the URL bbPress puts in subscription emails and search results, so a
 * reply has one address rather than two.
 *
 * The opening post gets none. Its permalink is the topic's, which is the URL the
 * reader is already on, so the link would go nowhere they are not.
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
		printf(
			// The hidden prefix is what a screen reader announces; "#1247" alone is
			// clear beside the byline and meaningless in a list of links. Keeping the
			// visible text inside the accessible name is WCAG 2.5.3.
			'<a class="bltn-permalink" href="%1$s"><span class="bltn-sr-only">%2$s </span>#%3$s</a>',
			esc_url( $this->wp->get_reply_url( $reply_id ) ),
			esc_html__( 'Permalink to reply', 'jtzl-bulletin' ),
			esc_html( (string) $reply_id )
		);
		echo '</div>';
		echo '<div class="bltn-post__body">';
		$this->wp->the_reply_content( $reply_id ); // Echoes filtered post HTML (code/tables/images held in-column).
		echo '</div>';
		echo '</article>';
	}
}
