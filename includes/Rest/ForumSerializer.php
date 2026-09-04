<?php
/**
 * Forums, as the app sees them.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Unread\ReadState;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * One forum's row, and the batching that keeps a page of them cheap.
 */
class ForumSerializer {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private ReadState $reads;

	public function __construct( ContextInterface $wp, RestContextInterface $rest, ReadState $reads ) {
		$this->wp    = $wp;
		$this->rest  = $rest;
		$this->reads = $reads;
	}

	/**
	 * Data contract.
	 *
	 * @param int[] $forum_ids Forums, in the order they should appear.
	 * @return array<int,array<string,mixed>>
	 */
	public function forums( array $forum_ids ): array {
		$forum_ids = array_values( array_map( 'intval', $forum_ids ) );
		$counts    = $this->rest->forum_counts( $forum_ids, $this->wp->get_readable_topic_statuses() );
		$unread    = $this->unread( $forum_ids );

		$rows = array();
		foreach ( $forum_ids as $forum_id ) {
			$rows[] = $this->forum_data( $forum_id, $counts[ $forum_id ] ?? array(), $unread[ $forum_id ] ?? null );
		}

		return $rows;
	}

	/**
	 * Data contract.
	 *
	 * @param int $forum_id Forum ID.
	 * @return array<string,mixed>
	 */
	public function forum( int $forum_id ): array {
		$rows = $this->forums( array( $forum_id ) );

		return $rows[0];
	}

	/**
	 * Data contract.
	 *
	 * @param int               $forum_id Forum ID.
	 * @param array<string,int> $counts   Reader-visible counts.
	 * @param bool|null         $unread   Unread state, or null when logged out.
	 * @return array<string,mixed>
	 */
	private function forum_data( int $forum_id, array $counts, ?bool $unread ): array {
		return array(
			'id'             => $forum_id,
			'title'          => $this->wp->get_forum_title( $forum_id ),
			'description'    => $this->wp->get_forum_description( $forum_id ),
			'parent'         => $this->wp->get_forum_parent_id( $forum_id ),
			'is_category'    => $this->wp->is_forum_category( $forum_id ),
			'subforum_count' => (int) ( $counts['subforums'] ?? 0 ),
			'topic_count'    => (int) ( $counts['topics'] ?? 0 ),
			'reply_count'    => (int) ( $counts['replies'] ?? 0 ),
			'last_active'    => $this->rest->last_active_rfc3339( $forum_id ),
			'link'           => $this->wp->get_forum_permalink( $forum_id ),
			'is_unread'      => $unread,
		);
	}

	/**
	 * Data contract.
	 *
	 * @param int[] $forum_ids Forums.
	 * @return array<int,bool|null>
	 */
	private function unread( array $forum_ids ): array {
		$user_id = $this->wp->get_current_user_id();

		if ( $user_id < 1 ) {

			return array_fill_keys( $forum_ids, null );
		}

		return $this->reads->unread_forums( $forum_ids, $user_id, $this->rest->readable_forum_ids() );
	}
}
