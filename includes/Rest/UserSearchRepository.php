<?php
/**
 * The arguments the member search is queried with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\UserSearchQuery;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * The one collection in this API whose rows are not posts.
 *
 * ## Why not a fourth method on Rest\UnanchoredRepository
 *
 * That class holds the collections with no anchor, and a member search is certainly
 * one — but everything it exists to say is about post visibility: which forums this
 * reader may open, what the parent thread's status is, which marker arms which
 * `posts_where` filter. None of it applies to a row out of the user table. A fourth
 * method there would arrive with a paragraph explaining that its every rule is
 * inapplicable, which is the shape of a class boundary rather than of a docblock.
 *
 * ⚠ **And the absence of those rules is a decision, not an oversight.** A member is
 * searchable whether or not they have ever posted, and whether or not this reader
 * could have met them — settled for #143 after measuring that `GET /users/{id}` is a
 * public route with no capability test, so an anonymous walk of the ID space already
 * yields every account's name, avatar and profile link. Search makes that list
 * *queryable*; it does not make it visible. The contract's *User search* section
 * records the reasoning, and closing the ID walk is a separate change to a shipped
 * route rather than something this one may do quietly.
 *
 * @since 0.6.1
 */
class UserSearchRepository {

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.1
	 */
	private RestContextInterface $rest;

	/**
	 * What the user table is asked, and what it is never asked.
	 *
	 * @var UserSearchQuery
	 * @since 0.6.1
	 */
	private UserSearchQuery $query;

	/**
	 * Constructor.
	 *
	 * @since 0.6.1
	 *
	 * @param RestContextInterface $rest  REST seam.
	 * @param UserSearchQuery      $query What the user table is asked.
	 */
	public function __construct( RestContextInterface $rest, UserSearchQuery $query ) {
		$this->rest  = $rest;
		$this->query = $query;
	}

	/**
	 * One page of the members matching a term, by display name or slug.
	 *
	 * @since 0.6.1
	 *
	 * @param string $terms    What to search for; already trimmed and long enough.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function search( string $terms, int $page, int $per_page ): array {
		return $this->rest->user_query( $this->query->args( $terms, $page, $per_page ) );
	}
}
