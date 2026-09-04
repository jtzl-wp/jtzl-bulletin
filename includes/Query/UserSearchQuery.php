<?php
/**
 * The arguments a member search runs with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Query;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Restricts member search to display names and slugs; email and login searches would
 * disclose account identifiers. RestContext also pins the filtered columns to this
 * list. The ID tiebreak keeps duplicate display names pageable.
 *
 * @since 0.6.1
 */
class UserSearchQuery {

	/**
	 * Searchable public identity columns.
	 *
	 * @var string[]
	 */
	public const COLUMNS = array( 'display_name', 'user_nicename' );

	/**
	 * One page of the members matching a term.
	 *
	 * The term is not checked here. How short is too short is a property of the
	 * route rather than of the query — see Rest\UserSearchController::MIN_TERM_LENGTH,
	 * which refuses before this is ever built.
	 *
	 * @since 0.6.1
	 *
	 * @param string $terms    What to search for; already trimmed and long enough.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page, already bounded.
	 * @return array<string,mixed>
	 */
	public function args( string $terms, int $page, int $per_page ): array {
		return array(
			'search'         => '*' . $terms . '*',
			'search_columns' => self::COLUMNS,
			'number'         => max( 1, $per_page ),
			'paged'          => max( 1, $page ),
			'orderby'        => array(
				'display_name' => 'ASC',
				'ID'           => 'ASC',
			),
		);
	}
}
