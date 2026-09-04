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
 * Owns reply paging for both initial and continuation queries.
 * Explicit reply type and `(date, ID)` order keep boundaries deterministic. Threaded
 * pages slice one shared tree order; both page and order queries carry visibility
 * markers so held replies cannot change totals independently of rendered rows.
 *
 * @since 0.1.0
 */
class ReplyQuery {

	private ContextInterface $wp;

	private ReplyOrder $order;

	/**
	 * Reading order per topic and reader, for this request.
	 *
	 * @var array<string,int[]>
	 */
	private array $ordered = array();

	private PendingVisibility $pending;

	private ProtectedStatusGuard $guard;

	public function __construct( ContextInterface $wp, ReplyOrder $order, PendingVisibility $pending, ProtectedStatusGuard $guard ) {
		$this->wp      = $wp;
		$this->order   = $order;
		$this->pending = $pending;
		$this->guard   = $guard;
	}

	/**
	 * Reply-query args for a topic and 1-based page.
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
	 * The whole unthreaded thread, as one unpaged query.
	 *
	 * @since 0.6.1
	 *
	 * @param int $topic_id Thread to enumerate.
	 * @return array<string,mixed>
	 */
	public function flat_order_args( int $topic_id ): array {
		// Overrides on the LEFT: `+` keeps the left operand for a duplicate key, so this
		// replaces the paging `args()` set rather than losing to it — the mistake
		// `args()`'s own comment records making with `orderby`.
		return array(
			'posts_per_page' => -1,
			'nopaging'       => true,
			'paged'          => 1,
			'offset'         => 0,
		) + $this->args( $topic_id, 1 );
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
