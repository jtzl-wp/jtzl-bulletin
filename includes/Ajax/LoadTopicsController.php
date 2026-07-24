<?php
/**
 * Load-more threads endpoint.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Ajax;

use JTZL\Bulletin\Query\TopicQuery;
use JTZL\Bulletin\View\ThreadList;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * The forum screen's half of load-more, sibling to LoadRepliesController: same
 * bbPress AJAX router (bbp_get_ajax_url() dispatches to `bbp_ajax_{action}`),
 * same paging contract — page 1 ships with the document, so load-more starts at
 * page 2 — and the same rows, rendered through View\ThreadList.
 *
 * @since 0.1.0
 */
class LoadTopicsController {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Shared topic-query builder.
	 *
	 * @var TopicQuery
	 */
	private TopicQuery $query;

	/**
	 * Thread list renderer.
	 *
	 * @var ThreadList
	 */
	private ThreadList $threads;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp      WordPress/bbPress seam.
	 * @param TopicQuery       $query   Shared topic-query builder.
	 * @param ThreadList       $threads Thread list renderer.
	 */
	public function __construct( ContextInterface $wp, TopicQuery $query, ThreadList $threads ) {
		$this->wp      = $wp;
		$this->query   = $query;
		$this->threads = $threads;
	}

	/**
	 * Return a rendered page of a forum's threads as JSON.
	 *
	 * @since 0.1.0
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public read-only endpoint; see Asset\AssetManager for why there is no nonce.
		$forum_id = isset( $_POST['forum'] ) ? (int) $_POST['forum'] : 0;
		$page     = isset( $_POST['paged'] ) ? (int) $_POST['paged'] : 2;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Clamp: page 1 ships with the document, so load-more starts at 2.
		if ( $page < 2 ) {
			$page = 2;
		}

		// send_json_error() ends the request, so there is nothing to return to.
		if ( $forum_id <= 0 || $this->wp->get_forum_post_type() !== $this->wp->get_post_type( $forum_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'bad_forum' ), 400 );
		}

		// Gate on what the *user* may view rather than on the forum's status
		// label: a keymaster, moderator, or member of a private forum still gets
		// their threads, while an unauthorised visitor is refused — including for
		// a public forum nested under a restricted ancestor. bbPress applies this
		// check on its own singular views but not on this AJAX route.
		if ( ! $this->wp->user_can_view_forum( $forum_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		// A password-protected forum masks its listing behind the password form on
		// bbPress's own view, so the forum screen declines takeover there (see
		// ReadingScreen). This continuation must refuse the same way rather than
		// stream the threads bbPress withholds until the password is supplied.
		if ( $this->wp->is_password_required( $forum_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'protected' ), 403 );
		}

		$html = $this->threads->capture( $this->query->args( $forum_id, $page ) );
		$max  = $this->wp->get_max_topic_pages();

		$this->wp->send_json_success(
			array(
				'html'     => $html,
				'page'     => $page,
				'nextPage' => $page + 1,
				'hasMore'  => $page < $max,
			)
		);
	}
}
