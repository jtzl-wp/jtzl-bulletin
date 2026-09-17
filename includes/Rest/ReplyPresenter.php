<?php
/**
 * What a reply response is, decided once.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Rest;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Adds thread position to reply rows and resolves `?around=` page numbers.
 */
class ReplyPresenter {

	private ReplySerializer $replies;

	private ReplyPositions $positions;

	public function __construct( ReplySerializer $replies, ReplyPositions $positions ) {
		$this->replies   = $replies;
		$this->positions = $positions;
	}

	/**
	 * Data contract.
	 *
	 * @param int $reply_id    Reply to serialize.
	 * @param int $avatar_size Pixels.
	 * @return array<string,mixed>
	 */
	public function reply( int $reply_id, int $avatar_size ): array {
		return $this->replies->reply( $reply_id, $avatar_size, $this->positions->for_reply( $reply_id ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int[] $reply_ids   The page, in reading order.
	 * @param int   $avatar_size Pixels.
	 * @param int   $offset      Rows preceding this page: `( page - 1 ) * per_page`.
	 * @return array<int,array<string,mixed>>
	 */
	public function page( array $reply_ids, int $avatar_size, int $offset ): array {
		return $this->replies->replies( $reply_ids, $avatar_size, $this->positions->for_page( $offset, $reply_ids ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int $topic_id  Thread being paged.
	 * @param int $around    Reply the page must contain, or 0 for none.
	 * @param int $requested The `page` the request asked for.
	 * @param int $per_page  Rows per page, already bounded.
	 * @return int|\WP_Error
	 */
	public function page_number( int $topic_id, int $around, int $requested, int $per_page ) {
		if ( $around < 1 ) {
			return $requested;
		}

		$page = $this->positions->page_of( $topic_id, $around, $per_page );

		return null === $page
			? new \WP_Error(
				'invalid_around',
				__( 'That reply cannot be paged to.', 'jtzl-bulletin' ),
				array( 'status' => 400 )
			)
			: $page;
	}
}
