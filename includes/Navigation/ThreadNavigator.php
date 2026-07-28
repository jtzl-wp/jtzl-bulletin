<?php
/**
 * Forum-scoped thread Prev/Next.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Navigation;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Custom forum-scoped adjacent-thread navigation.
 *
 * Because bbPress strips WordPress's chronological adjacent-post links (they're
 * global, not forum-scoped), this is custom. Topics in the current forum are
 * ordered by freshness; stepping moves one whole thread at a time and stops hard
 * at the forum's first and last thread — no silent wrap.
 *
 * Nothing here is cached, and nothing here is proportional to the size of the
 * forum. It used to be both: an ordered array of every topic ID, kept in a
 * transient (issue #59). The array was the cost — reading it back meant pulling
 * the whole thing out of the cache and unserialising it on every page view, only
 * to look at three entries. Asking the database for those three costs less than
 * carrying the rest, so the cache had nothing left to save.
 *
 * @since 0.1.0
 */
class ThreadNavigator {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Locate a topic within its forum's freshness order.
	 *
	 * Returns the adjacent thread URLs (empty string at a boundary) and the
	 * 1-based position (0 when the topic isn't part of the order — trashed,
	 * spammed, or in another forum — while total still reports the forum's).
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Current topic.
	 * @param int $forum_id Its forum.
	 * @return array{prev_url:string,next_url:string,position:int,total:int}
	 */
	public function locate( int $topic_id, int $forum_id ): array {
		$result = array(
			'prev_url' => '',
			'next_url' => '',
			'position' => 0,
			'total'    => 0,
		);

		if ( $topic_id <= 0 || $forum_id <= 0 ) {
			return $result;
		}

		$rank = $this->wp->get_topic_rank(
			$forum_id,
			$topic_id,
			array(
				$this->wp->get_public_status_id(),
				$this->wp->get_closed_status_id(),
			)
		);

		$result['total'] = $rank['total'];

		if ( $rank['position'] < 1 ) {
			return $result;
		}

		$result['position'] = $rank['position'];

		if ( $rank['prev_id'] > 0 ) {
			$result['prev_url'] = $this->wp->get_topic_permalink( $rank['prev_id'] );
		}

		if ( $rank['next_id'] > 0 ) {
			$result['next_url'] = $this->wp->get_topic_permalink( $rank['next_id'] );
		}

		return $result;
	}
}
