<?php
/**
 * Which posts a search may return to the reader running it.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Restores the reader-specific status list that bbPress's mixed post-type search
 * normalizer replaces. Widening preserves readable forum rows; the WHERE clauses
 * then restrict topic/reply statuses and prevent replies exposing unreadable parents.
 *
 * @since 0.3.0
 */
class SearchVisibility {

	private const CAPTURED = 'bltn_search_post_status';

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Take a copy of the statuses bbPress worked out, in the moment before the
	 * query that will discard them is built. Hooked on
	 * `bbp_after_has_search_results_parse_args`.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed search-query arguments.
	 * @return mixed
	 */
	public function capture_statuses( $args ) {
		if ( ! is_array( $args ) || ! isset( $args['post_status'] ) ) {
			return $args;
		}

		$statuses = $this->clean( $args['post_status'] );

		if ( array() === $statuses || $this->is_wildcard( $statuses ) ) {
			return $args;
		}

		$args[ self::CAPTURED ] = $statuses;

		return $args;
	}

	/**
	 * Whether a list is WP_Query's `any` rather than a list of statuses.
	 *
	 * @since 0.3.0
	 *
	 * @param string[] $statuses Cleaned status list.
	 * @return bool
	 */
	private function is_wildcard( array $statuses ): bool {
		return in_array( 'any', $statuses, true );
	}

	/**
	 * Restore statuses after bbPress normalizes forum visibility at priority 4.
	 *
	 * The union retains private or hidden forums the reader may see.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $query The query about to run.
	 */
	public function widen_statuses( $query ): void {
		$captured = $this->captured( $query );

		if ( array() === $captured ) {
			return;
		}

		$current = $this->clean( $this->wp->get_query_arg( $query, 'post_status' ) );

		$this->wp->set_query_arg(
			$query,
			'post_status',
			array_values( array_unique( array_merge( $current, $captured ) ) )
		);
	}

	/**
	 * Restrict widened statuses to readable posts and reply parents.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $where The WHERE clause built so far.
	 * @param mixed $query The query it belongs to.
	 * @return mixed
	 */
	public function restrict_statuses( $where, $query = null ) {
		$captured = $this->captured( $query );

		if ( ! is_string( $where ) || array() === $captured ) {
			return $where;
		}

		$where .= $this->wp->post_status_where_clause( $this->wp->get_forum_post_type(), $captured );
		$where .= $this->wp->reply_parent_where_clause(
			$this->wp->get_reply_post_type(),
			$this->wp->get_topic_post_type(),
			$captured
		);

		return $where;
	}

	/**
	 * Return the status list captured on this search query.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $query The query to read.
	 * @return string[]
	 */
	private function captured( $query ): array {
		return $this->clean( $this->wp->get_query_arg( $query, self::CAPTURED ) );
	}

	/**
	 * Normalize scalar or array post statuses.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $statuses Whatever was found.
	 * @return string[]
	 */
	private function clean( $statuses ): array {
		if ( ! is_array( $statuses ) ) {
			$statuses = array( $statuses );
		}

		$clean = array();

		foreach ( $statuses as $status ) {
			if ( is_string( $status ) && '' !== $status ) {
				$clean[] = $status;
			}
		}

		return array_values( array_unique( $clean ) );
	}
}
