<?php
/**
 * Canonical reply-query arguments.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Builds the bbp_has_replies() args used by BOTH the initial reading-view render
 * and the load-more AJAX handler, so the two paths paginate identically.
 *
 * Four deliberate choices (the first two are CLAUDE.md trap #4):
 *  - An explicit reply-only post type, rather than leaning on
 *    bbp_show_lead_topic() — that helper only excludes the topic when
 *    bbp_is_single_topic() is also true, which is false in an AJAX request, so
 *    relying on it pulls the topic into the replies loop off-page and shifts
 *    every page boundary by one.
 *  - A (date, ID) order. Ordering by date ALONE is unstable when replies share a
 *    timestamp (coarse-dated imports, two posts in the same second): MySQL
 *    returns tied rows in an undefined order, so LIMIT/OFFSET paging shuffles
 *    rows across page boundaries — duplicating some replies and dropping others.
 *    The ID tiebreak makes paging deterministic.
 *  - An explicit flat query, always. bbp_has_replies() defaults `hierarchical` to
 *    true whenever threading is active on a single topic, and a hierarchical reply
 *    query is not pageable: it forces posts_per_page to -1 and counts its pages
 *    from the number of ROOT replies, loading every descendant of every root in one
 *    request. That is what made the reading view decline a threaded topic in P1
 *    (issue #12). Asking for a flat query is what makes a threaded thread pageable
 *    at all.
 *  - The READING order is ours, and on a threaded forum it is the tree: a reply
 *    belongs under the one it answers (Yoren, 2026-07-28). bbPress cannot give us
 *    that and pagination together, so Query\ReplyOrder computes the order and a
 *    page becomes a slice of it, fetched by post__in. See issue #37.
 *
 * A fifth, added in 0.5.0: both queries carry Query\PendingVisibility's marker, so
 * a reader's own reply held for moderation is in the page AND in the order. Armed
 * from one object precisely because the two must agree — a held reply counted by one
 * and dropped by the other is trap #4 again, with a moderation queue in place of a
 * tied timestamp.
 *
 * On a forum with threading off — bbPress's default, and JT's — none of the last
 * point applies: `parents()` is never called, no unbounded query runs, and the args
 * are the plain paged query they have always been.
 *
 * @since 0.1.0
 */
class ReplyQuery {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Reply-tree flattener.
	 *
	 * @var ReplyOrder
	 */
	private ReplyOrder $order;

	/**
	 * Reading order per topic and reader, for this request.
	 *
	 * Both args() and max_pages() need it, and the reading view calls them on the
	 * same topic in the same request — so without this the one unbounded query
	 * would run twice per screen.
	 *
	 * ⚠ **Keyed by reader as well as topic, not by topic alone.** The order is not the
	 * same for everybody: Query\PendingVisibility puts one author's own held reply
	 * into it, so a memo keyed by topic answers the second reader with the first
	 * reader's order. A request has one reader, so a topic-only key is correct in
	 * production and quietly wrong anywhere the current user changes — a test walking
	 * a matrix of roles, or any future code answering for somebody other than the
	 * caller. The answer depends on who is asking, so the cache does too. (The same
	 * reasoning keys WordPress\RestContext's forum scope; found here by a REST test
	 * that read one thread as two members and was handed the first one's page count.)
	 *
	 * @var array<string,int[]>
	 */
	private array $ordered = array();

	/**
	 * The reader's own held replies, armed onto both queries.
	 *
	 * @var PendingVisibility
	 */
	private PendingVisibility $pending;

	/**
	 * Keeps WordPress's admin status list out of both queries.
	 *
	 * @var ProtectedStatusGuard
	 */
	private ProtectedStatusGuard $guard;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface     $wp      WordPress/bbPress seam.
	 * @param ReplyOrder           $order   Reply-tree flattener.
	 * @param PendingVisibility    $pending The reader's own held replies.
	 * @param ProtectedStatusGuard $guard   Admin-only statuses, kept out.
	 */
	public function __construct( ContextInterface $wp, ReplyOrder $order, PendingVisibility $pending, ProtectedStatusGuard $guard ) {
		$this->wp      = $wp;
		$this->order   = $order;
		$this->pending = $pending;
		$this->guard   = $guard;
	}

