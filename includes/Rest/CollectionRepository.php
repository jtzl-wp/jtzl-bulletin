<?php
/**
 * The arguments every REST collection is queried with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\ForumQuery;
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
	 * Shared forum-query builder.
	 *
	 * @var ForumQuery
	 * @since 0.6.0
	 */
	private ForumQuery $forums;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param RestContextInterface $rest       REST seam.
	 * @param CollectionVisibility $visibility Collection scope.
	 * @param ForumQuery           $forums     Shared forum-query builder.
	 */
	public function __construct(
		RestContextInterface $rest,
		CollectionVisibility $visibility,
		ForumQuery $forums
	) {
		$this->rest       = $rest;
		$this->visibility = $visibility;
		$this->forums     = $forums;
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
}
