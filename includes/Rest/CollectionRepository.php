<?php
/**
 * The arguments every REST collection is queried with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\ForumQuery;
use JTZL\Bulletin\Query\TopicQuery;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Turns a page request into a query, once, for every collection the API serves.
 *
 * ## Why the controllers do not build their own arguments
 *
 * Because the reader's forum scope is the kind of thing that can be left off. It
 * rides the query variables as a marker (Rest\CollectionVisibility), so a collection
 * that forgets to arm it is not refused and does not warn — it returns the site's
 * content in place of this reader's, with an `X-WP-Total` that matches the rows and
 * makes the answer look deliberate. Routing every collection through one class turns
 * "did this controller remember" into a property one test can hold.
 *
 * ## Ordering is borrowed, never restated
 *
 * The `Query\*` builders are the website's own, and the API reuses them rather than
 * writing a second copy of each order. That matters most where an order is a bug fix:
 * `Query\ForumQuery`'s `(menu_order, title, ID)` exists because forums tie on the
 * first two and MySQL returns tied rows in an undefined order, which pages a list by
 * serving one row twice and dropping another. A second implementation would inherit
 * the defect the first one fixed.
 *
 * The page size is the one argument the API overrides. `Query\ForumQuery` carries
 * bbPress's `_bbp_forums_per_page` because the website's forum list has no second
 * page and that number is a ceiling; the API pages, so the request's size replaces
 * it — bounded by Rest\RequestBounds before it ever arrives here.
 *
 * @since 0.6.0
 */
class CollectionRepository {

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * The forum scope every collection is narrowed to.
	 *
	 * @var CollectionVisibility
	 * @since 0.6.0
	 */
	private CollectionVisibility $visibility;

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * Shared forum-query builder.
	 *
	 * @var ForumQuery
	 * @since 0.6.0
	 */
	private ForumQuery $forums;

