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
 */
class UserSearchRepository {

	private RestContextInterface $rest;

	private UserSearchQuery $query;

	public function __construct( RestContextInterface $rest, UserSearchQuery $query ) {
		$this->rest  = $rest;
		$this->query = $query;
	}

	/**
	 * Search columns are explicitly bounded to display name and slug; email and login must not become searchable through WordPress filters.
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
