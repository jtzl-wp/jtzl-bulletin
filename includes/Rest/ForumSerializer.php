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
 *
 * Two properties are load-bearing and both are about a whole page at once:
 *
 * - **Counts are reader-visible.** Not bbPress's stored counters, which count the
 *   site's content rather than this reader's — see RestContextInterface::forum_counts.
 * - **Unread is scoped to the same forums.** `ReadState` already refuses a topic with
 *   a password of its own; the scope handed to it here additionally removes a branch
 *   hidden behind a password on a forum. Without it a visible parent lights up for
 *   activity in a branch the reader cannot reach, and the dot is the disclosure.
 *
 * Both are primed once per page. A per-row count or a per-row unread lookup is how a
 * forum index with fifty rows becomes a hundred queries.
 *
 * @since 0.6.0
 */
class ForumSerializer {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Per-reader read state.
	 *
	 * @var ReadState
	 * @since 0.6.0
	 */
	private ReadState $reads;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp    WordPress/bbPress seam.
	 * @param RestContextInterface $rest  REST seam.
	 * @param ReadState            $reads Per-reader read state.
	 */
	public function __construct( ContextInterface $wp, RestContextInterface $rest, ReadState $reads ) {
		$this->wp    = $wp;
		$this->rest  = $rest;
		$this->reads = $reads;
	}

	/**
	 * A page of forums.
	 *
	 * @since 0.6.0
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
	 * One forum.
	 *
	 * @since 0.6.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return array<string,mixed>
	 */
	public function forum( int $forum_id ): array {
		$rows = $this->forums( array( $forum_id ) );

		return $rows[0];
	}

	/**
	 * The row itself.
	 *
	 * @since 0.6.0
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
	 * Unread state for the page, or nulls for a reader with none.
	 *
	 * @since 0.6.0
	 *
	 * @param int[] $forum_ids Forums.
	 * @return array<int,bool|null>
	 */
	private function unread( array $forum_ids ): array {
		$user_id = $this->wp->get_current_user_id();

		if ( $user_id < 1 ) {
			// null, not false: the field's contract is "unknown for a stranger", and
			// false would tell the app there is nothing new when nobody has asked.
			return array_fill_keys( $forum_ids, null );
		}

		return $this->reads->unread_forums( $forum_ids, $user_id, $this->rest->readable_forum_ids() );
	}
}
