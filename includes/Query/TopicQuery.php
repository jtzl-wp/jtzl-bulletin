<?php
/**
 * Canonical topic-query arguments.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Builds the bbp_has_topics() args used by BOTH the initial forum render and the
 * load-more AJAX handler, so the two paths paginate identically — the same role
 * Query\ReplyQuery plays for the reading view.
 *
 * @since 0.1.0
 */
class TopicQuery {

	private ContextInterface $wp;

	private ProtectedStatusGuard $guard;

	public function __construct( ContextInterface $wp, ProtectedStatusGuard $guard ) {
		$this->wp    = $wp;
		$this->guard = $guard;
	}

	/**
	 * Args for a forum's paginated, sticky-free thread list.
	 *
	 * @since 0.1.0
	 *
	 * @param int $forum_id Forum to list topics for.
	 * @param int $page     1-based page number.
	 * @return array<string,mixed>
	 */
	public function args( int $forum_id, int $page ): array {
		return $this->base() + array(
			'post_parent'    => $forum_id,
			'posts_per_page' => $this->wp->get_topics_per_page(),
			'paged'          => max( 1, $page ),
			'post__not_in'   => $this->wp->get_sticky_topic_ids( $forum_id ),
		);
	}

	/**
	 * Args for every topic carrying one tag, across the forums the reader may see.
	 *
	 * @since 0.6.0
	 *
	 * @param string $taxonomy Topic-tag taxonomy.
	 * @param int    $term_id  Tag to filter by.
	 * @param int    $page     1-based page number.
	 * @return array<string,mixed>
	 */
	public function tagged_args( string $taxonomy, int $term_id, int $page ): array {
		return $this->base() + array(
			'posts_per_page' => $this->wp->get_topics_per_page(),
			'paged'          => max( 1, $page ),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The tag filter is the query.
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $term_id ),
				),
			),
		);
	}

	/**
	 * Args for a named set of topics, in the same order a forum lists its threads.
	 *
	 * @since 0.6.0
	 *
	 * @param int[] $topic_ids Topics to list; must not be empty.
	 * @param int   $page      1-based page number.
	 * @param int   $per_page  Rows per page.
	 * @return array<string,mixed>
	 */
	public function listed_args( array $topic_ids, int $page, int $per_page ): array {
		return $this->base() + array(
			'post__in'       => array_values( array_map( 'intval', $topic_ids ) ),
			'posts_per_page' => max( 1, $per_page ),
			'paged'          => max( 1, $page ),
		);
	}

	/**
	 * Args for the site-wide super stickies, which lead the pinned section.
	 *
	 * A super sticky is pinned into every forum, so it usually lives under a
	 * different parent — scoping this query to the forum would drop it. bbPress
	 * still filters by forum visibility either way
	 * (bbp_pre_get_posts_normalize_forum_visibility excludes topics whose
	 * _bbp_forum_id the reader may not see), so widening the parent here does not
	 * widen what is readable.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string,mixed>
	 */
	public function super_pinned_args(): array {
		return $this->pinned_base() + array(
			'post__in'    => $this->wp->get_super_sticky_ids(),
			'post_parent' => 'any',
		);
	}

	/**
	 * Args for a forum's own stickies, which follow the supers.
	 *
	 * Supers are subtracted rather than merely ordered after: a topic can be both
	 * (a keymaster stickies it in its forum, then promotes it site-wide), and
	 * without the subtraction it would render in the pinned section twice.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum to list pinned topics for.
	 * @return array<string,mixed>
	 */
	public function forum_pinned_args( int $forum_id ): array {
		return $this->pinned_base() + array(
			'post__in'    => array_values(
				array_diff(
					$this->wp->get_sticky_topic_ids( $forum_id ),
					$this->wp->get_super_sticky_ids()
				)
			),
			'post_parent' => $forum_id,
		);
	}

	/**
	 * The parts both pinned queries share.
	 *
	 * Returned unpaginated: stickies are a handful by definition, and splitting
	 * them across pages would put a "load more" inside the pinned section.
	 * Callers must skip a pinned query whose post__in came back empty, since
	 * WP_Query ignores an empty post__in and would return every topic.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string,mixed>
	 */
	private function pinned_base(): array {
		return $this->base() + array(
			'posts_per_page' => -1,
			'paged'          => 1,
		);
	}

	/**
	 * The parts both queries share.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string,mixed>
	 */
	private function base(): array {
		// Armed here rather than in each of the three builders: they all funnel
		// through this, and bbp_has_topics() leaves post_status to WordPress — which
		// hands `draft`, `future` and `pending` topics to anyone inside an AJAX
		// request. See Query\ProtectedStatusGuard.
		return $this->guard->marker() + array(
			'post_type'     => $this->wp->get_topic_post_type(),
			'show_stickies' => false,
			'meta_key'      => '_bbp_last_active_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bbPress's own topic ordering key.
			'meta_type'     => 'DATETIME',
			'orderby'       => array(
				'meta_value' => 'DESC',
				'ID'         => 'DESC',
			),
		);
	}
}
