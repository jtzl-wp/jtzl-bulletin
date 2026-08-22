<?php
/**
 * The four things a member may change about their own relationship to a thread.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Unread\ReadCursor;
use JTZL\Bulletin\Unread\ReadState;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Read position, favourite, topic subscription, forum subscription — set, and cleared.
 *
 * ## Why the policy is here and not in the controllers
 *
 * Every method asks the same three questions in the same order — is the feature on,
 * may this member have this resource, and is the relationship already where they want
 * it — and only then writes. Four routes each asking that for themselves is four
 * chances to leave one out, and the one left out would be a route that stored a
 * favourite against a thread in a forum the member cannot open. So the controllers
 * hold no policy at all: they read the ID, read the verb, and hand both over.
 *
 * ## Idempotent means "ask first", not "write twice"
 *
 * bbPress's `bbp_add_user_favorite()` and its three siblings return **false** when the
 * relationship already exists — the bail-out is the second line of each of them. A
 * service that treated false as failure would answer 500 to the one request that is
 * most obviously fine: the retry a phone sends after a flaky connection. So the
 * current state is read first and a request that asks for what is already true
 * succeeds without touching anything. False after that check is a real write failure
 * and is reported as one.
 *
 * ## The read cursor is checked against the thread it names
 *
 * Unread\ReadCursor::parse() is given the topic from the *path*, and refuses a token
 * issued for a different one. That is what stops a cursor from one thread silencing
 * another, and it is why the topic ID travels inside the signed payload rather than
 * being taken on trust from the request.
 *
 * ⚠ **Nothing here compares positions.** An older cursor arriving after a newer one —
 * two requests, one flaky connection, either order — is settled inside
 * Unread\ReadState::mark_topic_read()'s single statement, where two concurrent writers
 * cannot race between a read and a write. A guard here would be a second opinion that
 * is wrong under exactly the conditions it was added for.
 *
 * @since 0.6.0
 */
class StateService {

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Who may have what, and which features are on.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

	/**
	 * Whether favouriting and subscribing exist on this forum at all.
	 *
	 * @var FeatureGate
	 * @since 0.6.0
	 */
	private FeatureGate $features;

	/**
	 * Per-reader read state.
	 *
	 * @var ReadState
	 * @since 0.6.0
	 */
	private ReadState $reads;

	/**
	 * Signed read positions.
	 *
	 * @var ReadCursor
	 * @since 0.6.0
	 */
	private ReadCursor $cursor;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param RestContextInterface $rest     REST seam.
	 * @param AccessPolicy         $access   Who may have what.
	 * @param FeatureGate          $features Which features are on.
	 * @param ReadState            $reads    Per-reader read state.
	 * @param ReadCursor           $cursor   Signed read positions.
	 */
	public function __construct(
		RestContextInterface $rest,
		AccessPolicy $access,
		FeatureGate $features,
		ReadState $reads,
		ReadCursor $cursor
	) {
		$this->rest     = $rest;
		$this->access   = $access;
		$this->features = $features;
		$this->reads    = $reads;
		$this->cursor   = $cursor;
	}

	/**
	 * Record how far a member has read in a thread.
	 *
	 * ⚠ **The thread is ruled on before the token is parsed.** A cursor is only ever
	 * issued by a response the member was allowed to have, so a valid one for a thread
	 * they may not open should not exist — but the check is the cheap half of the pair
	 * and refusing first means a revoked reader cannot keep writing rows about a forum
	 * that closed to them, using a token they were legitimately given last week.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $topic_id Thread being marked.
	 * @param int    $user_id  Member marking it.
	 * @param string $token    The `read_cursor` the app was given with the thread.
	 * @return true|\WP_Error
	 */
	public function mark_read( int $topic_id, int $user_id, string $token ) {
		$allowed = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$position = $this->cursor->parse( $token, $topic_id );

		if ( null === $position ) {
			return new \WP_Error(
				'invalid_read_cursor',
				__( 'The read cursor is invalid.', 'jtzl-bulletin' ),
				array( 'status' => 400 )
			);
		}

		$this->reads->mark_topic_read( $topic_id, $user_id, $position['read_time'], $position['read_id'] );

		return true;
	}

