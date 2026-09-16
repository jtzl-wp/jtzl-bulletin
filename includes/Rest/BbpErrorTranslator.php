<?php
/**
 * Native bbPress mutation errors in Bulletin's REST vocabulary.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Translates the first form-handler error without inferring meaning from its message.
 */
final class BbpErrorTranslator {

	private const MAPPING = array(
		'bbp_topic_title'               => array( 'invalid_title', 400 ),
		'bbp_reply_title'               => array( 'invalid_title', 400 ),
		'bbp_edit_topic_title'          => array( 'invalid_title', 400 ),
		'bbp_topic_content'             => array( 'invalid_content', 400 ),
		'bbp_reply_content'             => array( 'invalid_content', 400 ),
		'bbp_edit_topic_content'        => array( 'invalid_content', 400 ),
		'bbp_edit_reply_content'        => array( 'invalid_content', 400 ),
		'bbp_topic_tags'                => array( 'invalid_tags', 400 ),
		'bbp_reply_tags'                => array( 'invalid_tags', 400 ),
		'bbp_topic_moderation'          => array( 'moderation_rejected', 400 ),
		'bbp_reply_moderation'          => array( 'moderation_rejected', 400 ),
		'bbp_topic_permission'          => array( 'forbidden', 403 ),
		'bbp_reply_permission'          => array( 'forbidden', 403 ),
		'bbp_edit_topic_permission'     => array( 'forbidden', 403 ),
		'bbp_edit_reply_permission'     => array( 'forbidden', 403 ),
		'bbp_new_topic_forum_category'  => array( 'forbidden', 403 ),
		'bbp_new_reply_forum_category'  => array( 'forbidden', 403 ),
		'bbp_edit_topic_forum_category' => array( 'forbidden', 403 ),
		'bbp_edit_reply_forum_category' => array( 'forbidden', 403 ),
		'bbp_new_topic_forum_closed'    => array( 'forbidden', 403 ),
		'bbp_new_reply_forum_closed'    => array( 'forbidden', 403 ),
		'bbp_edit_topic_forum_closed'   => array( 'forbidden', 403 ),
		'bbp_edit_reply_forum_closed'   => array( 'forbidden', 403 ),
		'bbp_reply_topic_closed'        => array( 'forbidden', 403 ),
		'bbp_new_topic_status'          => array( 'forbidden', 403 ),
		'bbp_edit_topic_status'         => array( 'forbidden', 403 ),
		'bbp_edit_reply_status'         => array( 'forbidden', 403 ),
		'bbp_topic_forum_id'            => array( 'not_found', 404 ),
		'bbp_reply_topic_id'            => array( 'not_found', 404 ),
		'bbp_reply_forum_id'            => array( 'not_found', 404 ),
		'bbp_edit_topic_id'             => array( 'not_found', 404 ),
		'bbp_edit_topic_not_found'      => array( 'not_found', 404 ),
		'bbp_edit_reply_id'             => array( 'not_found', 404 ),
		'bbp_edit_reply_not_found'      => array( 'not_found', 404 ),
		'bbp_new_topic_forum_private'   => array( 'not_found', 404 ),
		'bbp_new_topic_forum_hidden'    => array( 'not_found', 404 ),
		'bbp_new_reply_forum_private'   => array( 'not_found', 404 ),
		'bbp_new_reply_forum_hidden'    => array( 'not_found', 404 ),
		// bbPress 2.6.17 folds the two rows above into one, and asks `read_topic`
		// before anything else; ≤2.6.16 never raises either code.
		'bbp_new_reply_forum_read'      => array( 'not_found', 404 ),
		'bbp_new_reply_topic_public'    => array( 'forbidden', 403 ),
		'bbp_edit_topic_forum_private'  => array( 'not_found', 404 ),
		'bbp_edit_topic_forum_hidden'   => array( 'not_found', 404 ),
		'bbp_edit_reply_forum_private'  => array( 'not_found', 404 ),
		'bbp_edit_reply_forum_hidden'   => array( 'not_found', 404 ),
		'bbp_topic_duplicate'           => array( 'duplicate_post', 409 ),
		'bbp_reply_duplicate'           => array( 'duplicate_post', 409 ),
		'bbp_topic_flood'               => array( 'rate_limited', 429 ),
		'bbp_reply_flood'               => array( 'rate_limited', 429 ),
		'bbp_topic_error'               => array( 'write_failed', 500 ),
		'bbp_reply_error'               => array( 'write_failed', 500 ),
		'bbp_new_topic_nonce'           => array( 'write_failed', 500 ),
		'bbp_new_reply_nonce'           => array( 'write_failed', 500 ),
		'bbp_edit_topic_nonce'          => array( 'write_failed', 500 ),
		'bbp_edit_reply_nonce'          => array( 'write_failed', 500 ),
	);

	private RestContextInterface $rest;

	public function __construct( RestContextInterface $rest ) {
		$this->rest = $rest;
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_Error $errors Native errors.
	 * @return \WP_Error|null
	 */
	public function first( \WP_Error $errors ): ?\WP_Error {
		$native_code = (string) $errors->get_error_code();

		if ( '' === $native_code ) {
			return null;
		}

		$message = $this->rest->strip_markup( (string) $errors->get_error_message( $native_code ) );

		if ( ! isset( self::MAPPING[ $native_code ] ) ) {
			return new \WP_Error(
				'write_rejected',
				'' === $message ? __( 'This post was rejected.', 'jtzl-bulletin' ) : $message,
				array( 'status' => 400 )
			);
		}

		list( $rest_code, $status ) = self::MAPPING[ $native_code ];

		if ( 'moderation_rejected' === $rest_code ) {
			$message = __( 'This post cannot be submitted at this time.', 'jtzl-bulletin' );
		}

		return new \WP_Error( $rest_code, $message, array( 'status' => $status ) );
	}
}
