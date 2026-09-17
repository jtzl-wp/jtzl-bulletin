<?php
/**
 * REST topic-tag validation.
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
 * Normalizes tag names under the stricter REST contract.
 */
class TopicTagValidator {

	private const MAX_NAME_LENGTH = 200;

	/**
	 * Data contract.
	 *
	 * @param string[] $names Names as the request carried them.
	 * @return string[]|\WP_Error
	 */
	public function normalize( array $names ) {
		$normalized = array();
		$seen       = array();

		foreach ( $names as $name ) {
			$name = trim( $name );

			if ( '' === $name || mb_strlen( $name, '8bit' ) > self::MAX_NAME_LENGTH ) {
				return new \WP_Error(
					'invalid_tags',
					__( 'One of those tags cannot be used.', 'jtzls-bulletin-for-bbpress' ),
					array( 'status' => 400 )
				);
			}

			$key = mb_strtolower( $name );

			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$normalized[] = $name;
			}
		}

		return $normalized;
	}
}
