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
 * Adds an ID tiebreak to bbPress topic and reply queries on reskin screens.
 * LIMIT/OFFSET over tied dates is otherwise nondeterministic, duplicating or omitting
 * rows across pages. Other screens retain their owners' ordering.
 *
 * @since 0.3.0
 */
class StableOrder {

	private ScreenClassifier $screen;

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
	 * Append `ID` without replacing the caller's sort key or direction.
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

		// WP_Query treats an empty orderby as `post_date`, but drops that key when an
		// ID tiebreak is appended. Name the default so ID does not replace it.
		if ( '' === $orderby ) {
			$orderby = 'date';
		}

		// Search relevance and random order cannot accept a deterministic tiebreak.
		if ( ! empty( $args['s'] ) || ! $this->is_tiebreakable( $orderby ) ) {
			return $args;
		}

		$args['orderby'] = is_array( $orderby )
			? $this->with_id_appended( $orderby, $order )
			: array(
				(string) $orderby => $order,
				'ID'              => $order,
			);

		return $args;
	}

	/**
	 * Append `ID` to an orderby that is already keyed.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $orderby A keyed orderby, known non-empty.
	 * @param string              $order   Normalised fallback direction.
	 * @return array<string,mixed>
	 */
	private function with_id_appended( array $orderby, string $order ): array {
		if ( isset( $orderby['ID'] ) ) {
			return $orderby;
		}

		$last          = end( $orderby );
		$orderby['ID'] = $this->direction( is_string( $last ) ? $last : $order );

		return $orderby;
	}

	/**
	 * Whether appending a key to this `orderby` would settle ties rather than
	 * change the result.
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
		if ( ! is_string( $orderby ) ) {
			return false;
		}
		if ( in_array( strtolower( $orderby ), array( 'rand', 'none' ), true ) ) {
			return false;
		}

		return ! str_contains( trim( $orderby ), ' ' );
	}

	/**
	 * Normalise a sort direction using WP_Query's `DESC` fallback.
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
