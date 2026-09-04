<?php
/**
 * Where a reply sits in the thread it belongs to.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\ReplyQuery;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * A reply's 1-based index in this reader's reading order, and the page that holds it.
 */
class ReplyPositions {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private CollectionVisibility $visibility;

	private ReplyQuery $replies;

	private array $flat = array();

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		CollectionVisibility $visibility,
		ReplyQuery $replies
	) {
		$this->wp         = $wp;
		$this->rest       = $rest;
		$this->visibility = $visibility;
		$this->replies    = $replies;
	}

	/**
	 * Data contract.
	 *
	 * @param int   $offset    Rows preceding this page: `( page - 1 ) * per_page`.
	 * @param int[] $reply_ids The page, in reading order.
	 * @return array<int,int> Reply ID => 1-based position.
	 */
	public function for_page( int $offset, array $reply_ids ): array {
		$positions = array();
		$rank      = max( 0, $offset );

		foreach ( $reply_ids as $reply_id ) {
			++$rank;
			$positions[ (int) $reply_id ] = $rank;
		}

		return $positions;
	}

	/**
	 * Data contract.
	 *
	 * @param int $reply_id Reply to place.
	 * @return array<int,int|null> Reply ID => 1-based position, or null.
	 */
	public function for_reply( int $reply_id ): array {
		return array( $reply_id => $this->of( $this->wp->get_reply_topic_id( $reply_id ), $reply_id ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int $topic_id Thread being paged.
	 * @param int $reply_id Reply the page must contain.
	 * @param int $per_page Rows per page, already bounded.
	 * @return int|null 1-based page, or null when the reply is not in this reader's thread.
	 */
	public function page_of( int $topic_id, int $reply_id, int $per_page ): ?int {
		$position = $this->of( $topic_id, $reply_id );

		return null === $position ? null : (int) ceil( $position / max( 1, $per_page ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int $topic_id Thread to look in.
	 * @param int $reply_id Reply to place.
	 * @return int|null
	 */
	private function of( int $topic_id, int $reply_id ): ?int {
		if ( $topic_id < 1 || $reply_id < 1 ) {
			return null;
		}

		$index = array_search( $reply_id, $this->order( $topic_id ), true );

		return false === $index ? null : (int) $index + 1;
	}

	/**
	 * Data contract.
	 *
	 * @param int $topic_id Thread to enumerate.
	 * @return int[]
	 */
	private function order( int $topic_id ): array {
		if ( $this->wp->is_thread_replies_active() ) {
			return $this->replies->ordered_ids( $topic_id );
		}

		$key = $topic_id . ':' . $this->wp->get_current_user_id();

		if ( ! isset( $this->flat[ $key ] ) ) {

			$this->flat[ $key ] = $this->rest->query(
				$this->visibility->scope( $this->replies->flat_order_args( $topic_id ) )
			)['ids'];
		}

		return $this->flat[ $key ];
	}
}
