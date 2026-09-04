<?php
/**
 * Load-more search results endpoint.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Ajax;

use JTZL\Bulletin\Query\SearchQuery;
use JTZL\Bulletin\View\SearchList;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Continues search results through bbPress's front-end AJAX router.
 *
 * The request provides sanitized terms rather than a post ID. The endpoint checks
 * the site search setting; SearchQuery applies per-result visibility capabilities.
 *
 * @since 0.3.0
 */
class LoadSearchController {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Shared search-query builder.
	 *
	 * @var SearchQuery
	 */
	private SearchQuery $query;

	/**
	 * Result list renderer.
	 *
	 * @var SearchList
	 */
	private SearchList $results;

	/**
	 * Shared reader and bound for the requested page.
	 *
	 * @var RequestedPage
	 */
	private RequestedPage $paging;

	public function __construct( ContextInterface $wp, SearchQuery $query, SearchList $results, RequestedPage $paging ) {
		$this->wp      = $wp;
		$this->query   = $query;
		$this->results = $results;
		$this->paging  = $paging;
	}

	/**
	 * Return a rendered page of search results as JSON.
	 *
	 * @since 0.3.0
	 */
	public function handle(): void {
		$terms = $this->wp->sanitize_search_request( 'bbp_search' );
		$page  = $this->paging->requested();

		if ( ! $this->wp->allow_search() ) {
			$this->wp->send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		if ( ! $this->query->is_runnable( $terms ) ) {
			$this->wp->send_json_error( array( 'message' => 'bad_terms' ), 400 );
		}

		// Check access before paging to avoid disclosing range.
		$this->paging->guard( $page );

		$html = $this->results->capture( $this->query->args( $terms, $page ) );
		$max  = $this->query->max_pages();

		$this->wp->send_json_success(
			array(
				'html'     => $html,
				'page'     => $page,
				'nextPage' => $page + 1,
				'hasMore'  => $page < $max,
			)
		);
	}
}
