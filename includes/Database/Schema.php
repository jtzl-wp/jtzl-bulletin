<?php
/**
 * Database schema.
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
 * Bulletin's one custom table, and the only persistent thing the plugin owns.
 *
 * @since 0.5.0
 */
class Schema {

	public const TOPIC_READS = 'jtzl_bltn_topic_reads';

	private \wpdb $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * The prefixed name of the topic-reads table.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function topic_reads_table(): string {
		return $this->wpdb->prefix . self::TOPIC_READS;
	}

	/**
	 * CREATE TABLE statements for every table the plugin owns, in dbDelta's dialect.
	 *
	 * @since 0.5.0
	 *
	 * @return array<int,string>
	 */
	public function get_create_table_sql(): array {
		$charset_collate = $this->wpdb->get_charset_collate();
		$topic_reads     = $this->topic_reads_table();

		return array(
			"CREATE TABLE $topic_reads (
  user_id bigint(20) UNSIGNED NOT NULL,
  topic_id bigint(20) UNSIGNED NOT NULL,
  read_time datetime NOT NULL,
  read_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (user_id,topic_id),
  KEY idx_topic_id (topic_id)
) $charset_collate;",
		);
	}
}
