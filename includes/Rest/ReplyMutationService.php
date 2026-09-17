<?php
/**
 * Answering a thread over the REST API.
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
 * Applies REST-only policy, then delegates the shared write lifecycle to bbPress.
 */
class ReplyMutationService {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private AccessPolicy $access;

	private BbpFormHandlerBridge $bridge;

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		AccessPolicy $access,
		BbpFormHandlerBridge $bridge
	) {
		$this->wp     = $wp;
		$this->rest   = $rest;
		$this->access = $access;
		$this->bridge = $bridge;
	}

	/**
	 * Data contract.
	 *
	 * @param int    $topic_id  Thread being answered.
	 * @param int    $author_id Authenticated author ID.
	 * @param string $content   Body as the app sent it.
	 * @param int    $reply_to  Reply being answered, or zero for the thread.
	 * @return MutationResult|\WP_Error
	 */
	public function create( int $topic_id, int $author_id, string $content, int $reply_to ) {
		if ( ! $this->wp->current_user_can( 'publish_replies' ) ) {
			return new \WP_Error(
				'forbidden',
				__( 'You cannot post replies.', 'jtzls-bulletin-for-bbpress' ),
				array( 'status' => 403 )
			);
		}

		$allowed = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		// bbPress's own rule, asked here so the contract's answer does not depend on
		// which of bbPress's checks fires first: 2.6.17 asks `read_topic` before the
		// closed check, and a closed thread fails that for a member, so the handler
		// would report "cannot read" where the contract promises "closed".
		if ( $this->wp->is_topic_closed( $topic_id ) && ! $this->wp->current_user_can_moderate( $topic_id ) ) {
			return new \WP_Error(
				'forbidden',
				__( 'This thread is closed.', 'jtzls-bulletin-for-bbpress' ),
				array( 'status' => 403 )
			);
		}

		if ( $reply_to > 0 ) {
			$allowed = $this->access->reply_to( $reply_to, $topic_id );

			if ( true !== $allowed ) {
				return $allowed;
			}
		}

		return $this->bridge->run(
			'bbp-new-reply',
			0,
			array(
				'bbp_topic_id'      => (string) $topic_id,
				'bbp_forum_id'      => (string) $this->wp->get_topic_forum_id( $topic_id ),
				'bbp_reply_content' => $this->rest->slash( $content ),
				'bbp_reply_to'      => (string) $reply_to,
			)
		);
	}
}
