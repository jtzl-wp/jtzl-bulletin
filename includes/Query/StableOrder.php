<?php
/**
 * Deterministic ordering for the loops bbPress queries on our behalf.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Appends an ID tiebreak to the topic and reply queries bbPress builds for a reskin
 * screen, so paging them is deterministic.
 *
 * Upstream orders topics by `_bbp_last_active_time` and replies by post date, each
 * with nothing to break a tie. Rows that share the sort key therefore come back in
 * whatever order the database chooses, and it need not choose the same order twice:
 * `LIMIT`/`OFFSET` then draws page 1 and page 2 from two independently ordered
 * result sets, so some rows appear on both pages while others become unreachable.
 * It is not hypothetical — an import with coarse dates, or two posts inside one
 * second, is enough, and on the dev fixture (every topic sharing one timestamp)
 * roughly a third of the forum was unreachable from the topic archive (issue #45).
 *
 * Bulletin's own queries have always been tiebroken — see TopicQuery and ReplyQuery,
 * which order by `(last-active, ID)` and `(date, ID)`. The three takeover screens
 * were therefore never affected. This carries the same guarantee to the screens
 * where bbPress builds the query and we only restyle the result.
 *
 * Scope is deliberately narrow. Only `ScreenTier::Reskin` is touched: on a takeover
 * screen bbPress's loops aren't what we render, and everywhere else the query
 * belongs to the theme or another plugin, so appending to it would be meddling in
 * someone else's results.
 *
 * @since 0.3.0
 */
class StableOrder {

	/**
	 * Screen-tier classifier.
	 *
	 * @var ScreenClassifier
	 */
	private ScreenClassifier $screen;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ScreenClassifier $screen Screen-tier classifier.
	 */
	public function __construct( ScreenClassifier $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Tiebreak a topics query. Hooked on `bbp_after_has_topics_parse_args`, which
	 * hands over the merged arguments in the moment before bbPress runs the query.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed query arguments.
	 * @return mixed
	 */
	public function filter_topic_args( $args ) {
		return $this->tiebreak( $args );
	}

	/**
	 * Tiebreak a replies query. Same hook, on `bbp_after_has_replies_parse_args`.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed query arguments.
	 * @return mixed
	 */
	public function filter_reply_args( $args ) {
		return $this->tiebreak( $args );
	}

	/**
	 * Append `ID` as the last sort key, preserving whatever bbPress asked for.
	 *
	 * The existing key and direction are kept rather than replaced: this settles
	 * ties, it does not impose an order. So a caller that asked for oldest-first
	 * replies still gets them oldest-first, with ID ascending inside each tie.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed query arguments.
	 * @return mixed The arguments, tiebroken where that is meaningful.
	 */
	private function tiebreak( $args ) {
		if ( ! is_array( $args ) || ScreenTier::Reskin !== $this->screen->tier() ) {
			return $args;
		}

		$orderby = $args['orderby'] ?? '';
		$order   = $this->direction( $args['order'] ?? 'DESC' );

		// A search ranks by relevance, and a random order is a deliberate absence
		// of one: in neither case would appending a key break a tie, it would
		// change which rows rank where. Leave both exactly as bbPress asked.
		if ( ! empty( $args['s'] ) || ! $this->is_tiebreakable( $orderby ) ) {
			return $args;
		}

		if ( is_array( $orderby ) ) {
			// Already keyed: add ID unless the caller sorts on it already, and
			// follow the direction of the key it will be breaking ties within.
			if ( ! isset( $orderby['ID'] ) ) {
				$last            = end( $orderby );
				$orderby['ID']   = $this->direction( is_string( $last ) ? $last : $order );
				$args['orderby'] = $orderby;
			}
			return $args;
		}

		$args['orderby'] = array(
			(string) $orderby => $order,
			'ID'              => $order,
		);

		return $args;
	}

	/**
	 * Whether appending a key to this `orderby` would settle ties rather than
	 * change the result.
	 *
	 * A single key is safe. `rand` and `none` are not orders to break ties within.
	 * A space-separated list ("date ID") is a form WP_Query accepts but cannot be
	 * turned into the keyed array without re-parsing it, so it is left alone —
	 * bbPress never emits one, and a caller who wrote it has said what they want.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $orderby The `orderby` argument.
	 * @return bool
	 */
	private function is_tiebreakable( $orderby ): bool {
		if ( is_array( $orderby ) ) {
			return array() !== $orderby;
		}
		if ( ! is_string( $orderby ) || '' === $orderby ) {
			return false;
		}
		if ( in_array( strtolower( $orderby ), array( 'rand', 'none' ), true ) ) {
			return false;
		}

		return ! str_contains( trim( $orderby ), ' ' );
	}

	/**
	 * Normalise a sort direction, defaulting anything unrecognised to DESC — which
	 * is what WP_Query itself does with a direction it cannot read.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $order The `order` argument.
	 * @return string
	 */
	private function direction( $order ): string {
		return ( is_string( $order ) && 0 === strcasecmp( trim( $order ), 'ASC' ) ) ? 'ASC' : 'DESC';
	}
}
