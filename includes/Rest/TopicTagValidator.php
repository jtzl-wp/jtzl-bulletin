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
 *
 * @since 0.6.0
 */
class TopicTagValidator {

	/**
	 * The width of WordPress's term-name column.
	 *
	 * @var int
	 */
	private const MAX_NAME_LENGTH = 200;

	/**
	 * Normalize tag names, or explain why they cannot be used.
	 *
	 * Names are trimmed and case-insensitive duplicates collapse to the first spelling.
	 * A blank name is refused rather than silently dropped because the request meant to
	 * assign a tag. An absent or empty list remains valid.
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
					__( 'One of those tags cannot be used.', 'jtzl-bulletin' ),
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
