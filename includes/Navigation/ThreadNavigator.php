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
 * global, not forum-scoped), this is custom. Every topic in the current forum is
 * ordered by freshness; stepping moves one whole thread at a time and stops hard
 * at the forum's first and last thread — no silent wrap.
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
	 * Ordered topic IDs for a forum, freshest first.
	 *
	 * Cached in a transient keyed on the forum's last-active time AND its topic
	 * count: a new post bumps the former, while trashing/deleting/spamming a topic
	 * bumps the latter (bbPress recounts on topic status transitions). Keying on
	 * both means a removed topic drops out of Prev/Next promptly, rather than
	 * lingering as a dead link for up to the cache lifetime.
	 *
	 * @since 0.1.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return int[]
	 */
	public function topic_order( int $forum_id ): array {
		if ( $forum_id <= 0 ) {
			return array();
		}

		$stamp = $this->wp->get_post_meta_value( $forum_id, '_bbp_last_active_time' );
		$count = $this->wp->get_post_meta_value( $forum_id, '_bbp_topic_count' );
		$key   = 'bltn_nav_' . $forum_id . '_' . md5( $stamp . '|' . $count );

		$cached = $this->wp->get_transient( $key );
		if ( is_array( $cached ) ) {
			return array_map( 'intval', $cached );
		}

		$ids = $this->wp->get_forum_topic_ids(
			$forum_id,
			array(
				$this->wp->get_public_status_id(),
				$this->wp->get_closed_status_id(),
			)
		);

		$this->wp->set_transient( $key, $ids, HOUR_IN_SECONDS );

		return $ids;
	}

	/**
	 * Locate a topic within its forum's freshness order.
	 *
	 * Returns the adjacent thread URLs (empty string at a boundary) and the
	 * 1-based position (0 when the topic isn't found in the order).
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Current topic.
	 * @param int $forum_id Its forum.
	 * @return array{prev_url:string,next_url:string,position:int,total:int}
	 */
	public function locate( int $topic_id, int $forum_id ): array {
		$order = $this->topic_order( $forum_id );
		$total = count( $order );
		$result = array(
			'prev_url' => '',
			'next_url' => '',
			'position' => 0,
			'total'    => $total,
		);

		$pos = array_search( $topic_id, $order, true );
		if ( false === $pos ) {
			return $result;
		}

		if ( $pos > 0 ) {
			$result['prev_url'] = $this->wp->get_topic_permalink( $order[ $pos - 1 ] );
		}
		if ( $pos < $total - 1 ) {
			$result['next_url'] = $this->wp->get_topic_permalink( $order[ $pos + 1 ] );
		}
		$result['position'] = $pos + 1;

		return $result;
	}
}
