<?php
/**
 * The arguments the REST collections that answer for no one place are queried with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\SearchQuery;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Search, and a member's own posts: the three collections with no anchor.
 */
class UnanchoredRepository {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private CollectionVisibility $visibility;

	private SearchQuery $search;

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		CollectionVisibility $visibility,
		SearchQuery $search
	) {
		$this->wp         = $wp;
		$this->rest       = $rest;
		$this->visibility = $visibility;
		$this->search     = $search;
	}

	/**
	 * Search visibility must be applied inside the query so hidden rows are absent from both results and totals.
	 *
	 * @param string $terms    What to search for; already known to be non-blank.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page, already bounded.
	 * @return array{results:array<int,array{id:int,type:string}>,total:int,total_pages:int}
	 */
	public function search( string $terms, int $page, int $per_page ): array {
		$results = array();

		if ( $this->wp->has_search_results( $this->search_args( $terms, $page, $per_page ) ) ) {
			while ( $this->wp->the_search_results_loop() ) {
				$this->wp->the_search_result();

				$results[] = array(
					'id'   => $this->wp->get_search_result_id(),
					'type' => $this->wp->get_search_result_post_type(),
				);
			}
		}

		$total = $this->search_total( $terms, $page, array() === $results );

		return array(
			'results'     => $results,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		);
	}

	/**
	 * Data contract.
	 *
	 * @param int $author_id Member whose topics are wanted.
	 * @param int $page      1-based page number.
	 * @param int $per_page  Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function user_topics( int $author_id, int $page, int $per_page ): array {
		return $this->rest->query(
			$this->visibility->scope(
				$this->authored( $author_id, $page, $per_page ) + array(
					'post_type'   => $this->wp->get_topic_post_type(),

					'post_status' => $this->wp->get_readable_topic_statuses(),
				)
			)
		);
	}

	/**
	 * Data contract.
	 *
	 * @param int $author_id Member whose replies are wanted.
	 * @param int $page      1-based page number.
	 * @param int $per_page  Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function user_replies( int $author_id, int $page, int $per_page ): array {
		return $this->rest->query(
			$this->visibility->scope_replies(
				$this->authored( $author_id, $page, $per_page ) + array(
					'post_type'   => $this->wp->get_reply_post_type(),
					'post_status' => $this->wp->get_public_reply_statuses(),
				),
				$this->wp->get_readable_topic_statuses()
			)
		);
	}

	/**
	 * Data contract.
	 *
	 * @param string $terms    What to search for.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page.
	 * @return array<string,mixed>
	 */
	private function search_args( string $terms, int $page, int $per_page ): array {
		return $this->visibility->scope( $this->search->args( $terms, $page, $per_page ) );
	}

	private function search_total( string $terms, int $page, bool $empty_page ): int {
		if ( $empty_page && $page > 1 ) {
			$this->wp->has_search_results( $this->search_args( $terms, 1, 1 ) );
		}

		return $this->wp->get_search_result_count();
	}

	/**
	 * Data contract.
	 *
	 * @param int $author_id Member whose posts are wanted.
	 * @param int $page      1-based page number.
	 * @param int $per_page  Rows per page.
	 * @return array<string,mixed>
	 */
	private function authored( int $author_id, int $page, int $per_page ): array {
		return array(
			'author__in'     => array( max( 0, $author_id ) ),
			'posts_per_page' => max( 1, $per_page ),
			'paged'          => max( 1, $page ),
			'orderby'        => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
		);
	}
}