	/**
	 * Favourite a thread, or take it out of the member's favourites.
	 *
	 * @since 0.6.0
	 *
	 * @param int  $topic_id Thread.
	 * @param int  $user_id  Member.
	 * @param bool $enabled  Whether the relationship should exist afterwards.
	 * @return true|\WP_Error
	 */
	public function set_favorite( int $topic_id, int $user_id, bool $enabled ) {
		$allowed = $this->features->favorites();

		if ( true !== $allowed ) {
			return $allowed;
		}

		$allowed = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		if ( $this->rest->is_favorite( $user_id, $topic_id ) === $enabled ) {
			return true;
		}

		$written = $enabled
			? $this->rest->add_favorite( $user_id, $topic_id )
			: $this->rest->remove_favorite( $user_id, $topic_id );

		return $written ? true : $this->write_failed();
	}

	/**
	 * Subscribe a member to a thread, or unsubscribe them.
	 *
	 * @since 0.6.0
	 *
	 * @param int  $topic_id Thread.
	 * @param int  $user_id  Member.
	 * @param bool $enabled  Whether the relationship should exist afterwards.
	 * @return true|\WP_Error
	 */
	public function set_topic_subscription( int $topic_id, int $user_id, bool $enabled ) {
		$allowed = $this->features->subscriptions();

		if ( true !== $allowed ) {
			return $allowed;
		}

		$allowed = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		return $this->subscribe( $topic_id, $user_id, $enabled );
	}

	/**
	 * Subscribe a member to a forum, or unsubscribe them.
	 *
	 * @since 0.6.0
	 *
	 * @param int  $forum_id Forum.
	 * @param int  $user_id  Member.
	 * @param bool $enabled  Whether the relationship should exist afterwards.
	 * @return true|\WP_Error
	 */
	public function set_forum_subscription( int $forum_id, int $user_id, bool $enabled ) {
		$allowed = $this->features->subscriptions();

		if ( true !== $allowed ) {
			return $allowed;
		}

		$allowed = $this->access->forum( $forum_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		return $this->subscribe( $forum_id, $user_id, $enabled );
	}

	/**
	 * The half of a subscription that does not care what it is subscribed to.
	 *
	 * One method for a forum and a thread because bbPress stores one relationship for
	 * both, against an object ID that never says which kind it is. The two public
	 * methods differ only in which visibility question they ask first, and that is the
	 * whole of the difference.
	 *
	 * @since 0.6.0
	 *
	 * @param int  $object_id Forum or thread, already ruled on.
	 * @param int  $user_id   Member.
	 * @param bool $enabled   Whether the relationship should exist afterwards.
	 * @return true|\WP_Error
	 */
	private function subscribe( int $object_id, int $user_id, bool $enabled ) {
		if ( $this->rest->is_subscribed( $user_id, $object_id ) === $enabled ) {
			return true;
		}

		$written = $enabled
			? $this->rest->add_subscription( $user_id, $object_id )
			: $this->rest->remove_subscription( $user_id, $object_id );

		return $written ? true : $this->write_failed();
	}

	/**
	 * The answer for a write bbPress refused for a reason it did not give.
	 *
	 * Deliberately one error for every such failure. By the time it can be reached the
	 * feature is on, the member may have the resource, and the relationship is not
	 * already where they asked for it — so what is left is a storage failure, and
	 * naming which one would be describing an engagement strategy the app has no
	 * business knowing about.
	 *
	 * @since 0.6.0
	 *
	 * @return \WP_Error
	 */
	private function write_failed(): \WP_Error {
		return new \WP_Error(
			'write_failed',
			__( 'That change could not be saved.', 'jtzl-bulletin' ),
			array( 'status' => 500 )
		);
	}
}
