<?php
/**
 * What a profile response is, decided once.
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
 * The single answer to "serialize this member", for every route that returns one.
 */
class ProfilePresenter {

	private AccessPolicy $access;

	private UnanchoredRepository $collections;

	private UserSerializer $users;

	private ResponseFactory $responses;

	public function __construct(
		AccessPolicy $access,
		UnanchoredRepository $collections,
		UserSerializer $users,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->users       = $users;
		$this->responses   = $responses;
	}

	/**
	 * Data contract.
	 *
	 * @param int $user_id     Member ID.
	 * @param int $avatar_size Pixels.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function present( int $user_id, int $avatar_size ) {
		$allowed = $this->access->user( $user_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$profile = $this->users->user(
			$user_id,
			$avatar_size,
			$this->collections->user_topics( $user_id, 1, 1 )['total'],
			$this->collections->user_replies( $user_id, 1, 1 )['total']
		);

		return null === $profile ? $this->access->user( 0 ) : $this->responses->item( $profile );
	}
}
