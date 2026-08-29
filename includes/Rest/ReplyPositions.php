<?php
/**
 * Where a reply sits in the thread it belongs to.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\ReplyQuery;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * A reply's 1-based index in this reader's reading order, and the page that holds it.
 *
 * ## What this is for
 *
 * The API could resolve a reply and could not say where it sits, so an app with a
 * permalink had to page a thread until the reply turned up. Two things produce those
 * permalinks today and neither involves push: bbPress emails one to every subscriber
 * of a topic (`bbp_get_reply_url()`, in the `Post Link:` line), and a `read_cursor`
 * says where a member stopped without saying where that is. See issue #142.
 *
 * ⚠ **bbPress cannot answer this, and on a threaded forum it does not try.**
 * `bbp_get_reply_url()` hard-codes `$reply_page = 1` under `bbp_thread_replies()`, so
 * a subscription email already lands a member on page 1 with an anchor three pages
 * away — on the website, today. The position is ours to compute because the *order*
 * is ours to compute: bbPress cannot page a hierarchical query at all, which is why
 * Query\ReplyOrder exists (issue #37).
 *
 * ## Position is per reader, not per reply
 *
 * An author sees their own held reply where nobody else does (Query\PendingVisibility),
 * so the same reply genuinely has a different position for different people — the same
 * reason a thread's `X-WP-Total` can be one higher for its author. Every answer here is
 * therefore read out of the *visibility-filtered* order, never from a raw count of the
 * thread, and the memo below is keyed by reader as well as by topic.
 *
 * ## Two ways to an answer, and the cheap one is not a shortcut
 *
 * | Caller | How | Cost |
 * |---|---|---|
 * | a page of `/topics/{id}/replies` | `for_page()` — offset arithmetic | free |
 * | `/replies/{id}`, a write response, `?around=` | `for_reply()` / `page_of()` | one order build |
 *
 * `for_page()` is exact rather than approximate, and by construction: a page *is* the
 * slice of the reading order at that offset, in both threading modes —
 * `Query\ReplyQuery::args()` takes `array_slice()` of the order when threading is on
 * and a `LIMIT` over the same `(date, ID)` sequence when it is off. Row *i* of page *p*
 * is at index `(p - 1) * per_page + i`, so the collection pays nothing to say so.
 *
 * That is what keeps this off the ordinary reading path. ⚠ It does **add a consumer**
 * to the order computation issue #105 flags, on the singular and `?around=` routes.
 * Deliberately, and it is no worse than the website already is — the reading view
 * rebuilds the same order on every page load — but it ships on the existing
 * computation and inherits whatever #105 later decides, rather than pre-empting it.
 *
 * ## The order is asked for the way the page was
 *
 * Both branches below mirror `Query\ReplyQuery::args()`'s own branch, and that is
 * load-bearing rather than tidy. bbPress keeps `_bbp_reply_to` meta when threading is
 * switched **off**, so flattening the tree regardless would order a flat forum by a
 * hierarchy it does not read in — and a position would then disagree with the page it
 * claims to be on, for every reply anybody had ever answered.
 *
 * @since 0.6.1
 */
class ReplyPositions {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.1
	 */
	private ContextInterface $wp;

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.1
	 */
	private RestContextInterface $rest;

	/**
	 * The forum scope every collection query is narrowed to.
	 *
	 * @var CollectionVisibility
	 * @since 0.6.1
	 */
	private CollectionVisibility $visibility;

	/**
	 * Shared reply-query builder.
	 *
	 * @var ReplyQuery
	 * @since 0.6.1
	 */
	private ReplyQuery $replies;

	/**
	 * Unthreaded reading orders already built, by topic and reader.
	 *
	 * Keyed by reader for the reason the class docblock gives, and for the reason
	 * `Query\ReplyQuery::$ordered` gives at more length: a topic-only key answers the
	 * second reader of a request with the first one's order, which is correct in
	 * production and quietly wrong in any test that walks a matrix of roles.
	 *
	 * The threaded order is not memoized here — `Query\ReplyQuery::ordered_ids()`
	 * already holds it, and a second copy would be a second thing to invalidate.
	 *
	 * @var array<string,int[]>
	 * @since 0.6.1
	 */
	private array $flat = array();

