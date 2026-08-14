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
	 * Moderation actions renderer.
	 *
	 * @var ModerationActions
	 */
	private ModerationActions $moderation;

	/**
	 * The author's own Edit control.
	 *
	 * @var AuthorEdit
	 */
	private AuthorEdit $author_edit;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface  $wp          WordPress/bbPress seam.
	 * @param ModerationActions $moderation  Moderation actions renderer.
	 * @param AuthorEdit        $author_edit The author's own Edit control.
	 */
	public function __construct( ContextInterface $wp, ModerationActions $moderation, AuthorEdit $author_edit ) {
		$this->wp          = $wp;
		$this->moderation  = $moderation;
		$this->author_edit = $author_edit;
	}

	/**
	 * Echo the current reply.
	 *
	 * The thread is passed rather than read from the loop because this renders in two
	 * places: the page template, where bbp_get_topic_id() would answer, and the
	 * load-more endpoint, where it would not — an AJAX request sets no topic context,
	 * so it returns 0. That would have silently dropped the moderation row from every
	 * appended reply while page 1 kept its own, which reads as a rendering fault
	 * rather than a missing argument.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Thread the reply belongs to.
	 */
	public function render( int $topic_id ): void {
		$reply_id = $this->wp->get_reply_id();

		printf( '<article class="bltn-post" id="post-%s">', esc_attr( (string) $reply_id ) );
		$this->render_reply_context( $reply_id, $topic_id );
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
		// Last in the byline, where the flex row's trailing slot is, and rendered here
		// rather than in the template so replies the load-more endpoint appends carry it
		// too — the same reason the moderation row below is built here.
		$this->author_edit->render_for_reply( $topic_id, $reply_id );
		echo '</div>';
		echo '<div class="bltn-post__body">';
		$this->wp->the_reply_content( $reply_id ); // Echoes filtered post HTML (code/tables/images held in-column).
		echo '</div>';

		// Below the body, where an action reads as being about the post above it, and
		// where it cannot push the reading content down the screen. Rendered here — not
		// only in the page template — so replies the load-more endpoint appends carry
		// their row too; the mode is a class on the enclosing article, so those rows are
		// in whatever state the reader has the screen in (issue #36).
		if ( $this->moderation->available( $topic_id ) ) {
			$this->moderation->render_for_reply( $reply_id );
		}

		echo '</article>';
	}

	/**
	 * The "In reply to …" line, for a reply that answers another reply.
	 *
	 * Bulletin's threading is a sequence and a line, and no indentation (issue #37).
	 * A reply is placed under the one it answers — Query\ReplyOrder computes that
	 * order — and this names the parent for the cases position cannot show.
	 *
	 * The order carries most of it. Under it, a reply usually IS the post below its
	 * parent, so the line is confirming what the reader can already see. It earns
	 * its place on the cases where the sequence is silent: a second child sits after
	 * its elder sibling's whole subtree, so its parent may be well above it; and at a
	 * page boundary a child opens a page whose parent closed the last one. Neither is
	 * rare enough to leave a reader guessing, and position alone cannot distinguish
	 * "answers the post above" from "answers something further up".
	 *
	 * No indentation, though. It buys nothing the order has not already given, and
	 * it costs the reading measure a step per level on the screen with the least
	 * width to spare — which is the whole product's constraint.
	 *
	 * ⚠ The order being ours is a consequence of bbPress, not a preference:
	 * bbp_has_replies() pages a hierarchical query by *root* replies and loads every
	 * descendant, which is why it forces posts_per_page to -1 (issue #12). So
	 * Query\ReplyQuery asks bbPress for a flat query and supplies the reading order
	 * itself.
	 *
	 * No excerpt of the parent. Discourse and friends quote it because their parent
	 * is collapsed somewhere else; ours never is — the order puts it above, usually
	 * immediately above, and the reading view loads forward from page 1. Quoting it
	 * would print the same words twice, a line apart.
	 *
	 * "Usually" is doing real work there. A parent is above its child in the reading
	 * order by construction, but it can be off the loaded pages: the order is sliced
	 * 15 at a time, so a parent that closes page 1 has children opening page 2 — and
	 * a page-2 reply is in the document only after the reader has loaded it.
	 * src/reading.ts handles that rather than this file: a tap on a target the
	 * document does not hold pages forward until it does.
	 *
	 * Three conditions, each load-bearing:
	 *
	 *  - Threading is on. bbPress renders no "reply to" control when it is off, but
	 *    `_bbp_reply_to` survives the switch, so a forum that ever had threading keeps
	 *    the meta. An admin who turns threading off is asking for a flat conversation
	 *    and we honour that. It also means this whole feature is a no-op on a default
	 *    install, where bbp_thread_replies() is false.
	 *  - The parent belongs to THIS thread. A cross-thread parent is reachable for
	 *    real: bbp_move_reply_handler() re-parents a reply to another topic without
	 *    clearing `_bbp_reply_to`, and bbp_validate_reply_to() only asks whether the
	 *    target is a reply, not which thread it is in. (bbp_split_topic() does clear
	 *    it; merge keeps everything in one thread, so neither of those leaks.) The
	 *    same check catches a parent that is not a reply at all — bbp_get_reply_to()
	 *    reads the meta raw, and bbp_get_reply_topic_id() answers 0 for a non-reply,
	 *    which is why bbp_has_replies() carries a normalisation of its own.
	 *  - The parent is published. To a reader who cannot see a trashed or spammed parent
	 *    there is no parent, and naming one would hand them a link to an anchor that is
	 *    not in the document. A moderator viewing all statuses loses the line in that
	 *    case too, which is a fair price for not branching this on capability.
	 *
	 * The href is a bare fragment, not bbp_get_reply_url(). bbPress's URL carries a
	 * page segment counted against a loop that includes the lead topic while ours does
	 * not (see the class docblock), so it can name a later page for a post that is
	 * already on screen — reloading the whole document to reach something visible.
	 *
	 * The fragment is not left to the browser either: src/reading.ts intercepts it, so
	 * the reader gets the same highlight an arriving permalink gets, the app frame
	 * stays put, and an unloaded target is paged in rather than silently doing
	 * nothing. That file carries the measurements.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply being rendered.
	 * @param int $topic_id Thread it belongs to.
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
			// The whole phrase is the link, so its accessible name says where it goes
			// without the surrounding text (WCAG 2.4.4). The author name is the only
			// payload: it says which way the conversation turned, and the tap resolves
			// which post when a member has answered more than once.
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
