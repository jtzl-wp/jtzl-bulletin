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
	 * Shared reader and bound for the requested page.
	 *
	 * @var RequestedPage
	 */
	private RequestedPage $paging;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp      WordPress/bbPress seam.
	 * @param TopicQuery       $query   Shared topic-query builder.
	 * @param ThreadList       $threads Thread list renderer.
	 * @param RequestedPage    $paging  Shared reader and bound for `paged`.
	 */
	public function __construct( ContextInterface $wp, TopicQuery $query, ThreadList $threads, RequestedPage $paging ) {
		$this->wp      = $wp;
		$this->query   = $query;
		$this->threads = $threads;
		$this->paging  = $paging;
	}

	/**
	 * Return a rendered page of a forum's threads as JSON.
	 *
	 * @since 0.1.0
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public read-only endpoint; see Asset\AssetManager for why there is no nonce.
		$forum_id = isset( $_POST['forum'] ) ? (int) $_POST['forum'] : 0;
		$page     = $this->paging->requested();

		// send_json_error() ends the request, so there is nothing to return to. A
		// forum the caller may not view answers exactly like one that doesn't exist
		// — bbPress's own singular views present inaccessible private/hidden
		// resources as not found, and this route must not let an anonymous caller
		// distinguish "no such forum" from "a forum you can't see" (issue #78).
		// Gating on capability rather than the forum's status label also means a
		// keymaster, moderator, or member of a private forum still gets their
		// threads, while an unauthorised visitor is refused — including for a
		// public forum nested under a restricted ancestor.
		if ( ! $this->forum_is_readable( $forum_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'bad_forum' ), 400 );
		}

		// A password-protected forum masks its listing behind the password form on
		// bbPress's own view, so the forum screen declines takeover there (see
		// ReadingScreen). This continuation must refuse the same way rather than
		// stream the threads bbPress withholds until the password is supplied.
		if ( $this->wp->is_password_required( $forum_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'protected' ), 403 );
		}

		// Then the page, after access and never before it: a reader who may not see
		// this forum is told that, whatever page they asked for.
		$this->paging->guard( $page );

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

	/**
	 * Whether the request names a real forum the caller may view.
	 *
	 * Deliberately answers a nonexistent ID and an existing-but-inaccessible one
	 * the same way, so the caller learns nothing about which it was (issue #78).
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	private function forum_is_readable( int $forum_id ): bool {
		if ( $forum_id <= 0 || $this->wp->get_forum_post_type() !== $this->wp->get_post_type( $forum_id ) ) {
			return false;
		}
		return $this->wp->user_can_view_forum( $forum_id );
	}
}
