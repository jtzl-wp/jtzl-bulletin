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
 * A reply row **with its place in the thread**, for every route where a thread is the
 * context — and the resolution of `?around=` into the page number that holds one.
 *
 * ## Why this is a class and not two collaborators on each controller
 *
 * Four responses carry a position: the rows of `GET /topics/{id}/replies`, the entity on
 * `GET /replies/{id}`, and the two reply write responses. They are split across
 * Rest\ReplyController and Rest\TopicRepliesController, and handing both of those a
 * serializer *and* a position source put both over PHPMD's coupling gate — the same
 * gate, and the same answer, as Rest\ProfilePresenter two tasks ago.
 *
 * ⚠ **The gate was right again, and for a better reason than arithmetic.** Which
 * responses carry `position` is one decision, and it was about to be spelled out at four
 * call sites in two classes. A fifth route returning a reply now has one obvious place
 * to get one, and cannot accidentally return the field computed a different way — or
 * return it where its cost is not worth paying (see Rest\ReplySerializer on why a member's
 * replies and a search result deliberately have no position).
 *
 * ## Two ways to a position, and which one a caller gets is not its choice
 *
 * `page()` is free and `reply()` costs a reading-order build, for the reason
 * Rest\ReplyPositions sets out: a page *is* a slice of the order at a known offset, and a
 * single reply is not. Callers ask for what they have — a page, or a reply — and the cost
 * follows from that rather than from a flag anybody has to remember to set.
 *
 * @since 0.6.1
 */
class ReplyPresenter {

	/**
	 * Reply rows.
	 *
	 * @var ReplySerializer
	 * @since 0.6.1
	 */
	private ReplySerializer $replies;

	/**
	 * Where a reply sits in its thread.
	 *
	 * @var ReplyPositions
	 * @since 0.6.1
	 */
	private ReplyPositions $positions;

	/**
	 * Constructor.
	 *
	 * @since 0.6.1
	 *
	 * @param ReplySerializer $replies   Reply rows.
	 * @param ReplyPositions  $positions Where a reply sits in its thread.
	 */
	public function __construct( ReplySerializer $replies, ReplyPositions $positions ) {
		$this->replies   = $replies;
		$this->positions = $positions;
	}

	/**
	 * One reply, placed in its own thread.
	 *
	 * @since 0.6.1
	 *
	 * @param int $reply_id    Reply to serialize.
	 * @param int $avatar_size Pixels.
	 * @return array<string,mixed>
	 */
	public function reply( int $reply_id, int $avatar_size ): array {
		return $this->replies->reply( $reply_id, $avatar_size, $this->positions->for_reply( $reply_id ) );
	}

	/**
	 * One page of a thread, every row placed by where the page starts.
	 *
	 * @since 0.6.1
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
	 * Which page of a thread to serve, given what the request asked for.
	 *
	 * ⚠ **`around` wins and `page` is ignored when both are sent.** They are two ways of
	 * naming one page and there is no reconciliation worth inventing, so the more specific
	 * one answers. The page it chose is discoverable without a header the client has to
	 * learn about: every row carries its `position`.
	 *
	 * ⚠ **One refusal for every way `around` can be wrong** — the ID names nothing, names
	 * a post that is not a reply, names a reply in another thread, or names one this
	 * reader may not see. That is Rest\AccessPolicy::reply_to()'s rule applied to a read
	 * route, and for the same reason: told apart, these answer "does reply 4211 exist, and
	 * where does it live" for anybody able to open one public thread.
	 *
	 * ⚠ **And no access check enforces it, deliberately.** The reading order *is* the
	 * visibility-filtered set, so a target this reader may not see is absent from it
	 * exactly as a nonexistent one is — while an author's own held reply is present, and
	 * is a valid target for that author and for nobody else, without a branch anywhere
	 * saying so. A second capability test here would be a second opinion to keep in step
	 * with the first.
	 *
	 * @since 0.6.1
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
