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
 * **A topic is unread when the member has no row for it, or when the topic has moved
 * since the row was written** — bbPress's `(_bbp_last_active_time, _bbp_last_active_id)`
 * past the stored `(read_time, read_id)`. The ID is the tiebreak, and it is not
 * decoration: last-active is stamped to the second, so without it a second reply
 * inside one second is "not newer than" what was read and the thread stays quietly
 * clear. A forum is unread when any topic inside it, or inside any forum beneath it,
 * is.
 *
 * Two properties are load-bearing and both are about the same thing — this runs on
 * every list a reader sees:
 *
 * - **Every lookup is batched.** `unread_topics()` and `unread_forums()` take the
 *   whole set of IDs a screen is about to render and answer in a fixed number of
 *   queries, never one per row. The SoW promises the plugin "adds no theme weight to
 *   forum pages"; a per-row lookup is how that promise gets broken quietly, fifteen
 *   rows at a time.
 * - **There is exactly one resolver.** The page render and the load-more endpoints
 *   both reach the accent through this class, because both render their rows through
 *   View\ThreadList and View\ForumList, which call it. CLAUDE.md's reply-pagination
 *   trap is what happens when a page and its continuation answer the same question
 *   through two code paths.
 *
 * No REST assumptions anywhere in here: P5 exposes this class, it does not
 * reimplement it. Both shapes the app team asked about fall out of per-topic rows —
 * we return a flag, or they send a timestamp and we upsert it.
 *
 * @since 0.5.0
 */
class ReadState {

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 * @since 0.5.0
	 */
	private \wpdb $wpdb;

	/**
	 * Schema definition, for the table name.
	 *
	 * @var Schema
	 * @since 0.5.0
	 */
	private Schema $schema;

	/**
	 * WordPress/bbPress seam, for bbPress's post types and statuses.
	 *
	 * @var ContextInterface
	 * @since 0.5.0
	 */
	private ContextInterface $wp;

	/**
	 * The forum hierarchy, for the roll-up.
	 *
	 * @var ForumTree
	 * @since 0.5.0
	 */
	private ForumTree $tree;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param \wpdb            $wpdb   WordPress database handle.
	 * @param Schema           $schema Schema definition.
	 * @param ContextInterface $wp     WordPress/bbPress seam.
	 * @param ForumTree        $tree   The forum hierarchy.
	 */
	public function __construct( \wpdb $wpdb, Schema $schema, ContextInterface $wp, ForumTree $tree ) {
		$this->wpdb   = $wpdb;
		$this->schema = $schema;
		$this->wp     = $wp;
		$this->tree   = $tree;
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
				    AND (
				          r.read_time IS NULL
				          OR COALESCE( NULLIF( m.meta_value, '' ), p.post_date ) > r.read_time
				          OR (
				               COALESCE( NULLIF( m.meta_value, '' ), p.post_date ) = r.read_time
				               AND CAST( COALESCE( NULLIF( i.meta_value, '' ), p.ID ) AS UNSIGNED ) > r.read_id
				             )
				        )",
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
	 * Answered over the forum and everything beneath it, because the count beside it
	 * is: View\ForumList's row shows `bbp_get_forum_topic_count()`, which includes
	 * sub-forum topics. A dot that ignored sub-forums would contradict the number it
	 * sits next to on a category — and a category, which holds no topics of its own,
	 * would never light up at all.
	 *
	 * @since 0.5.0
	 *
	 * @param array<int,int> $forum_ids Forum IDs.
	 * @param int            $user_id   Member ID; 0 for logged out.
	 * @return array<int,bool> Keyed by forum ID. Every input ID is present.
	 */
	public function unread_forums( array $forum_ids, int $user_id ): array {
		$forum_ids = $this->clean_ids( $forum_ids );
		$answer    = array_fill_keys( $forum_ids, false );

		if ( $user_id <= 0 || array() === $forum_ids ) {
			return $answer;
		}

		$descendants = $this->tree->descendants_of( $forum_ids );
		$scope       = $this->clean_ids( array_merge( $forum_ids, ...array_values( $descendants ) ) );
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
	 * Stamped with the topic's own last-active position rather than "now", so a reply
	 * that lands while the page is open is still unread on the next visit instead of
	 * being swallowed by the act of having been on the screen when it arrived.
	 *
	 * **The position can only move forward, and the statement is what enforces it.**
	 * Two requests can carry two positions and arrive in either order — a phone that
	 * retried on a flaky connection sends the older one second — so the comparison
	 * belongs where the write happens rather than in a check before it. A position
	 * that is not ahead of the stored one performs a harmless no-op update.
	 *
	 * ⚠ There is deliberately no read-before-write. The version of this that skipped
	 * the write when the stored timestamp matched could not survive the ID: a second
	 * reply in the same second leaves the timestamp identical and the ID higher, which
	 * is exactly the case the skip declined to write. One statement is also cheaper
	 * than the lookup it replaced, which matters — this is the one place the plugin
	 * writes during a GET.
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
		// ⚠ read_id is assigned before read_time. Assignments in ON DUPLICATE KEY
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
	 * Sub-forums are not rolled up here — that is unread_forums()' job, and doing it
	 * in SQL would mean either a recursive CTE (MySQL 8 only; bbPress supports 5.6)
	 * or one query per level.
	 *
	 * @since 0.5.0
	 *
	 * ⚠ **$forum_ids must not be empty.** unread_forums() is the only caller and
	 * guarantees it — it returns before reaching here when the list is empty, and what
	 * it passes always contains the forums it was asked about. The guard that used to
	 * stand here could therefore never fire, and an unreachable branch is a worse
	 * protection than a stated precondition: it reads as covering a case that has been
	 * thought about. A second caller passing an empty array would build `IN ( )` and
	 * fail loudly, which is the right outcome for a caller that ignored this.
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
				    AND (
				          r.read_time IS NULL
				          OR COALESCE( NULLIF( m.meta_value, '' ), t.post_date ) > r.read_time
				          OR (
				               COALESCE( NULLIF( m.meta_value, '' ), t.post_date ) = r.read_time
				               AND CAST( COALESCE( NULLIF( i.meta_value, '' ), t.ID ) AS UNSIGNED ) > r.read_id
				             )
				        )",
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
