<?php
/**
 * Topic tags, as the app sees them.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * A tag is `id`, `slug`, `name` and `count` — and the count is the interesting field.
 */
class TagSerializer {

	private RestContextInterface $rest;

	public function __construct( RestContextInterface $rest ) {
		$this->rest = $rest;
	}

	/**
	 * Data contract.
	 *
	 * @param int[] $topic_ids Topics.
	 * @return int[]
	 */
	public function term_ids( array $topic_ids ): array {
		if ( ! $this->rest->topic_tags_enabled() ) {
			return array();
		}

		$ids = array();

		foreach ( $topic_ids as $topic_id ) {
			foreach ( $this->rest->get_topic_tags( (int) $topic_id ) as $term ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int            $topic_id Topic ID.
	 * @param array<int,int> $counts   Visible counts, keyed by term ID.
	 * @return array<int,array{id:int,slug:string,name:string,count:int}>
	 */
	public function topic_tags( int $topic_id, array $counts ): array {
		if ( ! $this->rest->topic_tags_enabled() ) {
			return array();
		}

		return $this->tags( $this->rest->get_topic_tags( $topic_id ), $counts );
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_Term[]     $terms  Terms.
	 * @param array<int,int> $counts Visible counts, keyed by term ID.
	 * @return array<int,array{id:int,slug:string,name:string,count:int}>
	 */
	public function tags( array $terms, array $counts ): array {
		$tags = array();

		foreach ( $terms as $term ) {
			$tags[] = array(
				'id'    => (int) $term->term_id,
				'slug'  => (string) $term->slug,
				'name'  => (string) $term->name,
				'count' => (int) ( $counts[ (int) $term->term_id ] ?? 0 ),
			);
		}

		return $tags;
	}
}