	/**
	 * Constructor.
	 *
	 * @since 0.6.1
	 *
	 * @param ContextInterface     $wp         WordPress/bbPress seam.
	 * @param RestContextInterface $rest       REST seam.
	 * @param CollectionVisibility $visibility Collection scope.
	 * @param ReplyQuery           $replies    Shared reply-query builder.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		CollectionVisibility $visibility,
		ReplyQuery $replies
	) {
		$this->wp         = $wp;
		$this->rest       = $rest;
		$this->visibility = $visibility;
		$this->replies    = $replies;
	}

	/**
	 * The positions of one page's rows, from the offset it was taken at.
	 *
	 * @since 0.6.1
	 *
	 * @param int   $offset    Rows preceding this page: `( page - 1 ) * per_page`.
	 * @param int[] $reply_ids The page, in reading order.
	 * @return array<int,int> Reply ID => 1-based position.
	 */
	public function for_page( int $offset, array $reply_ids ): array {
		$positions = array();
		$rank      = max( 0, $offset );

		foreach ( $reply_ids as $reply_id ) {
			++$rank;
			$positions[ (int) $reply_id ] = $rank;
		}

		return $positions;
	}

	/**
	 * One reply's position, as the map a serializer takes.
	 *
	 * The thread is resolved from the reply rather than passed in, because every caller
	 * — the singular route, a 201, a 200 — is answering *about that reply* and would
	 * only have to look the same thing up. `page_of()` is the deliberate exception.
	 *
	 * ⚠ The entry is present with a `null` value when the position cannot be
	 * determined, never absent: a thread-scoped response says `position` either way, and
	 * a missing key is how the field would silently disappear from a route that
	 * promises it.
	 *
	 * @since 0.6.1
	 *
	 * @param int $reply_id Reply to place.
	 * @return array<int,int|null> Reply ID => 1-based position, or null.
	 */
	public function for_reply( int $reply_id ): array {
		return array( $reply_id => $this->of( $this->wp->get_reply_topic_id( $reply_id ), $reply_id ) );
	}

	/**
	 * Which page of a thread holds a reply, at the size the request asked for.
	 *
	 * ⚠ **The thread is the caller's, not the reply's.** This answers `?around=`, where
	 * the target has to be a reply *in the thread being paged*: resolving its own topic
	 * instead would hand back a page number computed in some other thread, and the app
	 * would page confidently to a page that does not contain what it asked for. A target
	 * outside this thread is simply absent from this thread's order, so it comes back
	 * null with everything else that is not in it.
	 *
	 * @since 0.6.1
	 *
	 * @param int $topic_id Thread being paged.
	 * @param int $reply_id Reply the page must contain.
	 * @param int $per_page Rows per page, already bounded.
	 * @return int|null 1-based page, or null when the reply is not in this reader's thread.
	 */
	public function page_of( int $topic_id, int $reply_id, int $per_page ): ?int {
		$position = $this->of( $topic_id, $reply_id );

		return null === $position ? null : (int) ceil( $position / max( 1, $per_page ) );
	}

	/**
	 * A reply's 1-based index in this reader's reading order for a thread.
	 *
	 * ⚠ **`false` is guarded rather than arithmetic'd.** `array_search()` returns
	 * `false` for a miss and `false + 1` is `1` in PHP, so the unguarded spelling
	 * reports every unplaceable reply as the first post in the thread — the one answer
	 * an app would act on without question. A reply this reader can open but that is
	 * not in the order they are being paged through has no position, and null says so.
	 *
	 * @since 0.6.1
	 *
	 * @param int $topic_id Thread to look in.
	 * @param int $reply_id Reply to place.
	 * @return int|null
	 */
	private function of( int $topic_id, int $reply_id ): ?int {
		if ( $topic_id < 1 || $reply_id < 1 ) {
			return null;
		}

		$index = array_search( $reply_id, $this->order( $topic_id ), true );

		return false === $index ? null : (int) $index + 1;
	}

	/**
	 * Every reply of a thread this reader may have, in the order they read in.
	 *
	 * @since 0.6.1
	 *
	 * @param int $topic_id Thread to enumerate.
	 * @return int[]
	 */
	private function order( int $topic_id ): array {
		if ( $this->wp->is_thread_replies_active() ) {
			return $this->replies->ordered_ids( $topic_id );
		}

		$key = $topic_id . ':' . $this->wp->get_current_user_id();

		if ( ! isset( $this->flat[ $key ] ) ) {
			// Scoped like the page it has to agree with. Rest\AccessPolicy::topic() has
			// already ruled on the parent, so this admits every reply under a thread that
			// got here — but an imported reply whose `post_parent` and `_bbp_forum_id`
			// disagree still fails closed, exactly as it does in the collection.
			$this->flat[ $key ] = $this->rest->query(
				$this->visibility->scope( $this->replies->flat_order_args( $topic_id ) )
			)['ids'];
		}

		return $this->flat[ $key ];
	}
}
