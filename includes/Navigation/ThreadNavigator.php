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
 * @since 0.1.0
 */
class ThreadNavigator {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Locate a topic within its forum's freshness order.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Current topic.
	 * @param int $forum_id Its forum.
	 * @return array{prev_url:string,next_url:string,position:int,total:int,pinned:bool}
	 */
	public function locate( int $topic_id, int $forum_id ): array {
		$result = array(
			'prev_url' => '',
			'next_url' => '',
			'position' => 0,
			'total'    => 0,
			'pinned'   => false,
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

		$result['total']  = $rank['total'];
		$result['pinned'] = $this->is_pinned( $topic_id, $forum_id );

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

	/**
	 * Whether this thread is one the forum screen pins above the freshness order.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Current topic.
	 * @param int $forum_id Its forum.
	 * @return bool
	 */
	private function is_pinned( int $topic_id, int $forum_id ): bool {
		return in_array( $topic_id, $this->wp->get_sticky_topic_ids( $forum_id ), true );
	}
}
