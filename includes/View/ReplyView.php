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
 * Each reply carries that URL on its timestamp. bbPress prints a separate
 * "#{post_id}" link (loop-single-reply.php) and that shipped first, but the number
 * is an identifier, not a position — "#1247" tells a reader nothing and a column
 * of them down the byline is exactly the clutter this app exists to remove
 * (Yoren, 2026-07-27). The timestamp is already there, already the right size, and
 * "the time is the permalink" is the convention every forum and timeline uses, so
 * the affordance costs no pixels at all.
 *
 * Deliberately undecorated — no underline, no glyph. On a read-only screen this
 * is a nicety for sharing, not a navigation primitive: a reader who knows the
 * convention gets it for free, and one who does not loses nothing they had. Buying
 * discoverability with a dotted rule on every byline would cost more than the
 * feature is worth.
 *
 * The URL's page segment is bbPress's: it counts the opening post as the first
 * item of the replies loop when bbp_show_lead_topic() is off, and our loop is
 * replies-only, so a boundary reply can name a later page than the one it would
 * sit on here. Immaterial on this screen — the reading view always renders page 1
 * and walks forward until the anchor exists (src/reading.ts) — and worth the
 * consistency: this is the URL bbPress puts in subscription emails and search
 * results, so a reply has one address rather than two.
 *
 * The opening post's timestamp is not a link. Its permalink is the topic's, which
 * is the URL the reader is already on, so it would go nowhere they are not.
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
			// The anchor sits inside the span rather than replacing it, so the
			// separator the span draws with ::before stays outside the link — a
			// clickable middot would be a target nobody aimed at.
			//
			// The hidden prefix is what a screen reader announces: "3 days ago, link"
			// says nothing out of context. The visible text stays inside the
			// accessible name, which is WCAG 2.5.3.
			//
			// No punctuation inside the translatable string — the separating space
			// belongs to this format, not to the phrase, and a comma a translator
			// cannot move is one they have to work around.
			'<span class="bltn-byline__time"><a class="bltn-permalink" href="%1$s"><span class="bltn-sr-only">%2$s </span>%3$s</a></span>',
			esc_url( $this->wp->get_reply_url( $reply_id ) ),
			esc_html__( 'Permalink to reply', 'jtzl-bulletin' ),
			esc_html( $this->wp->get_reply_post_date( $reply_id, true ) )
		);
		echo '</div>';
		echo '<div class="bltn-post__body">';
		$this->wp->the_reply_content( $reply_id ); // Echoes filtered post HTML (code/tables/images held in-column).
		echo '</div>';
		echo '</article>';
	}
}
