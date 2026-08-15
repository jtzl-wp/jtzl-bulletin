<?php
/**
 * A reader's own reply, held for moderation, in the thread they wrote it in.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Lets one reader — and only that reader — see their own `pending` replies in the
 * thread they are reading.
 *
 * ## The problem this exists for
 *
 * `bbp_check_for_moderation()` and Akismet hold a reply as `pending`. bbPress then
 * redirects its author to `#post-{id}`, an anchor for a post no query will return —
 * so the thread they just wrote in visibly does not contain what they wrote. That
 * reads as a bug, and no acknowledgement message undoes the impression (§3
 * decision 6, settled by Yoren 2026-08-14).
 *
 * ## Why it is a query change and not a template one
 *
 * `pending` is a WordPress *protected* status, so nothing that renders can help: the
 * post never reaches the loop. And the fix has to land at **two** query sites or
 * paging drifts, because the reading view splits the work between them:
 *
 * | Site | What it decides |
 * |---|---|
 * | `Query\ReplyQuery::args()` | the rows on the page |
 * | `ContextInterface::get_reply_parents()` | the threaded reading order, and the page count from it |
 *
 * A held reply admitted by one and not the other is exactly CLAUDE.md's trap #4 —
 * a page that renders 14 rows where the count says 15, or a load-more that fetches
 * a short page — with a moderation queue as the source of the disagreement instead
 * of a tied timestamp. So this class is the *only* thing that arms either, and
 * `ReplyQuery` calls it for both: `marker()` for the order, and `arm()` for the page.
 *
 * ## The shape, and why it is this shape
 *
 * A marker rides the query's own variables — `Query\SearchVisibility`'s trick, and
 * the reason both halves are stateless: an unrecognised argument is carried into
 * `WP_Query::$query_vars` untouched, so `widen()` can ask the query it was handed
 * whether this is one of ours instead of holding a flag between calls. Nothing else
 * on the site arms it, including bbPress's own reply loops on the reskin tier, the
 * profile's Replies Created tab, and the search query.
 *
 * The widening itself is `ContextInterface::own_pending_where_clause()`, which
 * OR-s one fully-bound predicate beside the clause WordPress already built rather
 * than editing it. Its docblock carries the measurement that ruled out the obvious
 * alternative — naming `pending` in `post_status` is a disclosure on bbPress 2.6.14,
 * not a theoretical one — and the guards. The property worth repeating here: this
 * can only ever *add* one author's own held replies in one thread, so getting it
 * wrong loses a row rather than showing a stranger's.
 *
 * ## Registered last on `posts_where`, deliberately
 *
 * `own_pending_where_clause()` *rewrites* the clause rather than appending to it,
 * exactly so a filter that runs after us still narrows both sides of the widening
 * instead of only the half we added. Running at `PHP_INT_MAX` means fewer filters
 * run after us at all — belt as well as braces, on the one filter in this plugin
 * whose interaction with another plugin's is a disclosure question rather than a
 * rendering one. It costs nothing: nothing downstream reads what we return except
 * WP_Query itself.
 *
 * ## What is deliberately not covered
 *
 * - **An anonymous reply.** It carries `post_author = 0` and its identity in post
 *   meta, so there is no author to match — and matching zero would hand every
 *   anonymous held reply to every logged-out visitor. The author gets the
 *   acknowledgement message and no row; a documented carve-out, not a gap (§3
 *   decision 6).
 * - **Spam.** One status only. A spammed reply is never shown back, which is the
 *   same decision as never naming spam in the message: it is a free tuning oracle.
 * - **A moderator's view of other people's held replies.** Unchanged, and bbPress
 *   already answers it — `view=all` on the URL puts `pending` in the status list
 *   (`bbp_get_topic_statuses()`), which `get_reply_parents()` mirrors. This class
 *   is author-pinned, so a moderator browsing normally sees exactly what they saw
 *   in 0.4.x.
 * - **A held *topic*.** Decision 6 is about replies. A thread that never appears is
 *   a different failure with a different fix, and PR 5 does not guess at it.
 *
 * @since 0.5.0
 */
class PendingVisibility {

	/**
	 * The query variable the marker rides on, between the args builder that sets it
	 * and the `posts_where` filter that reads it back.
	 *
	 * @var string
	 */
	private const MARKER = 'bltn_own_pending';

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
	 * The marker for one topic, or an empty array when there is nobody to widen for.
	 *
	 * Empty is the answer for a logged-out visitor, and that single test is also the
	 * anonymous carve-out: `post_author = 0` is what an anonymous reply carries, so a
	 * visitor with no ID must arm nothing rather than arm a zero.
	 *
	 * @since 0.5.0
	 *
	 * @param int   $topic_id Thread being read.
	 * @param int[] $ids      Page slice, for a query scoped by `post__in` alone.
	 * @return array<string,mixed>
	 */
	public function marker( int $topic_id, array $ids = array() ): array {
		$author = $this->wp->get_current_user_id();

		if ( $author < 1 || $topic_id < 1 ) {
			return array();
		}

		return array(
			self::MARKER => array(
				'author' => $author,
				'topic'  => $topic_id,
				'ids'    => array_values( array_map( 'intval', $ids ) ),
			),
		);
	}

	/**
	 * Arm a set of reply-query arguments.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string,mixed> $args     Arguments to arm.
	 * @param int                 $topic_id Thread being read.
	 * @param int[]               $ids      Page slice, where `post__in` is the scope.
	 * @return array<string,mixed>
	 */
	public function arm( array $args, int $topic_id, array $ids = array() ): array {
		return array_merge( $args, $this->marker( $topic_id, $ids ) );
	}

	/**
	 * Widen a query that carries the marker. Hooked on `posts_where`.
	 *
	 * Every other query on the request returns untouched, and there is no screen
	 * tier or request condition keeping that true — a query either carries a marker
	 * this class put there or it does not.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $where The WHERE clause built so far.
	 * @param mixed $query The query it belongs to.
	 * @return mixed
	 */
	public function widen( $where, $query = null ) {
		$marker = $this->wp->get_query_arg( $query, self::MARKER );

		if ( ! is_string( $where ) || ! is_array( $marker ) ) {
			return $where;
		}

		return $this->wp->own_pending_where_clause(
			$where,
			$this->wp->get_reply_post_type(),
			$this->wp->get_pending_status_id(),
			isset( $marker['author'] ) ? (int) $marker['author'] : 0,
			isset( $marker['topic'] ) ? (int) $marker['topic'] : 0,
			isset( $marker['ids'] ) && is_array( $marker['ids'] ) ? array_map( 'intval', $marker['ids'] ) : array()
		);
	}
}
