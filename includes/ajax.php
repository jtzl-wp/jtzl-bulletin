<?php
/**
 * Load-more replies endpoint.
 *
 * Extends bbPress's own front-end AJAX router (bbp_get_ajax_url() dispatches to
 * `bbp_ajax_{action}`) rather than adding a standalone wp_ajax_ handler — this
 * keeps us inside bbPress's request lifecycle, where its query vars and helpers
 * are already set up.
 *
 * We force lead-topic separation here too, so paging matches the reading view:
 * page 1 (rendered with the topic) is replies-only, and every page after it is
 * replies-only as well.
 *
 * @package Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'bbp_ajax_bulletin_load_replies', 'bltn_ajax_load_replies' );

/**
 * Return a rendered page of replies for a topic as JSON.
 */
function bltn_ajax_load_replies() {
	$topic_id = isset( $_POST['topic'] ) ? (int) $_POST['topic'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public read-only endpoint; see includes/assets.php.
	$page     = isset( $_POST['paged'] ) ? (int) $_POST['paged'] : 2;  // phpcs:ignore WordPress.Security.NonceVerification.Missing

	// Clamp: page 1 ships with the document, so load-more starts at 2.
	if ( $page < 2 ) {
		$page = 2;
	}

	// Validate the topic.
	if ( ! $topic_id || bbp_get_topic_post_type() !== get_post_type( $topic_id ) ) {
		wp_send_json_error( array( 'message' => 'bad_topic' ), 400 );
	}

	// Only public forums are served in v1 (the live forum is public-read). This
	// stops the endpoint leaking replies from a private/hidden forum. Ancestor
	// visibility is not walked — acceptable while everything is public.
	$forum_id = bbp_get_topic_forum_id( $topic_id );
	if ( get_post_status( $forum_id ) !== bbp_get_public_status_id() ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	ob_start();
	if ( bbp_has_replies( bltn_reply_query_args( $topic_id, $page ) ) ) {
		while ( bbp_replies() ) {
			bbp_the_reply();
			bltn_render_reply();
		}
	}
	$html = ob_get_clean();

	$max = (int) bbpress()->reply_query->max_num_pages;

	wp_send_json_success(
		array(
			'html'     => $html,
			'page'     => $page,
			'nextPage' => $page + 1,
			'hasMore'  => $page < $max,
		)
	);
}
