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
	 * Reading order per topic, for this request.
	 *
	 * Both args() and max_pages() need it, and the reading view calls them on the
	 * same topic in the same request — so without this the one unbounded query
	 * would run twice per screen.
	 *
	 * @var array<int,int[]>
	 */
	private array $ordered = array();

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp    WordPress/bbPress seam.
	 * @param ReplyOrder       $order Reply-tree flattener.
	 */
	public function __construct( ContextInterface $wp, ReplyOrder $order ) {
		$this->wp    = $wp;
		$this->order = $order;
	}

	/**
	 * Reply-query args for a topic and 1-based page.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic to load replies for.
	 * @param int $page     1-based page number.
	 * @return array<string,mixed>
	 */
	public function args( int $topic_id, int $page ): array {
		$page = max( 1, $page );

		// Spelled out per branch rather than merged onto a shared base: `+` keeps the
		// LEFT operand's value for a duplicate key, so a shared base carrying
		// `orderby` silently won the override and the tree order never reached
		// WP_Query at all. Caught because the ordering tests make the tree and the
		// clock disagree; an assertion that held under either would have missed it.
		if ( ! $this->wp->is_thread_replies_active() ) {
			return array(
				'post_parent'    => $topic_id,
				'post_type'      => $this->wp->get_reply_post_type(),
				'hierarchical'   => false,
				'posts_per_page' => $this->wp->get_replies_per_page(),
				'paged'          => $page,
				'orderby'        => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
			);
		}

		$per_page = max( 1, $this->wp->get_replies_per_page() );
		$slice    = array_slice( $this->reading_order( $topic_id ), ( $page - 1 ) * $per_page, $per_page );

		return array(
			// An empty post__in is not "match nothing" to WP_Query — it drops the
			// constraint entirely and returns the whole post type. A page past the
			// end has to match nothing, so it names an ID that cannot exist.
			'post__in'       => array() === $slice ? array( 0 ) : $slice,
			'post_type'      => $this->wp->get_reply_post_type(),
			'hierarchical'   => false,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $slice ) > 0 ? count( $slice ) : 1,
			'paged'          => 1,
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

		return (int) ceil( count( $this->reading_order( $topic_id ) ) / $per_page );
	}

	/**
	 * The topic's replies in reading order, computed once per request.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic to read.
	 * @return int[]
	 */
	private function reading_order( int $topic_id ): array {
		if ( ! isset( $this->ordered[ $topic_id ] ) ) {
			$this->ordered[ $topic_id ] = $this->order->flatten( $this->wp->get_reply_parents( $topic_id ) );
		}

		return $this->ordered[ $topic_id ];
	}
}
