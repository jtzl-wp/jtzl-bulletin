<?php
/**
 * Replies, as the app sees them.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * A reply row. Flat, always — the relationship travels in `reply_to` and the API never
 * nests or indents.
 */
class ReplySerializer {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private AuthorSerializer $authors;

	private AccessPolicy $access;

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		AuthorSerializer $authors,
		AccessPolicy $access
	) {
		$this->wp      = $wp;
		$this->rest    = $rest;
		$this->authors = $authors;
		$this->access  = $access;
	}

	/**
	 * Data contract.
	 *
	 * @param int[]               $reply_ids   Replies, in reading order.
	 * @param int                 $avatar_size Pixels.
	 * @param array<int,int|null> $positions   Reply ID => position, for a thread-scoped route.
	 * @return array<int,array<string,mixed>>
	 */
	public function replies( array $reply_ids, int $avatar_size, array $positions = array() ): array {
		$reply_ids = array_values( array_map( 'intval', $reply_ids ) );
		$authors   = $this->authors->authors( $reply_ids, $avatar_size );

		$rows = array();
		foreach ( $reply_ids as $reply_id ) {
			$rows[] = $this->reply_data( $reply_id, $authors[ $reply_id ] ?? null, $positions );
		}

		return $rows;
	}

	/**
	 * Data contract.
	 *
	 * @param int                 $reply_id    Reply ID.
	 * @param int                 $avatar_size Pixels.
	 * @param array<int,int|null> $positions   Reply ID => position, for a thread-scoped route.
	 * @return array<string,mixed>
	 */
	public function reply( int $reply_id, int $avatar_size, array $positions = array() ): array {
		$rows = $this->replies( array( $reply_id ), $avatar_size, $positions );

		return $rows[0];
	}

	/**
	 * Data contract.
	 *
	 * @param int                      $reply_id  Reply ID.
	 * @param array<string,mixed>|null $author    Primed author.
	 * @param array<int,int|null>      $positions Reply ID => position, for a thread-scoped route.
	 * @return array<string,mixed>
	 */
	private function reply_data( int $reply_id, ?array $author, array $positions ): array {
		$topic_id = $this->wp->get_reply_topic_id( $reply_id );

		$row = array(
			'id'       => $reply_id,
			'topic_id' => $topic_id,
			'reply_to' => $this->reply_to( $reply_id, $topic_id ),
			'author'   => $author,
			'created'  => $this->rest->post_date_rfc3339( $reply_id ),
			'content'  => $this->rest->rendered_reply_content( $reply_id ),
			'status'   => $this->rest->normalize_status( $this->wp->get_post_status( $reply_id ) ),
			'link'     => $this->wp->get_reply_url( $reply_id ),
			'can_edit' => $this->access->can_edit_reply( $reply_id ),
		);

		if ( array_key_exists( $reply_id, $positions ) ) {
			$row['position'] = $positions[ $reply_id ];
		}

		return $row;
	}

	/**
	 * Data contract.
	 *
	 * @param int $reply_id Reply being serialized.
	 * @param int $topic_id Its thread.
	 * @return int|null
	 */
	private function reply_to( int $reply_id, int $topic_id ): ?int {
		$target = $this->wp->get_reply_to( $reply_id );

		if ( $target < 1 ) {
			return null;
		}

		return true === $this->access->reply_to( $target, $topic_id, $reply_id ) ? $target : null;
	}
}