	/**
	 * Shared topic-query builder.
	 *
	 * @var TopicQuery
	 * @since 0.6.0
	 */
	private TopicQuery $topics;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp         WordPress/bbPress seam.
	 * @param RestContextInterface $rest       REST seam.
	 * @param CollectionVisibility $visibility Collection scope.
	 * @param ForumQuery           $forums     Shared forum-query builder.
	 * @param TopicQuery           $topics     Shared topic-query builder.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		CollectionVisibility $visibility,
		ForumQuery $forums,
		TopicQuery $topics
	) {
		$this->wp         = $wp;
		$this->rest       = $rest;
		$this->visibility = $visibility;
		$this->forums     = $forums;
		$this->topics     = $topics;
	}

	/**
	 * One page of one level of the forum hierarchy.
	 *
	 * @since 0.6.0
	 *
	 * @param int $parent_id Parent forum, or 0 for the top-level index.
	 * @param int $page      1-based page number.
	 * @param int $per_page  Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function forums( int $parent_id, int $page, int $per_page ): array {
		$args                   = $this->forums->args( $parent_id, $page );
		$args['posts_per_page'] = $per_page;

		return $this->rest->query( $this->visibility->scope( $args ) );
	}

	/**
	 * One page of a forum's thread list: pinned topics first, then freshness.
	 *
	 * ## One sequence, not a prefix beside a collection
	 *
	 * The pinned topics are part of the pagination rather than exempt from it. Handing
	 * back every sticky and then starting to count would make page 1 an unbounded
	 * response on a forum with forty of them, and would put the same rows above every
	 * later page — the app has no way to ask for "the rest of page 1".
	 *
	 * So the visible pinned IDs form a prefix, the requested slice is taken out of it,
	 * and whatever is still owed comes from the ordinary query at an offset reduced by
	 * the prefix's length. `Query\TopicQuery::args()` already excludes every sticky
	 * from that query, so no row can arrive twice.
	 *
	 * ⚠ **The ordinary query runs even when the page is entirely pinned.** Its rows are
	 * discarded then, but its `found_posts` is the other half of the collection's
	 * total, and a page that skipped it would report a thread list two rows long.
	 *
	 * ⚠ **And its page count is not the collection's.** It is asked for `$needed` rows,
	 * not `$per_page`, so `total_pages` from that query counts pages in the wrong unit.
	 * The number below is derived from the combined total.
	 *
	 * @since 0.6.0
	 *
	 * @param int $forum_id Forum whose topics are wanted.
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function forum_topics( int $forum_id, int $page, int $per_page ): array {
		$pinned   = $this->pinned_topic_ids( $forum_id );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;
		$page_ids = array_slice( $pinned, $offset, $per_page );
		$needed   = $per_page - count( $page_ids );

		$ordinary                   = $this->topics->args( $forum_id, 1 );
		$ordinary['offset']         = max( 0, $offset - count( $pinned ) );
		$ordinary['posts_per_page'] = max( 1, $needed );

		$rest_of_page = $this->rest->query( $this->visibility->scope( $ordinary ) );
		$total        = count( $pinned ) + $rest_of_page['total'];

		return array(
			'ids'         => $needed > 0 ? array_merge( $page_ids, $rest_of_page['ids'] ) : $page_ids,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		);
	}

	/**
	 * One page of the topics carrying a tag, across every forum the reader may see.
	 *
	 * @since 0.6.0
	 *
	 * @param int $term_id  Tag to filter by.
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function tag_topics( int $term_id, int $page, int $per_page ): array {
		$args                   = $this->topics->tagged_args( $this->rest->topic_tag_taxonomy(), $term_id, $page );
		$args['posts_per_page'] = $per_page;

		return $this->rest->query( $this->visibility->scope( $args ) );
	}

	/**
	 * One page of the tag vocabulary, with the counts that describe it.
	 *
	 * ⚠ Not routed through `Rest\CollectionVisibility`, and that is not an omission:
	 * the scope arrives inside the seam's own query instead. `posts_where` narrows a
	 * `WP_Query` over posts, and this is a grouped query over the taxonomy tables — a
	 * marker on arguments no `WP_Query` will ever see would be a scope that silently
	 * did nothing, which is the exact failure this repository exists to prevent.
	 *
	 * @since 0.6.0
	 *
	 * @param int $page     1-based page number.
	 * @param int $per_page Terms per page, already bounded.
	 * @return array{terms:\WP_Term[],counts:array<int,int>,total:int,total_pages:int}
	 */
	public function tags( int $page, int $per_page ): array {
		$vocabulary = $this->rest->visible_tags(
			array( 'post_status' => $this->wp->get_readable_topic_statuses() ),
			$page,
			$per_page
		);

		return $vocabulary + array( 'total_pages' => (int) ceil( $vocabulary['total'] / max( 1, $per_page ) ) );
	}

	/**
	 * The pinned topics a reader may actually see, supers first.
	 *
	 * ⚠ **Both queries are skipped when their `post__in` is empty.** `WP_Query` ignores
	 * an empty `post__in` rather than matching nothing, so running either one on a
	 * forum with no stickies would return *every topic on the site* as the pinned
	 * prefix — and it would look like an ordering bug rather than a visibility one.
	 *
	 * ⚠ **And they are scoped like any other collection.** A super sticky is pinned
	 * into every forum from wherever it lives, so an unscoped pinned query is a way to
	 * read the title of a thread in a forum that is closed to you.
	 *
	 * @since 0.6.0
	 *
	 * @param int $forum_id Forum whose pinned topics are wanted.
	 * @return int[]
	 */
	private function pinned_topic_ids( int $forum_id ): array {
		$pinned = array_merge(
			$this->pinned_page( $this->topics->super_pinned_args() ),
			$this->pinned_page( $this->topics->forum_pinned_args( $forum_id ) )
		);

		return array_values( array_unique( $pinned ) );
	}

	/**
	 * One pinned query's visible IDs, or none when it names no topics.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $args Pinned query arguments.
	 * @return int[]
	 */
	private function pinned_page( array $args ): array {
		if ( array() === (array) ( $args['post__in'] ?? array() ) ) {
			return array();
		}

		return $this->rest->query( $this->visibility->scope( $args ) )['ids'];
	}
}
