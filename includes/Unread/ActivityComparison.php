<?php
/**
 * The comparison that decides whether a topic has moved.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Unread;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * One SQL fragment, shared by the two queries that answer the unread accent.
 *
 * The topic list decides a thread's accent; the forum roll-up decides its parent's. If
 * the two compared differently, a forum could light while every thread inside it read
 * as clear — so the comparison is written once and both ask for it here.
 *
 * The fallbacks are the same ones the write path applies: a topic bbPress never
 * stamped compares against its own post date and ID, so it reads as caught up rather
 * than as permanently unread.
 *
 * @since 0.6.0
 */
class ActivityComparison {

	/**
	 * Whether the topic at `$alias` has moved past the reader's stored position.
	 *
	 * ⚠ Assumes the caller's query joins last-active time as `m`, last-active ID as
	 * `i`, and the reads table as `r`. Both callers are in this namespace and build
	 * those joins immediately above the call.
	 *
	 * @since 0.6.0
	 *
	 * @param string $alias Posts-table alias holding the topic.
	 * @return string
	 */
	public function moved_past( string $alias ): string {
		$time = "COALESCE( NULLIF( m.meta_value, '' ), {$alias}.post_date )";
		$id   = "CAST( COALESCE( NULLIF( i.meta_value, '' ), {$alias}.ID ) AS UNSIGNED )";

		return "( r.read_time IS NULL OR {$time} > r.read_time OR ( {$time} = r.read_time AND {$id} > r.read_id ) )";
	}
}
