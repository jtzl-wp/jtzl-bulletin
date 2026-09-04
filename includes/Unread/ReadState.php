<?php
/**
 * Per-reader read state.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Unread;

use JTZL\Bulletin\Database\Schema;
use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Answers "has this member read this?" for topics and forums, and records that they
 * have. Everything the unread accent knows comes from here.
 *
 * @since 0.5.0
 */
class ReadState {

	private \wpdb $wpdb;

	private Schema $schema;

	private ContextInterface $wp;

	private ForumTree $tree;

	private ActivityComparison $comparison;

	public function __construct( \wpdb $wpdb, Schema $schema, ContextInterface $wp, ForumTree $tree, ActivityComparison $comparison ) {
		$this->wpdb       = $wpdb;
		$this->schema     = $schema;
		$this->wp         = $wp;
		$this->tree       = $tree;
		$this->comparison = $comparison;
	}

	/**
	 * Which of these topics are unread for this member.
	 *
	 * @since 0.5.0
	 *
	 * @param array<int,int> $topic_ids Topic IDs.
	 * @param int            $user_id   Member ID; 0 for logged out.
	 * @return array<int,bool> Keyed by topic ID. Every input ID is present.
	 */
	public function unread_topics( array $topic_ids, int $user_id ): array {
		$topic_ids = $this->clean_ids( $topic_ids );
		$answer    = array_fill_keys( $topic_ids, false );

		if ( $user_id <= 0 || array() === $topic_ids ) {
			return $answer;
		}

		$reads        = $this->schema->topic_reads_table();
		$placeholders = implode( ',', array_fill( 0, count( $topic_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT p.ID
				   FROM {$this->wpdb->posts} p
			  LEFT JOIN {$this->wpdb->postmeta} m
				     ON m.post_id = p.ID AND m.meta_key = '_bbp_last_active_time'
			  LEFT JOIN {$this->wpdb->postmeta} i
				     ON i.post_id = p.ID AND i.meta_key = '_bbp_last_active_id'
			  LEFT JOIN {$reads} r
				     ON r.topic_id = p.ID AND r.user_id = %d
				  WHERE p.ID IN ( {$placeholders} )
				    AND {$this->comparison->moved_past( 'p' )}",
				array_merge( array( $user_id ), $topic_ids )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $rows as $id ) {
			$answer[ (int) $id ] = true;
		}

		return $answer;
	}

	/**
	 * Which of these forums hold something unread for this member.
	 *
	 * Intersect API branches with readable forums to avoid disclosing protected activity.
	 * A null allowlist preserves the website callers' existing behavior.
	 *
	 * @since 0.5.0
	 *
	 * @param array<int,int>      $forum_ids         Forum IDs.
	 * @param int                 $user_id           Member ID; 0 for logged out.
	 * @param array<int,int>|null $allowed_forum_ids Forums the caller will admit, or
	 *                                               null for no restriction.
	 * @return array<int,bool> Keyed by forum ID. Every input ID is present.
	 */
	public function unread_forums( array $forum_ids, int $user_id, ?array $allowed_forum_ids = null ): array {
		$forum_ids = $this->clean_ids( $forum_ids );
		$answer    = array_fill_keys( $forum_ids, false );

		if ( $user_id <= 0 || array() === $forum_ids ) {
			return $answer;
		}

		$descendants = $this->tree->descendants_of( $forum_ids );
		$scope       = $this->clean_ids( array_merge( $forum_ids, ...array_values( $descendants ) ) );

		if ( null !== $allowed_forum_ids ) {
			$allowed = array_flip( $this->clean_ids( $allowed_forum_ids ) );
			$scope   = array_values( array_filter( $scope, static fn( int $id ): bool => isset( $allowed[ $id ] ) ) );
		}

		// forums_holding_unread() states an unempty precondition and would build
		// `IN ( )` without it. A scope filtered down to nothing is an ordinary answer
		// — a reader who may open none of these forums — not a caller's mistake.
		if ( array() === $scope ) {
			return $answer;
		}

		$with_unread = $this->forums_holding_unread( $scope, $user_id );

		foreach ( $forum_ids as $forum_id ) {
			$branch = array_merge( array( $forum_id ), $descendants[ $forum_id ] ?? array() );

			foreach ( $branch as $id ) {
				if ( isset( $with_unread[ $id ] ) ) {
					$answer[ $forum_id ] = true;
					break;
				}
			}
		}

		return $answer;
	}

	/**
	 * Record that a member has read a topic as far as the topic had got.
	 *
	 * @since 0.5.0
	 *
	 * @param int    $topic_id  Topic ID.
	 * @param int    $user_id   Member ID.
	 * @param string $read_time MySQL datetime the topic was last active.
	 * @param int    $read_id   ID of the post that activity was; 0 only for a legacy row.
	 */
	public function mark_topic_read( int $topic_id, int $user_id, string $read_time, int $read_id ): void {
		if ( $topic_id <= 0 || $user_id <= 0 || '' === $read_time || $read_id < 0 ) {
			return;
		}

		$reads = $this->schema->topic_reads_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		//
		// The primary key already states one row per member per topic, so the database
		// can enforce it in one statement and two concurrent requests cannot race into
		// a duplicate.
		//
		// read_id is assigned before read_time. Assignments in ON DUPLICATE KEY
		// UPDATE run in order and see each other's results, so comparing against
		// read_time after GREATEST() had already advanced it would compare the new
		// value against itself and drop the tiebreak.
		$this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT INTO {$reads} ( user_id, topic_id, read_time, read_id )
				 VALUES ( %d, %d, %s, %d )
				 ON DUPLICATE KEY UPDATE
				   read_id = IF(
				     VALUES( read_time ) > read_time
				       OR ( VALUES( read_time ) = read_time AND VALUES( read_id ) > read_id ),
				     VALUES( read_id ),
				     read_id
				   ),
				   read_time = GREATEST( read_time, VALUES( read_time ) )",
				$user_id,
				$topic_id,
				$read_time,
				$read_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Drop every row about a topic that no longer exists.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Topic ID.
	 */
	public function forget_topic( int $topic_id ): void {
		if ( $topic_id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $this->schema->topic_reads_table(), array( 'topic_id' => $topic_id ), array( '%d' ) );
	}

	/**
	 * Drop every row belonging to a member who no longer exists.
	 *
	 * @since 0.5.0
	 *
	 * @param int $user_id Member ID.
	 */
	public function forget_user( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $this->schema->topic_reads_table(), array( 'user_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * Which of these forums hold at least one unread topic of their own.
	 *
	 * `$forum_ids` must not be empty; `unread_forums()` enforces this before SQL builds
	 * the `IN` clause.
	 *
	 * @param array<int,int> $forum_ids Forum IDs, already cleaned, never empty.
	 * @param int            $user_id   Member ID.
	 * @return array<int,bool> Keyed by forum ID; present only when it holds unread.
	 */
	private function forums_holding_unread( array $forum_ids, int $user_id ): array {
		$reads        = $this->schema->topic_reads_table();
		$placeholders = implode( ',', array_fill( 0, count( $forum_ids ), '%d' ) );
		// Not the two public statuses: bbPress lets a moderator see `private` topics
		// and a keymaster `hidden` ones, and hardcoding publish+closed means a forum
		// whose only unread topic is private shows no dot to the very reader who can
		// open it — while the thread list inside it does show one, because that list
		// resolves whatever the loop already decided they may see. The seam mirrors
		// bbPress's own assembly of this list.
		$statuses     = $this->wp->get_readable_topic_statuses();
		$status_slots = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT t.post_parent
				   FROM {$this->wpdb->posts} t
			  LEFT JOIN {$this->wpdb->postmeta} m
				     ON m.post_id = t.ID AND m.meta_key = '_bbp_last_active_time'
			  LEFT JOIN {$this->wpdb->postmeta} i
				     ON i.post_id = t.ID AND i.meta_key = '_bbp_last_active_id'
			  LEFT JOIN {$reads} r
				     ON r.topic_id = t.ID AND r.user_id = %d
				  WHERE t.post_type = %s
				    AND t.post_status IN ( {$status_slots} )
				    AND t.post_password = ''
				    AND t.post_parent IN ( {$placeholders} )
				    AND {$this->comparison->moved_past( 't' )}",
				array_merge( array( $user_id, $this->wp->get_topic_post_type() ), $statuses, $forum_ids )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_fill_keys( array_map( 'intval', $rows ), true );
	}

	/**
	 * Positive, unique, integer IDs, reindexed.
	 *
	 * @since 0.5.0
	 *
	 * @param array<int,int> $ids Raw IDs.
	 * @return array<int,int>
	 */
	private function clean_ids( array $ids ): array {
		$clean = array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => $id > 0 );

		return array_values( array_unique( $clean ) );
	}
}
