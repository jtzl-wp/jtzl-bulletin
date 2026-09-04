<?php
/**
 * Whole-handler strict Akismet discard detection.
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
 * Stops only the exact strict-discard branch before bbPress redirects and exits.
 */
final class AkismetDiscardGuard {

	private const BYPASS_HOOK = 'bbp_bypass_spam_enforcement';

	private ContextInterface $wp;

	private RestContextInterface $rest;

	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Data contract.
	 *
	 * @param callable():void $handler Native handler invocation.
	 * @return bool
	 * @throws \RuntimeException When an extension throws during the handler.
	 */
	public function run( callable $handler ): bool {
		$sentinel = new \RuntimeException( 'akismet_discard' );
		$guard    = function ( $bypass, $filtered ) use ( $sentinel ) {
			if ( true === $bypass || ! $this->discarding( $filtered ) ) {
				return $bypass;
			}

			throw $sentinel;
		};
		$depth    = $this->rest->current_filter_depth();

		$this->wp->add_filter( self::BYPASS_HOOK, $guard, PHP_INT_MAX, 2 );

		try {
			$handler();
		} catch ( \RuntimeException $thrown ) {
			if ( $thrown !== $sentinel ) {
				throw $thrown;
			}

			return true;
		} finally {
			$this->wp->remove_filter_callback( self::BYPASS_HOOK, $guard, PHP_INT_MAX );
			$this->rest->unwind_filters( $depth );
		}

		return false;
	}

	private function discarding( $filtered ): bool {
		if ( ! is_array( $filtered ) || ! $this->rest->is_akismet_strict() ) {
			return false;
		}

		$headers = $filtered['bbp_akismet_result_headers'] ?? array();

		return is_array( $headers ) && 'discard' === ( $headers['x-akismet-pro-tip'] ?? '' );
	}
}
