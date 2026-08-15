<?php
/**
 * The acknowledgement a reply held for moderation gets on the redirect.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Tells the author their reply went somewhere, once, on the screen bbPress sends
 * them back to.
 *
 * ## It speaks only where there is no row to speak
 *
 * `Query\PendingVisibility` puts a `pending` reply back in the thread for its author,
 * wearing an "Awaiting review" chip. That answers "where did it go" better than a
 * sentence does, and it answers it where the reader is already looking — bbPress
 * redirects them to `#post-{id}`, which is the row itself. So for that case this
 * renders **nothing**.
 *
 * ⚠ **Measured, not assumed** (2026-08-15, on the fixture). Built to fire in every held
 * case — the literal reading of §3 decision 6 — the sentence and the chip land about
 * 40px apart saying the same two words, because a held reply is always the newest post
 * in the thread and the composer sits directly under it. Two things saying "awaiting
 * review" inside one screen-height is the clutter this product exists to remove.
 * Narrowed on Yoren's call, 2026-08-15; decision 6's requirement that *one* message
 * covers pending and spam alike is untouched.
 *
 * What is left for it are the two cases where nothing is visible at all:
 *
 * - **Spam.** Never shown back, deliberately — a spammed reply reappearing is a free
 *   tuning oracle for whoever wrote it.
 * - **Anonymous.** `post_author = 0` and the identity in post meta, so there is no
 *   author for the widening to match; the documented carve-out (§3 decision 6).
 *
 * In both, bbPress redirects to an anchor for a post no query will return, and the
 * thread visibly does not contain what was just written.
 *
 * ## One message for both, and it says the true, useless-to-a-spammer thing
 *
 * Settled by Yoren, 2026-08-14. Naming spam is the one message with a negative
 * expected return: a real member cannot act on it, and a spammer gets a tuning
 * signal. "Awaiting review" is true of both — a held reply *is* awaiting review.
 *
 * ## A query argument, not a transient
 *
 * Stateless, so it cannot be delivered to the wrong reader, survive a page they did
 * not ask for, or need cleaning up. Read as a **presence flag only** — the value is
 * never echoed and never distinguishes the two states — and stripped from the address
 * bar by `reading.ts` after the render, so a reload or a shared link does not
 * re-announce it.
 *
 * @since 0.5.0
 */
class HeldNotice {

	/**
	 * The query argument carrying the acknowledgement.
	 *
	 * @var string
	 */
	public const FLAG = 'bltn_held';

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Flag the redirect when the reply just written will be nowhere on the screen it
	 * lands on. Hooked on `bbp_new_reply_redirect_to`.
	 *
	 * ⚠ **The decision is made here and only here, because this is the one moment
	 * that holds all three facts**: the reply's status, its author, and who is asking.
	 * The alternative — letting the renderer notice whether a held row was printed —
	 * would carry state from the replies loop to the compose slot to answer a
	 * question that was already answerable at the redirect.
	 *
	 * The public-status test asks bbPress's list rather than naming `pending` and
	 * `spam`, for the same reason `View\AuthorEdit` asks it: the question is "may
	 * anyone else see this", and a site that adds a status to bbPress's moderation
	 * vocabulary gets the acknowledgement without editing this class.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $url         Where bbPress is about to send the author.
	 * @param mixed $redirect_to The reader's own requested destination, unused here.
	 * @param mixed $reply_id    The reply just written.
	 * @return mixed
	 */
	public function filter_redirect( $url, $redirect_to = null, $reply_id = null ) {
		unset( $redirect_to );

		if ( ! is_string( $url ) || ! is_numeric( $reply_id ) ) {
			return $url;
		}

		$reply = (int) $reply_id;

		// Published: nothing happened that is worth saying.
		if ( in_array( $this->wp->get_post_status( $reply ), $this->wp->get_public_reply_statuses(), true ) ) {
			return $url;
		}

		// Held, and its author will meet the row itself — chip and all — at the anchor
		// bbPress is sending them to. The row is the better message.
		if ( $this->shown_back( $reply ) ) {
			return $url;
		}

		return $this->wp->add_query_arg( self::FLAG, '1', $url );
	}

	/**
	 * Whether Query\PendingVisibility will put this reply back in the thread for the
	 * reader who wrote it.
	 *
	 * The same three conditions that class widens on, in the same order: the status it
	 * admits, an author to match, and that author being the one reading. Stated twice
	 * rather than shared, and that is the safer duplication — this copy only decides
	 * whether a sentence appears, so if the two ever drift the cost is a message that
	 * is redundant or missing, never a row that is disclosed.
	 *
	 * @since 0.5.0
	 *
	 * @param int $reply_id Reply just written.
	 * @return bool
	 */
	private function shown_back( int $reply_id ): bool {
		$author = $this->wp->get_post_author( $reply_id );

		return $this->wp->get_post_status( $reply_id ) === $this->wp->get_pending_status_id()
			&& $author > 0
			&& $author === $this->wp->get_current_user_id();
	}

	/**
	 * Echo the acknowledgement, if this request is carrying the flag.
	 *
	 * @since 0.5.0
	 */
	public function render(): void {
		if ( ! $this->wp->has_query_flag( self::FLAG ) ) {
			return;
		}

		printf(
			'<p class="bltn-compose__note bltn-compose__note--held" role="status">%s</p>',
			esc_html__( 'Your reply is awaiting review.', 'jtzl-bulletin' )
		);
	}
}
