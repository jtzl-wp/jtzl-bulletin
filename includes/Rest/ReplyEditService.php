<?php
/**
 * Editing a reply over the REST API.
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
 * Applies REST-only PATCH policy, then delegates the write to bbPress.
 *
 * @since 0.6.0
 */
class ReplyEditService {

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
	 * @param BbpFormHandlerBridge $bridge Native handler bridge.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		BbpFormHandlerBridge $bridge
	) {
		$this->wp     = $wp;
		$this->rest   = $rest;
		$this->bridge = $bridge;
	}

	/**
	 * Edit a reply as its author.
	 *
	 * The author ID remains part of the stable service contract. The native handler
	 * obtains the authoritative author from the stored reply and current-user context.
	 *
	 * @param int                 $reply_id  Reply being edited.
	 * @param int                 $author_id Authenticated author ID.
	 * @param array<string,mixed> $changes   Fields the request actually carried.
	 * @return MutationResult|\WP_Error
	 */
	public function update( int $reply_id, int $author_id, array $changes ) {
		if ( ! array_key_exists( 'content', $changes ) ) {
			return new \WP_Error(
				'invalid_request',
				__( 'An edit has to change something.', 'jtzl-bulletin' ),
				array( 'status' => 400 )
			);
		}

		$content     = $changes['content'];
		$form_values = array(
			'bbp_reply_id'      => (string) $reply_id,
			'bbp_reply_content' => $this->rest->slash( is_scalar( $content ) ? (string) $content : '' ),
		);

		if ( $this->wp->is_thread_replies_active() || $this->wp->get_reply_to( $reply_id ) <= 0 ) {
			return $this->bridge->run( 'bbp-edit-reply', $reply_id, $form_values );
		}

		$preserve_parent = static fn(): bool => true;
		$this->wp->add_filter( 'bbp_thread_replies', $preserve_parent, PHP_INT_MAX );

		try {
			return $this->bridge->run( 'bbp-edit-reply', $reply_id, $form_values );
		} finally {
			$this->wp->remove_filter_callback( 'bbp_thread_replies', $preserve_parent, PHP_INT_MAX );
		}
	}
}
