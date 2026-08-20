<?php
/**
 * Schema installation and upgrades.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Database;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Brings the database up to the schema this build expects.
 *
 * Two entry points apply the same upgrade, and that redundancy is the point:
 * activation catches the ordinary install, and a check on load catches every way a
 * plugin can arrive at a new version without its activation hook running — an
 * unzip-over-the-directory update, a WP-CLI file copy, a deploy that rsyncs the
 * plugins directory. A plugin whose tables exist only if one particular hook fired
 * is a plugin that is missing its tables on somebody's site.
 *
 * The load-time check costs one option read on requests where nothing has changed,
 * which is why it compares a stored version string rather than asking the database
 * whether the table is there.
 *
 * @since 0.5.0
 */
class Migrator {

	/**
	 * Option holding the schema version last applied.
	 *
	 * @var string
	 * @since 0.5.0
	 */
	public const VERSION_OPTION = 'jtzl_bltn_db_version';

	/**
	 * The schema version this build expects.
	 *
	 * Bumped when a table changes, independently of the plugin version — most
	 * releases do not touch the schema, and tying the two would run dbDelta on every
	 * upgrade for nothing.
	 *
	 * @var string
	 * @since 0.5.0
	 */
	public const SCHEMA_VERSION = '2';

	/**
	 * Schema definition.
	 *
	 * @var Schema
	 * @since 0.5.0
	 */
	private Schema $schema;

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 * @since 0.6.0
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Schema $schema Schema definition.
	 * @param \wpdb  $wpdb   WordPress database handle.
	 */
	public function __construct( Schema $schema, \wpdb $wpdb ) {
		$this->schema = $schema;
		$this->wpdb   = $wpdb;
	}

	/**
	 * Apply the schema if this build's version is not the one already applied.
	 *
	 * @since 0.5.0
	 */
	public function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}

		$this->upgrade();
	}

	/**
	 * Apply the schema unconditionally, then record the version.
	 *
	 * WordPress's dbDelta() creates what is missing and alters what has drifted, so
	 * this is safe to run against a fresh site and an existing one alike.
	 *
	 * The version is recorded only after dbDelta() returns. If it fails — no
	 * permission to create tables, a storage engine that refuses the statement — the
	 * option stays behind and the next request tries again, rather than the plugin
	 * recording success it did not have and never looking at the schema again.
	 *
	 * A new column is not always the whole upgrade. dbDelta() gives every existing row
	 * that column's default, and a default is a claim about rows written before the
	 * column existed — so where that claim would be wrong, the data has to be brought
	 * forward too, in the same guarded step and before the version is recorded.
	 *
	 * @since 0.5.0
	 */
	public function upgrade(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$from = (string) get_option( self::VERSION_OPTION, '' );

		foreach ( $this->schema->get_create_table_sql() as $sql ) {
			dbDelta( $sql );
		}

		if ( '' !== $from && version_compare( $from, '2', '<' ) && ! $this->backfill_read_ids() ) {
			// The column exists but the rows are still v1's. Recording the version
			// here would mean the backfill never runs again and every caught-up row
			// stays at 0 — the forum-wide re-light it exists to prevent, arriving
			// silently. Leaving the version behind costs a repeated no-op dbDelta on
			// the next request and buys a retry.
			return;
		}

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Give every v1 read row that was level with its topic the position it implied.
	 *
	 * ⚠ **Without this, upgrade day marks the entire forum unread.** A v1 row recorded
	 * only a time, and being caught up meant that time equalled the topic's
	 * `_bbp_last_active_time`. The new column's default of 0 then loses to any real
	 * activity ID in the tiebreak — `(same time, 0)` is behind `(same time, 41)` — so
	 * every topic every member had finished reading would light up at once. The
	 * default is right for a row nothing can be said about; it is wrong for a row
	 * whose position is still recoverable, and most of them are.
	 *
	 * Recoverable means exactly one thing: the stored time still equals the topic's
	 * last-active time, so the reader was level with the topic and remains level with
	 * it. Those rows take the topic's current activity ID. A row already behind its
	 * topic is left at 0, which costs nothing — the timestamp alone already says
	 * unread, so the tiebreak is never reached — and a row whose topic is gone cannot
	 * be answered for at all; it keeps 0 and waits to be pruned.
	 *
	 * The fallbacks are the same ones both unread queries apply, so a topic bbPress
	 * never stamped is treated identically on both sides of the comparison.
	 *
	 * Safe to repeat: `read_id = 0` is the whole selection, so rows already carried
	 * forward are skipped and a run that died half way resumes where it stopped.
	 *
	 * ⚠ Returns whether the statement ran, because `wpdb::query()` reports a failure
	 * by returning false rather than by throwing — a lock timeout or a deadlock on a
	 * large table would otherwise fall straight through to the version being recorded.
	 * Zero rows affected is success, and the ordinary case on a site with no read
	 * state at all, so the check is strictly against false.
	 *
	 * @since 0.6.0
	 *
	 * @return bool Whether the backfill statement completed.
	 */
	private function backfill_read_ids(): bool {
		$reads = $this->schema->topic_reads_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query(
			"UPDATE {$reads} r
			   JOIN {$this->wpdb->posts} p
			     ON p.ID = r.topic_id
		  LEFT JOIN {$this->wpdb->postmeta} m
			     ON m.post_id = p.ID AND m.meta_key = '_bbp_last_active_time'
		  LEFT JOIN {$this->wpdb->postmeta} i
			     ON i.post_id = p.ID AND i.meta_key = '_bbp_last_active_id'
			    SET r.read_id = CAST( COALESCE( NULLIF( i.meta_value, '' ), p.ID ) AS UNSIGNED )
			  WHERE r.read_id = 0
			    AND COALESCE( NULLIF( m.meta_value, '' ), p.post_date ) = r.read_time"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false !== $result;
	}
}
