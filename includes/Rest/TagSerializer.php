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
 *
 * ⚠ **`count` is what this reader may see, not what the site holds.** WordPress's own
 * `WP_Term::$count` counts every topic carrying the tag, including ones in a forum
 * this reader cannot open. Handing that number to the app puts a number beside a list
 * it does not match, and the difference between them says *there is more here than you
 * are being shown*. So counts arrive from a visibility-scoped query, primed once for
 * the whole page and passed in.
 *
 * @since 0.6.0
 */
class TagSerializer {

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param RestContextInterface $rest REST seam.
	 */
	public function __construct( RestContextInterface $rest ) {
		$this->rest = $rest;
	}

	/**
	 * The term IDs carried by a set of topics, for one batched count.
	 *
	 * @since 0.6.0
	 *
	 * @param int[] $topic_ids Topics.
	 * @return int[]
	 */
	public function term_ids( array $topic_ids ): array {
		$ids = array();

		foreach ( $topic_ids as $topic_id ) {
			foreach ( $this->rest->get_topic_tags( (int) $topic_id ) as $term ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * One topic's tags.
	 *
	 * @since 0.6.0
	 *
	 * @param int            $topic_id Topic ID.
	 * @param array<int,int> $counts   Visible counts, keyed by term ID.
	 * @return array<int,array{id:int,slug:string,name:string,count:int}>
	 */
	public function topic_tags( int $topic_id, array $counts ): array {
		return $this->tags( $this->rest->get_topic_tags( $topic_id ), $counts );
	}

	/**
	 * A list of terms.
	 *
	 * @since 0.6.0
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
