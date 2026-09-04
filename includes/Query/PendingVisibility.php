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
 * Adds only the current reader's pending replies in the current thread.
 * Both the page query and threaded-order query must carry the marker or pagination
 * drifts. Naming `pending` in `post_status` would disclose every held reply.
 *
 * @since 0.5.0
 */
class PendingVisibility {

	private const MARKER = 'bltn_own_pending';

	private ContextInterface $wp;

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
