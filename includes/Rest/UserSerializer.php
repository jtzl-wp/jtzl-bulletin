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
 *
 * ⚠ **Nothing else.** A WP_User carries an email address, a login name, roles and
 * capabilities, and a serializer that spread the object would publish all of them at a
 * public route. The fields are written out one at a time for that reason.
 *
 * ⚠ **The counts are the caller's, not bbPress's.** bbPress keeps per-user topic and
 * reply counters for the whole site, including content in forums this reader cannot
 * open. The collections that answer `/users/{id}/topics` and `/users/{id}/replies`
 * already compute a reader-visible total, so those totals are what a profile reports —
 * a profile whose number disagrees with its own list is telling a visitor how much is
 * being kept from them.
 *
 * @since 0.6.0
 */
class UserSerializer {

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
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp   WordPress/bbPress seam.
	 * @param RestContextInterface $rest REST seam.
	 */
	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * One profile, or null when there is no such person.
	 *
	 * @since 0.6.0
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
}
