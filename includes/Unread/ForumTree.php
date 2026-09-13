<?php
/**
 * The forum hierarchy, as far as one reader may see it.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Unread;

use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Answers "what is beneath this forum, for this reader".
 *
 * Split out of Unread\ReadState because it is a different question: read state is
 * about a member and a topic, this is about how forums nest and who may see them.
 * ReadState needs it only because a forum row's dot has to cover the same branch its
 * topic count does — sub-forums included, or a category could never light up.
 *
 * @since 0.5.0
 */
class ForumTree {

	private \wpdb $wpdb;

	private ContextInterface $wp;

	public function __construct( \wpdb $wpdb, ContextInterface $wp ) {
		$this->wpdb = $wpdb;
		$this->wp   = $wp;
	}

	/**
	 * Every forum beneath each of these forums, to any depth.
	 *
	 * One query for the whole forum tree, walked in PHP. The alternative — a query
	 * per level — is more queries on every forum list for a set that is small on any
	 * real install: bbPress renders the entire tree on its own index, so a site whose
	 * forum table is too big to read here has a much louder problem than this dot.
	 *
	 * @since 0.5.0
	 *
	 * @param array<int,int> $forum_ids Forum IDs, already cleaned.
	 * @return array<int,array<int,int>> Descendant IDs keyed by forum ID.
	 */
	public function descendants_of( array $forum_ids ): array {
		$wpdb = $this->wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one read of the forum tree per request; see the docblock.
		$tree = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type = %s",
				$this->wp->get_forum_post_type()
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// What this reader may not see never contributes a dot. Without this a topic
		// inside a hidden or private sub-forum — `publish`, so it passes every filter
		// in forums_holding_unread() — lights its parent's dot, and the reader cannot
		// clear it because they cannot reach the topic. A permanently stuck mark is
		// worse than a missing one, and this is the same visibility question
		// Query\SearchVisibility answers for search and AJAX
		// endpoints. bbPress computes the set per reader and per capability, so it is
		// asked here rather than cached anywhere.
		$excluded = array_flip( $this->wp->get_excluded_forum_ids() );

		$children = array();
		foreach ( (array) $tree as $row ) {
			$id = (int) $row->ID;
			if ( isset( $excluded[ $id ] ) ) {
				continue;
			}

			$children[ (int) $row->post_parent ][] = $id;
		}

		$descendants = array();
		foreach ( $forum_ids as $forum_id ) {
			$descendants[ $forum_id ] = $this->walk( $forum_id, $children, array() );
		}

		return $descendants;
	}

	/**
	 * Collect every forum beneath one forum.
	 *
	 * @since 0.5.0
	 *
	 * @param int                       $forum_id Forum to walk from.
	 * @param array<int,array<int,int>> $children Child IDs keyed by parent ID.
	 * @param array<int,bool>           $seen     IDs already visited on this branch.
	 * @return array<int,int>
	 */
	private function walk( int $forum_id, array $children, array $seen ): array {
		if ( isset( $seen[ $forum_id ] ) || ! isset( $children[ $forum_id ] ) ) {
			return array();
		}

		$seen[ $forum_id ] = true;
		$found             = array();

		foreach ( $children[ $forum_id ] as $child ) {
			$found[] = $child;
			$found   = array_merge( $found, $this->walk( $child, $children, $seen ) );
		}

		return $found;
	}
}
