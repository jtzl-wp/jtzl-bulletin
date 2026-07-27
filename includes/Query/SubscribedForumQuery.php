<?php
/**
 * Paging for the Subscribed Forums list on a member's profile.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Makes the "Subscribed Forums" half of a profile's Subscriptions tab pageable, and
 * is the single place the two keys that do it are written: found-row counting back
 * on, and a total sort order.
 *
 * The defect is the one Query\ForumQuery fixed on the takeover tier, reaching a
 * reskin screen by a different route (issue #50). `bbp_get_user_forum_subscriptions()`
 * hands its arguments to `bbp_has_forums()`, which caps at `_bbp_forums_per_page`
 * (50) and sets `no_found_rows => true` — so a reader subscribed to 51 forums is
 * shown 50 of them, and nothing on the screen says the others exist. The symptom is
 * an absence, which is why it hid. The Subscribed *Topics* list beside it paginates
 * (bbPress renders `pagination-topics.php` around that loop), so only the forums half
 * is affected — an upstream oversight rather than a design choice.
 *
 * Both keys are applied through one private method used by both entry points, for
 * the reason CLAUDE.md's trap #4 records: page 1 and the continuation that follows it
 * must be pages of the same ordered set, or the two disagree about which row sits
 * where and LIMIT/OFFSET serves one forum twice while dropping another.
 *
 *  - `filter_forum_args()` answers `bbp_after_has_forums_parse_args` for the page
 *    render. We cannot pass arguments there: bbPress's own `user-subscriptions.php`
 *    makes that call, with no arguments of its own to intercept. The filter fires for
 *    every `bbp_has_forums()` call on the site, including the forum index shortcode
 *    on an ordinary page, so it is scoped to `bbp_is_subscriptions()` — bbPress's own
 *    conditional, and the same one `bbp_has_forums()` consults to decide that a
 *    subscriptions screen lists forums of any parent rather than root forums.
 *  - `args()` builds the continuation's arguments outright, because inside the AJAX
 *    request there is no bbPress template to filter.
 *
 * `no_found_rows` is the load-bearing key. It skips the `COUNT(*)`, leaving
 * `max_num_pages` at 0 — so there is no page count to compare a page against, and no
 * control could be rendered even if a template wanted one. Switching it back off
 * costs one count per query and buys the rest of the list.
 *
 * The order matters for the same reason it does on the index: bbPress asks for
 * `menu_order title`, `menu_order` is 0 for every forum until a keymaster sets one,
 * and identical titles are legal — so ties are the norm here, MySQL returns tied rows
 * in an undefined order, and paging over that sort is not stable (issues #45 and #38).
 * The `ID` tiebreak makes it total. Keyed rather than a string, which is also why
 * `order` is left alone: WP_Query takes the direction per key and ignores `order`
 * entirely for an array.
 *
 * What is *in* the list stays bbPress's decision. The user, and how a subscription is
 * stored, come from bbPress's engagement strategy (see
 * ContextInterface::has_forum_subscriptions), and forum visibility is filtered by the
 * `post_status` and capability pass bbPress applies to any forum query. This changes
 * how much of the list a reader can reach, never which forums are in it.
 *
 * @since 0.3.0
 */
class SubscribedForumQuery {

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
	 * Args for one page of the subscribed-forums list.
	 *
	 * `post_parent` is passed rather than left to bbPress, on the precedent
	 * Query\ForumQuery set: bbPress defaults it from ambient state, and a
	 * continuation should not depend on that state having survived into its own
	 * request. `'any'` is what a subscriptions screen means — a subscribed forum can
	 * sit at any depth, so restricting to a parent would hide the sub-forums a reader
	 * subscribed to. WP_Query drops the parent clause entirely for a non-numeric
	 * value, which is how bbPress spells the same thing.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page 1-based page number.
	 * @return array<string,mixed>
	 */
	public function args( int $page ): array {
		return $this->pageable(
			array(
				'post_type'      => $this->wp->get_forum_post_type(),
				'post_parent'    => 'any',
				'posts_per_page' => $this->wp->get_forums_per_page(),
				'paged'          => max( 1, $page ),
			)
		);
	}

	/**
	 * Make bbPress's own subscribed-forums query pageable, on the profile tab only.
	 *
	 * Hooked on `bbp_after_has_forums_parse_args`, which hands over the merged
	 * arguments in the moment before bbPress runs the query. Nothing else is touched:
	 * the page the reader is served is still page 1, which is the contract every
	 * load-more list here keeps — the document ships page 1 and the control grows it.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed query arguments.
	 * @return mixed
	 */
	public function filter_forum_args( $args ) {
		if ( ! is_array( $args ) || ! $this->wp->is_subscriptions() ) {
			return $args;
		}

		return $this->pageable( $args );
	}

	/**
	 * Count the rows, and settle the order they come back in.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<string,mixed>
	 */
	private function pageable( array $args ): array {
		$args['no_found_rows'] = false;
		$args['orderby']       = array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
			'ID'         => 'ASC',
		);

		return $args;
	}
}
