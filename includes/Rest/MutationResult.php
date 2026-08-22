<?php
/**
 * What a write ended as.
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
 * A write that succeeded, in one of the only two shapes the contract has for one.
 *
 * ## Two outcomes, and no third
 *
 * Either the caller is handed the thing they wrote — `entity()`, carrying its ID — or
 * they are told the site accepted the request and nothing else. `accepted()` is that
 * second answer, and it is deliberately empty: it is what a strict Akismet discard, a
 * pre-insert filter that turned the post to spam, and one that turned it to trash all
 * come back as, in identical bytes.
 *
 * ⚠ **The identical bytes are the point.** Those three outcomes differ in ways the
 * author is not told apart — whether anything was stored, and under what status —
 * because the difference is precisely what somebody probing the spam filter would tune
 * against. A body saying "held as spam" is a free oracle for how to get past it.
 *
 * A *failed* write is not represented here at all. It is a `WP_Error`, returned instead
 * of a MutationResult, so a caller cannot reach for an ID that a failure never had.
 *
 * ## Why a type rather than a nullable ID
 *
 * `accepted()` and `entity( 0 )` would be the same value if this were an int, and they
 * are not the same event. `is_accepted()` answers the question the controller actually
 * asks — "am I serializing something, or am I answering 202?" — without a caller having
 * to know that zero means one of them.
 *
 * @since 0.6.0
 */
final class MutationResult {

	/**
	 * The post that was written, when the caller is allowed to know about it.
	 *
	 * @var int|null
	 * @since 0.6.0
	 */
	private ?int $id;

	/**
	 * Constructor.
	 *
	 * Private: the two named constructors below are the whole vocabulary, and a third
	 * shape reached by passing something unusual here is exactly what the type exists
	 * to prevent.
	 *
	 * @since 0.6.0
	 *
	 * @param int|null $id Post written, or null for an acknowledgement.
	 */
	private function __construct( ?int $id ) {
		$this->id = $id;
	}

	/**
	 * A write the caller is handed back.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Post that was created or edited.
	 * @return self
	 */
	public static function entity( int $id ): self {
		return new self( $id );
	}

	/**
	 * A write the caller is told nothing further about.
	 *
	 * @since 0.6.0
	 *
	 * @return self
	 */
	public static function accepted(): self {
		return new self( null );
	}

	/**
	 * Whether this is the acknowledgement rather than an entity.
	 *
	 * @since 0.6.0
	 *
	 * @return bool
	 */
	public function is_accepted(): bool {
		return null === $this->id;
	}

	/**
	 * The post that was written, or null when there is nothing to hand back.
	 *
	 * @since 0.6.0
	 *
	 * @return int|null
	 */
	public function id(): ?int {
		return $this->id;
	}
}
