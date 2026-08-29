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
 *
 * ⚠ **`reply_to` is checked, not copied.** bbPress stores whatever ID was posted, and
 * imports and moved threads leave pointers at replies in other threads, at replies
 * that were deleted, and at replies this reader may not see. Passing one through would
 * confirm that a reply exists to anyone who can read a thread. So every pointer is put
 * through the same access policy the singular route uses, and anything that fails
 * becomes null.
 *
 * ⚠ **`position` is handed in, never computed here.** It is a reply's place in *this
 * reader's* reading order of *one* thread, so it exists only where a thread is the
 * context: the rows of `/topics/{id}/replies`, `/replies/{id}`, and the two reply write
 * responses. A member's replies and a search result are lists of replies from arbitrary
 * threads, and a serializer that resolved a position per row would build one reading
 * order per row — an unbounded query each, for a field neither route has a use for. So
 * the field is absent there, and its cost stays a decision the controller makes with
 * Rest\ReplyPositions rather than one this class takes on everybody's behalf.
 *
 * @since 0.6.0
 */
class ReplySerializer {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Authors.
	 *
	 * @var AuthorSerializer
	 * @since 0.6.0
	 */
	private AuthorSerializer $authors;

	/**
	 * Access and edit policy.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp      WordPress/bbPress seam.
	 * @param RestContextInterface $rest    REST seam.
	 * @param AuthorSerializer     $authors Authors.
	 * @param AccessPolicy         $access  Access and edit policy.
	 */
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
	 * A page of replies.
	 *
	 * @since 0.6.0
	 * @since 0.6.1 Optional positions, for a thread-scoped route.
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
	 * One reply.
	 *
	 * @since 0.6.0
	 * @since 0.6.1 Optional positions, for a thread-scoped route.
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
	 * One reply's fields.
	 *
	 * @since 0.6.0
	 * @since 0.6.1 Optional positions, for a thread-scoped route.
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

		// ⚠ `array_key_exists`, not `isset` or `??`: a null position is an answer — "this
		// route reports positions and could not place this reply" — and both of those
		// spellings would drop the key instead, turning it into the other answer.
		if ( array_key_exists( $reply_id, $positions ) ) {
			$row['position'] = $positions[ $reply_id ];
		}

		return $row;
	}

	/**
	 * The reply this one answers, when the app may be told about it.
	 *
	 * @since 0.6.0
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
