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
 */
class StateService {

	private RestContextInterface $rest;

	private AccessPolicy $access;

	private FeatureGate $features;

	private ReadState $reads;

	private ReadCursor $cursor;

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
	 * Data contract.
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
				__( 'The read cursor is invalid.', 'jtzls-bulletin-for-bbpress' ),
				array( 'status' => 400 )
			);
		}

		$this->reads->mark_topic_read( $topic_id, $user_id, $position['read_time'], $position['read_id'] );

		return true;
	}

	/**
	 * Data contract.
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
	 * Data contract.
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
	 * Data contract.
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
	 * Data contract.
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

	private function write_failed(): \WP_Error {
		return new \WP_Error(
			'write_failed',
			__( 'That change could not be saved.', 'jtzls-bulletin-for-bbpress' ),
			array( 'status' => 500 )
		);
	}
}
