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
 */
class SearchController implements ControllerInterface {

	private FeatureGate $features;

	private UnanchoredRepository $collections;

	private SearchSerializer $results;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

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
	 * Data contract.
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
	 * Data contract.
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

	private function missing_terms(): \WP_Error {
		return new \WP_Error(
			'rest_missing_callback_param',
			/* translators: %s: name of the missing request parameter. */
			sprintf( __( 'Missing parameter(s): %s', 'jtzls-bulletin-for-bbpress' ), 'q' ),
			array(
				'status' => 400,
				'params' => array( 'q' ),
			)
		);
	}
}
