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
 * @since 0.5.0
 */
class Migrator {

	public const VERSION_OPTION = 'jtzl_bltn_db_version';

	public const SCHEMA_VERSION = '2';

	private Schema $schema;

	private \wpdb $wpdb;

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
	 * @since 0.6.0
	 *
	 * @return bool Whether the backfill statement completed.
	 */
	private function backfill_read_ids(): bool {
		$wpdb = $this->wpdb;

		$reads = $this->schema->topic_reads_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$reads} is the table Database\Schema creates
		$result = $wpdb->query(
			"UPDATE {$reads} r
			   JOIN {$wpdb->posts} p
			     ON p.ID = r.topic_id
		  LEFT JOIN {$wpdb->postmeta} m
			     ON m.post_id = p.ID AND m.meta_key = '_bbp_last_active_time'
		  LEFT JOIN {$wpdb->postmeta} i
			     ON i.post_id = p.ID AND i.meta_key = '_bbp_last_active_id'
			    SET r.read_id = CAST( COALESCE( NULLIF( i.meta_value, '' ), p.ID ) AS UNSIGNED )
			  WHERE r.read_id = 0
			    AND COALESCE( NULLIF( m.meta_value, '' ), p.post_date ) = r.read_time"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return false !== $result;
	}
}
