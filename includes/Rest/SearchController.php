<?php
/**
 * Search reads.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * `GET /search?q=` — forums, threads and replies in one list, newest first.
 *
 * ## One route, three kinds of row
 *
 * bbPress searches all three of its post types in a single query and interleaves them
 * by date, which is why the website's search screen is a bespoke one rather than a
 * topics loop (surface S10). The API keeps that shape: one flat array, ordered by
 * `(created, ID)` descending, every row carrying `type`. The alternative — three
 * requests the app would have to merge — would put bbPress's ordering in the client
 * and make paging meaningless.
 *
 * ## The two refusals, and why neither is a 404
 *
 * ⚠ **A terms-less search is a 400, not an empty collection.** It is not merely
 * pointless: `bbp_has_search_results()` assigns `bbpress()->search_query` only when
 * `s` is non-empty and then reads `->posts` off it either way, so a blank `q` reaches
 * a property that has been unset since `WP_Query::init()` ran. bbPress's own templates
 * step around it by asking for the terms first, and so does this — `q` is a required
 * argument so WordPress refuses an absent one before the callback, and a whitespace-only
 * one is refused here in the same words, because the app cannot be expected to tell
 * those two mistakes apart.
 *
 * **Search off site-wide is a 403.** It is a setting the site controls and the same
 * answer for everybody, so there is nothing to conceal — see `Rest\AccessPolicy::search()`.
 *
 * @since 0.6.0
 */
class SearchController implements ControllerInterface {

	/**
	 * Whether there is a search to run at all.
	 *
	 * @var FeatureGate
	 * @since 0.6.0
	 */
	private FeatureGate $features;

	/**
	 * Collection arguments.
	 *
	 * @var UnanchoredRepository
	 * @since 0.6.0
	 */
	private UnanchoredRepository $collections;

	/**
	 * Result rows.
	 *
	 * @var SearchSerializer
	 * @since 0.6.0
	 */
	private SearchSerializer $results;

	/**
	 * What a request may ask for.
	 *
	 * @var RequestBounds
	 * @since 0.6.0
	 */
	private RequestBounds $bounds;

	/**
	 * What comes back.
	 *
	 * @var ResponseFactory
	 * @since 0.6.0
	 */
	private ResponseFactory $responses;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param FeatureGate          $features    Whether there is a search to run.
	 * @param UnanchoredRepository $collections Collection arguments.
	 * @param SearchSerializer     $results     Result rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
	public function __construct(
		FeatureGate $features,
		UnanchoredRepository $collections,
		SearchSerializer $results,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->features    = $features;
		$this->collections = $collections;
		$this->results     = $results;
		$this->bounds      = $bounds;
		$this->responses   = $responses;
	}

	/**
	 * The routes this controller answers.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
		return array(
			'/search' => $this->bounds->readable_route(
				array( $this, 'get_collection' ),
				$this->bounds->collection_args() + array(
					'q' => $this->bounds->string_arg( array( 'required' => true ) ),
				),
			),
		);
	}

	/**
	 * One page of results.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_collection( \WP_REST_Request $request ) {
		$enabled = $this->features->search();

		if ( true !== $enabled ) {
			return $enabled;
		}

		$terms = $this->bounds->terms( $request );

		if ( '' === $terms ) {
			return $this->missing_terms();
		}

		$page = $this->collections->search(
			$terms,
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->results->results( $page['results'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}

	/**
	 * The refusal WordPress would have given for an absent `q`, for a blank one.
	 *
	 * Deliberately the same code, status and `params` payload rather than a Bulletin
	 * invention: an app that already handles the absent case is handling this one, and
	 * a second error code for the same mistake is a second thing to implement against.
	 *
	 * @since 0.6.0
	 *
	 * @return \WP_Error
	 */
	private function missing_terms(): \WP_Error {
		return new \WP_Error(
			'rest_missing_callback_param',
			/* translators: %s: name of the missing request parameter. */
			sprintf( __( 'Missing parameter(s): %s', 'jtzl-bulletin' ), 'q' ),
			array(
				'status' => 400,
				'params' => array( 'q' ),
			)
		);
	}
}
