<?php
/**
 * Editing your own profile over the REST API.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Applies this API's profile-write policy, then stores the one field it accepts.
 */
class ProfileEditService {

	/**
	 * MySQL silently truncates display names beyond 250 characters. Validate with
	 * mb_strlen() so multibyte names use the database's character limit, not a byte limit.
	 */
	private const NAME_MAX_LENGTH = 250;

	private RestContextInterface $rest;

	public function __construct( RestContextInterface $rest ) {
		$this->rest = $rest;
	}

	/**
	 * Data contract.
	 *
	 * @param int                  $user_id Member being edited — always the caller.
	 * @param array<string,string> $changes Fields the request actually carried.
	 * @return true|\WP_Error
	 */
	public function update( int $user_id, array $changes ) {
		if ( ! array_key_exists( 'name', $changes ) ) {
			return $this->refuse(
				'invalid_request',
				__( 'An edit has to change something.', 'jtzls-bulletin-for-bbpress' ),
				400
			);
		}

		$name = trim( $changes['name'] );

		if ( '' === $name ) {
			return $this->refuse(
				'invalid_name',
				__( 'A display name cannot be empty.', 'jtzls-bulletin-for-bbpress' ),
				400
			);
		}

		if ( mb_strlen( $name, 'utf-8' ) > self::NAME_MAX_LENGTH ) {
			return $this->refuse(
				'invalid_name',
				__( 'That display name is too long.', 'jtzls-bulletin-for-bbpress' ),
				400
			);
		}

		if ( ! $this->rest->update_display_name( $user_id, $name ) ) {

			return $this->refuse(
				'write_failed',
				__( 'The profile could not be saved.', 'jtzls-bulletin-for-bbpress' ),
				500
			);
		}

		return true;
	}

	private function refuse( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
