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
 * The search screen's half of load-more, sibling to LoadTopicsController: same
 * bbPress AJAX router (bbp_get_ajax_url() dispatches to `bbp_ajax_{action}`), same
 * paging contract — page 1 ships with the document, so load-more starts at page 2 —
 * and the same rows, rendered through View\SearchList.
 *
 * Two things are its own.
 *
 * Its subject is a string. The other four continuations name a topic or a forum and
 * validate it as a post; there is no post here, only terms, and they arrive in the
 * request body rather than from a rewrite rule. They are sanitised on the way in —
 * see the seam's sanitize_search_request(), and why bbPress's own wrapper for that
 * cannot be handed this parameter name.
 *
 * And its access check is the setting, not a capability. A search is not scoped to
 * one forum a reader might be barred from: bbPress decides result by result, in the
 * query, from the same `read_private_topics` / `read_hidden_topics` capabilities
 * that scope the screen — so the only thing left to refuse at the door is a site
 * that has turned search off, where the screen this continues does not exist.
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

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp      WordPress/bbPress seam.
	 * @param SearchQuery      $query   Shared search-query builder.
	 * @param SearchList       $results Result list renderer.
	 * @param RequestedPage    $paging  Shared reader and bound for `paged`.
	 */
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

		// send_json_error() ends the request, so there is nothing to return to.
		if ( ! $this->wp->allow_search() ) {
			$this->wp->send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		if ( ! $this->query->is_runnable( $terms ) ) {
			$this->wp->send_json_error( array( 'message' => 'bad_terms' ), 400 );
		}

		// Then the page, after access and never before it.
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
