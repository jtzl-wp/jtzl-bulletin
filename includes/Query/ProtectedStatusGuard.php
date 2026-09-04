<?php
/**
 * Keep WordPress's admin status list out of a front-end loop.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Excludes admin-only statuses from marked status-less front-end loops.
 * WordPress otherwise admits protected statuses when a query runs in admin context.
 * This filter must run before PendingVisibility, which restores one bounded row.
 *
 * @since 0.5.0
 */
class ProtectedStatusGuard {

	private const MARKER = 'bltn_front_end';

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * The marker, for a caller that merges argument sets itself.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string,mixed>
	 */
	public function marker(): array {
		return array( self::MARKER => true );
	}

	/**
	 * Arm a set of query arguments.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string,mixed> $args Arguments to arm.
	 * @return array<string,mixed>
	 */
	public function arm( array $args ): array {
		return array_merge( $args, $this->marker() );
	}

	/**
	 * Subtract the admin-only statuses from a marked query. Hooked on `posts_where`
	 * early, so `Query\PendingVisibility`'s widening can still put one row back.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $where The WHERE clause built so far.
	 * @param mixed $query The query it belongs to.
	 * @return mixed
	 */
	public function restrict( $where, $query = null ) {
		if ( ! is_string( $where ) || true !== $this->wp->get_query_arg( $query, self::MARKER ) ) {
			return $where;
		}

		// The same guard WordPress's own branch carries: a query that named its
		// statuses never went near the admin list, and narrowing it here would take
		// away a moderator's `view=all` rather than a stranger's disclosure.
		$named = $this->wp->get_query_arg( $query, 'post_status' );

		if ( ! in_array( $named, array( null, '', array() ), true ) ) {
			return $where;
		}

		return $where . $this->wp->status_exclusion_where_clause( $this->wp->get_admin_only_statuses() );
	}
}
