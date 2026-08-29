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
 *
 * ## Why this is a class and not a method on a controller
 *
 * Three routes return a User entity: `GET /users/{id}`, `GET /me`, and `PATCH /me`. They
 * are reached by different callers for different reasons, and if each built its own
 * response they could come to disagree — a profile read one way carrying a count the
 * other computes differently, or a 404 that means something subtly different on one
 * route than on its neighbour. The previous arrangement kept them honest by having
 * `/me` call the addressed route's own method; that stopped working when `/me` grew a
 * write, because a controller holding both the read collaborators and the write ones
 * crossed PHPMD's coupling gate.
 *
 * ⚠ **That gate was right, and this is the split it asked for** — the same story as
 * `Rest\WriteFields` being lifted out of `Rest\RequestBounds`. The property worth
 * keeping was never "one controller", it was "one decision", and a collaborator holds
 * that better than a shared method ever did: the decision is now named, and a fourth
 * route returning a profile has somewhere obvious to get one.
 *
 * ## The counts are the collections, asked
 *
 * `topic_count` and `reply_count` are the `X-WP-Total` of the two collection routes
 * beneath a profile, run for a single row. bbPress keeps per-user counters that would
 * answer instantly and they are the wrong number — they count the whole site, so a
 * member with three threads in a forum this reader cannot open would have a profile
 * saying "3" above a list showing none. That difference is small, and it is a
 * disclosure: it tells a visitor precisely how much is being kept from them, per person.
 *
 * @since 0.6.1
 */
class ProfilePresenter {

	/**
	 * Whether this ID names somebody.
	 *
	 * @var AccessPolicy
	 * @since 0.6.1
	 */
	private AccessPolicy $access;

	/**
	 * Collection arguments, for the two totals.
	 *
	 * @var UnanchoredRepository
	 * @since 0.6.1
	 */
	private UnanchoredRepository $collections;

	/**
	 * Profiles.
	 *
	 * @var UserSerializer
	 * @since 0.6.1
	 */
	private UserSerializer $users;

	/**
	 * What comes back.
	 *
	 * @var ResponseFactory
	 * @since 0.6.1
	 */
	private ResponseFactory $responses;

	/**
	 * Constructor.
	 *
	 * @since 0.6.1
	 *
	 * @param AccessPolicy         $access      Whether this ID names somebody.
	 * @param UnanchoredRepository $collections Collection arguments.
	 * @param UserSerializer       $users       Profiles.
	 * @param ResponseFactory      $responses   What comes back.
	 */
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
	 * One member's profile, or the refusal that stands in for them.
	 *
	 * @since 0.6.1
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

		// One row each, for the totals rather than the rows: what a page of this
		// member's work looks like is the two collection routes' job.
		$profile = $this->users->user(
			$user_id,
			$avatar_size,
			$this->collections->user_topics( $user_id, 1, 1 )['total'],
			$this->collections->user_replies( $user_id, 1, 1 )['total']
		);

		// The policy has just established that this ID names somebody, so the
		// serializer's null is unreachable here — and it is answered by asking the
		// policy again rather than by a second 404 written out, so there stays one
		// place that decides what a missing thing looks like.
		return null === $profile ? $this->access->user( 0 ) : $this->responses->item( $profile );
	}
}
