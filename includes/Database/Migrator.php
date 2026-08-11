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
	public const SCHEMA_VERSION = '1';

	/**
	 * Schema definition.
	 *
	 * @var Schema
	 * @since 0.5.0
	 */
	private Schema $schema;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Schema $schema Schema definition.
	 */
	public function __construct( Schema $schema ) {
		$this->schema = $schema;
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
	 * @since 0.5.0
	 */
	public function upgrade(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $this->schema->get_create_table_sql() as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}
}
