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
 * Until unread (#102) Bulletin was stateless — every screen was derived from
 * bbPress's own data, which is why `uninstall.php` had nothing to remove. Read state
 * is the one fact bbPress does not hold: there is no per-user read tracking anywhere
 * in it, so whatever a reader has seen has to be stored by us.
 *
 * **A row means "this member has opened this topic, and the topic had not moved past
 * `read_time` when they did". No row means unread.** That is the whole model. It is
 * deliberately not a per-user "last visit" watermark, which is the cheaper shape and
 * the wrong one: a watermark can only ever advance by declaring topics read that the
 * member never opened, and the product decision (Yoren, 2026-08-11) is that a topic
 * is unread until it has actually been opened.
 *
 * The same decision is why there is no expiry column and no row cap. Every rule that
 * bounds this table by size works by lying about what was read, so the only pruning
 * is the deletion of rows about things that no longer exist — see Unread\ReadState.
 *
 * @since 0.5.0
 */
class Schema {

	/**
	 * Table name without the site prefix.
	 *
	 * @var string
	 * @since 0.5.0
	 */
	public const TOPIC_READS = 'jtzl_bltn_topic_reads';

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 * @since 0.5.0
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param \wpdb $wpdb WordPress database handle.
	 */
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
	 * `PRIMARY KEY (user_id, topic_id)` is the model stated as a constraint: one row
	 * per member per topic, so marking a topic read twice cannot produce two rows and
	 * the write path needs no existence check to stay correct. It is also the index
	 * every read uses, because every read is scoped to one member.
	 *
	 * `read_time` is a datetime rather than an integer so it compares directly against
	 * bbPress's `_bbp_last_active_time`, which is a MySQL datetime string in site time.
	 * Converting on either side of that comparison would be a chance to get the
	 * timezone wrong once and be subtly wrong forever.
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
  PRIMARY KEY  (user_id,topic_id),
  KEY idx_topic_id (topic_id)
) $charset_collate;",
		);
	}
}
