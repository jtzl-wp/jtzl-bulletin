<?php
/**
 * Canonical search-query arguments.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Builds the bbp_has_search_results() args used by BOTH the initial search render
 * and the load-more AJAX handler, so the two paths paginate identically.
 *
 * @since 0.3.0
 */
class SearchQuery {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Search-query args for a set of terms and a 1-based page.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $terms    Search terms.
	 * @param int      $page     1-based page number.
	 * @param int|null $per_page Rows per page, or null for the site's own.
	 * @return array<string,mixed>
	 */
	public function args( string $terms, int $page, ?int $per_page = null ): array {
		$args = array(
			's'       => $terms,
			'paged'   => max( 1, $page ),
			'orderby' => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
		);

		if ( null !== $per_page ) {
			$args['posts_per_page'] = max( 1, $per_page );
		}

		return $args;
	}

	/**
	 * How many pages of results the last search matched.
	 *
	 * Read off the query object rather than derived, unlike Query\ReplyQuery's: a
	 * page here really is a LIMIT over the whole result set, so bbPress's own count
	 * is the true one and a second derivation could only drift from it.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function max_pages(): int {
		return $this->wp->get_max_search_pages();
	}

	/**
	 * Whether a set of terms is worth running a query for.
	 *
	 * @since 0.3.0
	 *
	 * @param string $terms Search terms.
	 * @return bool
	 */
	public function is_runnable( string $terms ): bool {
		return '' !== trim( $terms );
	}
}
