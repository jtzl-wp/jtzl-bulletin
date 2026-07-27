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
 * Three deliberate choices:
 *
 *  - An explicit post_parent. bbPress defaults it to bbp_get_forum_id(), which is
 *    the forum being viewed only while bbp_is_single_forum() holds. That is false
 *    in an AJAX request (the default becomes 'any', i.e. every forum on the site)
 *    and false again inside a sub-forum loop, so the forum is always passed in.
 *
 *  - Stickies excluded from the paginated set, and pinned separately. bbPress
 *    prepends stickies to page 1 only (bbp_add_sticky_topics bails when paged > 1)
 *    without removing them from the page they naturally fall on — so a sticky
 *    that belongs on page 3 renders pinned at the top AND again when page 3
 *    loads. Excluding them from every page instead keeps LIMIT/OFFSET boundaries
 *    consistent and makes the page count honest about what is left to load.
 *
 *  - Two pinned queries, not one. A site-wide super sticky outranks a forum's
 *    own: bbPress sorts the whole sticky set by freshness and only then splits it
 *    into supers and forum stickies, merging supers first
 *    (bbp_add_sticky_topics's $ordered_stickies). One query cannot express that —
 *    'orderby' => 'post__in' would give the ID array's order inside each group
 *    rather than freshness — so the section runs the two in turn, each ordered
 *    the same way as the list below it.
 *
 *  - A (last-active, ID) order. Topics sort by _bbp_last_active_time, which is a
 *    DATETIME with no sub-second resolution: an import, or a burst of activity in
 *    the same second, ties rows whose relative order MySQL may then return
 *    differently per query. LIMIT/OFFSET paging over an unstable sort duplicates
 *    some rows across page boundaries and drops others (see CLAUDE.md trap #4,
 *    where this bit the replies loop). The ID tiebreak makes paging deterministic.
 *
 * @since 0.1.0
 */
class TopicQuery {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
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
	 * Post_parent is not among them: the two queries scope differently, and PHP's
	 * + operator keeps the left-hand value, so a shared default here would
	 * silently win over the caller's.
	 *
	 * Post_status is absent for a different reason — bbPress fills it from the
	 * reader's capabilities (or falls back to perm => readable), which is the same
	 * visibility logic the forums index gets.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string,mixed>
	 */
	private function base(): array {
		return array(
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