	/**
	 * Reply-query args for a topic and 1-based page.
	 *
	 * The page size is the one thing a caller may override, and only the API does:
	 * the website has a single page size — bbPress's `_bbp_replies_per_page` — while
	 * a REST collection is paged by the request, bounded by Rest\RequestBounds long
	 * before it arrives here. Omitting the argument is the website's call and is
	 * byte-identical to what it has always built.
	 *
	 * @since 0.1.0
	 * @since 0.6.0 Optional page size, for the API.
	 *
	 * @param int      $topic_id Topic to load replies for.
	 * @param int      $page     1-based page number.
	 * @param int|null $per_page Rows per page, or null for the website's own.
	 * @return array<string,mixed>
	 */
	public function args( int $topic_id, int $page, ?int $per_page = null ): array {
		$page = max( 1, $page );

		// Spelled out per branch rather than merged onto a shared base: `+` keeps the
		// LEFT operand's value for a duplicate key, so a shared base carrying
		// `orderby` silently won the override and the tree order never reached
		// WP_Query at all. Caught because the ordering tests make the tree and the
		// clock disagree; an assertion that held under either would have missed it.
		if ( ! $this->wp->is_thread_replies_active() ) {
			// No ID list: `post_parent` is this query's whole scope, so it is also the
			// whole scope of the widening.
			return $this->guard->arm(
				$this->pending->arm(
					array(
						'post_parent'    => $topic_id,
						'post_type'      => $this->wp->get_reply_post_type(),
						'hierarchical'   => false,
						'posts_per_page' => $this->page_size( $per_page ),
						'paged'          => $page,
						'orderby'        => array(
							'date' => 'ASC',
							'ID'   => 'ASC',
						),
					),
					$topic_id
				)
			);
		}

		$size  = max( 1, $this->page_size( $per_page ) );
		$slice = array_slice( $this->ordered_ids( $topic_id ), ( $page - 1 ) * $size, $size );

		// An empty post__in is not "match nothing" to WP_Query — it drops the
		// constraint entirely and returns the whole post type. A page past the
		// end has to match nothing, so it names an ID that cannot exist.
		$in = array() === $slice ? array( 0 ) : $slice;

		return $this->guard->arm(
			$this->pending->arm(
				array(
					'post__in'       => $in,
					'post_type'      => $this->wp->get_reply_post_type(),
					'hierarchical'   => false,
					'orderby'        => 'post__in',
					'posts_per_page' => count( $slice ) > 0 ? count( $slice ) : 1,
					'paged'          => 1,
				),
				$topic_id,
				// The slice, not the topic: here `post__in` IS the scope, and a widening
				// that named only the thread would escape the page and repeat the held
				// reply on every one of them. It is already in the order — ordered_ids()
				// asked for it — so this admits it exactly on the page it belongs to.
				$in
			)
		);
	}

	/**
	 * How many pages of replies a topic has.
	 *
	 * Not read off the query object once the order is ours: args() hands WP_Query
	 * exactly one page of IDs, so it reports max_num_pages = 1 whatever the thread's
	 * length, and every "load more" control would vanish after page 1. bbPress's own
	 * count is still right for the unthreaded case, where a page really is a LIMIT
	 * over the whole set.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic to count.
	 * @return int
	 */
	public function max_pages( int $topic_id ): int {
		if ( ! $this->wp->is_thread_replies_active() ) {
			return $this->wp->get_max_reply_pages();
		}

		$per_page = max( 1, $this->wp->get_replies_per_page() );

		return (int) ceil( count( $this->ordered_ids( $topic_id ) ) / $per_page );
	}

	/**
	 * The topic's replies in reading order, computed once per request.
	 *
	 * ⚠ **Public since 0.6.0, and it is the only source of a threaded total.** A
	 * threaded page is a slice fetched by `post__in`, so the query that returns it
	 * reports a `found_posts` of one page however long the thread is — and a REST
	 * collection reading its `X-WP-Total` off that query would tell the app the thread
	 * ends at the page it is holding. The count has to come from the order, which is
	 * also what the page was sliced out of, so the two cannot disagree.
	 *
	 * @since 0.3.0
	 * @since 0.6.0 Public, for REST totals.
	 *
	 * @param int $topic_id Topic to read.
	 * @return int[]
	 */
	public function ordered_ids( int $topic_id ): array {
		$key = $topic_id . ':' . $this->wp->get_current_user_id();

		if ( ! isset( $this->ordered[ $key ] ) ) {
			// The second query site. Armed from the same object as the page query, so
			// the order and the page it slices cannot disagree about a held reply.
			$this->ordered[ $key ] = $this->order->flatten(
				$this->wp->get_reply_parents(
					$topic_id,
					$this->pending->marker( $topic_id ) + $this->guard->marker()
				)
			);
		}

		return $this->ordered[ $key ];
	}

	/**
	 * The page size this call runs with.
	 *
	 * @since 0.6.0
	 *
	 * @param int|null $per_page Requested size, or null for the website's own.
	 * @return int
	 */
	private function page_size( ?int $per_page ): int {
		return null === $per_page ? $this->wp->get_replies_per_page() : max( 1, $per_page );
	}
}
