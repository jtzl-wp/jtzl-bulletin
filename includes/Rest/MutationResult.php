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
 */
final class MutationResult {

	private ?int $id;

	private function __construct( ?int $id ) {
		$this->id = $id;
	}

	public static function entity( int $id ): self {
		return new self( $id );
	}

	public static function accepted(): self {
		return new self( null );
	}

	public function is_accepted(): bool {
		return null === $this->id;
	}

	/**
	 * Data contract.
	 *
	 * @return int|null
	 */
	public function id(): ?int {
		return $this->id;
	}
}
