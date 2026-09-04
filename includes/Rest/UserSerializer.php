<?php
/**
 * People, as the app sees them.
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
 * A public profile: `id`, `name`, `avatar`, `topic_count`, `reply_count`,
 * `registered`, `link`.
 */
class UserSerializer {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Data contract.
	 *
	 * @param int $user_id      User ID.
	 * @param int $avatar_size  Pixels.
	 * @param int $topic_count  Topics this reader may see by them.
	 * @param int $reply_count  Replies this reader may see by them.
	 * @return array<string,mixed>|null
	 */
	public function user( int $user_id, int $avatar_size, int $topic_count, int $reply_count ): ?array {
		$user = $this->rest->get_user( $user_id );

		if ( null === $user ) {
			return null;
		}

		return array(
			'id'          => $user_id,
			'name'        => (string) $user->display_name,
			'avatar'      => $this->rest->user_avatar_url( $user_id, $avatar_size ),
			'topic_count' => $topic_count,
			'reply_count' => $reply_count,
			'registered'  => $this->rest->registered_rfc3339( $user_id ),
			'link'        => $this->wp->get_user_profile_url( $user_id ),
		);
	}

	/**
	 * Data contract.
	 *
	 * @param int[] $user_ids    Members to serialize, in order.
	 * @param int   $avatar_size Pixels.
	 * @return array<int,array<string,mixed>>
	 */
	public function summaries( array $user_ids, int $avatar_size ): array {
		$rows = array();

		foreach ( $user_ids as $user_id ) {
			$row = $this->summary( (int) $user_id, $avatar_size );

			if ( null !== $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Data contract.
	 *
	 * @param int $user_id     User ID.
	 * @param int $avatar_size Pixels.
	 * @return array<string,mixed>|null
	 */
	public function summary( int $user_id, int $avatar_size ): ?array {
		$user = $this->rest->get_user( $user_id );

		if ( null === $user ) {
			return null;
		}

		return array(
			'id'     => $user_id,
			'name'   => (string) $user->display_name,
			'avatar' => $this->rest->user_avatar_url( $user_id, $avatar_size ),
			'slug'   => (string) $user->user_nicename,
		);
	}
}
