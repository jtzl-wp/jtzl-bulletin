<?php
/**
 * Canonical forum-query arguments.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Builds the bbp_has_forums() args used by BOTH takeover forum screens and the
 * load-forums AJAX handler — the role Query\TopicQuery plays for the thread list.
 *
 * It exists because a forum list in bbPress has no second page. `bbp_has_forums()`
 * caps itself at `_bbp_forums_per_page` (50) and sets `no_found_rows => true`, and
 * no bbPress template paginates a forum list — the `bbp_forum_pagination_*` helpers
 * page the *topics* inside a forum, despite the name. So the cap is not a page size,
 * it is a ceiling: forum 51 and everything after it is unreachable, along with every
 * topic that only that forum links to. Verified on the dev fixture at 58 root forums,
 * where /forums/ served 50 and /forums/page/2/ answered 200 with the very same 50.
 *
 * `no_found_rows` is the load-bearing part. It tells WP_Query to skip the COUNT(*),
 * which leaves `max_num_pages` at 0 — so there is no page count to page against, and
 * no control could be rendered even if a template wanted to. Perfectly reasonable
 * while nothing paginates; it is the first thing that has to change once something
 * does. Switching it back off costs one COUNT per query and buys the whole list.
 *
 * The rest follows from paging over LIMIT/OFFSET:
 *
 *  - An explicit post_parent, for the reason TopicQuery has one: bbPress defaults it
 *    from ambient state (0 on the forum archive, the viewed forum otherwise, 'any' on
 *    a subscriptions screen), and none of those hold inside an AJAX request. The index
 *    passes 0 and a sub-forum list passes its parent.
 *
 *  - A (menu_order, title, ID) order. bbPress orders forums by `menu_order title`,
 *    and menu_order is 0 for every forum until a keymaster sets one — so ties are the
 *    norm here, not the exception, and titles are what actually break them. Identical
 *    titles are legal, though, and MySQL returns tied rows in an undefined order, so
 *    LIMIT/OFFSET over that sort can serve one forum twice and drop another (the same
 *    defect as issues #45 and CLAUDE.md trap #4). The ID tiebreak makes it total.
 *    Keyed rather than a string, which is also why `order` is not passed: WP_Query
 *    takes the direction per key and ignores `order` entirely for an array.
 *
 * Everything bbPress sets that governs *visibility* is left untouched — post_status,
 * and the `bbp_pre_get_posts_normalize_forum_visibility` pass that widens it by
 * capability. A private or hidden forum is therefore filtered exactly as it is on
 * bbPress's own index; this changes how much of the list a reader can reach, never
 * which forums are in it. `update_post_family_cache` is likewise left on, so each
 * page still primes the last-active posts its rows report.
 *
 * @since 0.3.0
 */
class ForumQuery {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Args for one page of a forum list.
	 *
	 * @since 0.3.0
	 *
	 * @param int $parent_id Parent forum, or 0 for the root list the index shows.
	 * @param int $page      1-based page number.
	 * @return array<string,mixed>
	 */
	public function args( int $parent_id, int $page ): array {
		return array(
			'post_type'      => $this->wp->get_forum_post_type(),
			'post_parent'    => max( 0, $parent_id ),
			'posts_per_page' => $this->wp->get_forums_per_page(),
			'paged'          => max( 1, $page ),
			'no_found_rows'  => false,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
				'ID'         => 'ASC',
			),
		);
	}
}
