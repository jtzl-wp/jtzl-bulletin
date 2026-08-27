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
 *
 * @since 0.6.0
 */
class ReplyMutationService {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * REST-side WordPress/bbPress seam.
	 *
	 * @var RestContextInterface
	 */
	private RestContextInterface $rest;

	/**
	 * ID-addressed visibility and reply-parent policy.
	 *
	 * @var AccessPolicy
	 */
	private AccessPolicy $access;

	/**
	 * Scoped native form-handler bridge.
	 *
	 * @var BbpFormHandlerBridge
	 */
	private BbpFormHandlerBridge $bridge;

	/**
	 * Constructor.
	 *
	 * @param ContextInterface     $wp     WordPress/bbPress seam.
	 * @param RestContextInterface $rest   REST-side WordPress/bbPress seam.
	 * @param AccessPolicy         $access ID-addressed REST policy.
	 * @param BbpFormHandlerBridge $bridge Native handler bridge.
	 */
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
	 * Answer a thread.
	 *
	 * The author ID remains part of the stable service contract. The native handler
	 * obtains the authoritative author from WordPress's current-user context.
	 *
	 * The publish capability is deliberately preflighted before visibility. This keeps
	 * the REST contract's narrower answer for a spectator, while the native handler
	 * remains authoritative and checks the capability again inside the write lifecycle.
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
				__( 'You cannot post replies.', 'jtzl-bulletin' ),
				array( 'status' => 403 )
			);
		}

		$allowed = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
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
