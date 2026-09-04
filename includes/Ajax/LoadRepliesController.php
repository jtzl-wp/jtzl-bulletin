<?php
/**
 * Load-more replies endpoint.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Ajax;

use JTZL\Bulletin\Query\ReplyQuery;
use JTZL\Bulletin\View\ReplyView;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Continues a topic's replies through bbPress's front-end AJAX router.
 * Page 1 ships with the document; later pages are replies-only (see ReplyQuery).
 *
 * @since 0.1.0
 */
class LoadRepliesController {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Shared reply-query builder.
	 *
	 * @var ReplyQuery
	 */
	private ReplyQuery $query;

	/**
	 * Reply renderer.
	 *
	 * @var ReplyView
	 */
	private ReplyView $reply_view;

	/**
	 * Shared reader and bound for the requested page.
	 *
	 * @var RequestedPage
	 */
	private RequestedPage $paging;

	public function __construct( ContextInterface $wp, ReplyQuery $query, ReplyView $reply_view, RequestedPage $paging ) {
		$this->wp         = $wp;
		$this->query      = $query;
		$this->reply_view = $reply_view;
		$this->paging     = $paging;
	}

	/**
	 * Return a rendered page of replies for a topic as JSON.
	 *
	 * @since 0.1.0
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public read-only endpoint; see Asset\AssetManager for why there is no nonce.
		$topic_id = isset( $_POST['topic'] ) ? (int) $_POST['topic'] : 0;
		$page     = $this->paging->requested();

		$this->guard_access( $topic_id );

		// Check access before paging so an inaccessible topic does not disclose range.
		$this->paging->guard( $page );

		ob_start();
		if ( $this->wp->has_replies( $this->query->args( $topic_id, $page ) ) ) {
			while ( $this->wp->the_replies_loop() ) {
				$this->wp->the_reply();
				$this->reply_view->render( $topic_id );
			}
		}
		$html = (string) ob_get_clean();

		// Query\ReplyQuery's count, not bbPress's — see its max_pages() docblock.
		$max = $this->query->max_pages( $topic_id );

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
	 * Refuse the request unless this topic's replies may be served.
	 *
	 * These checks apply on bbPress singular views but not on this AJAX route.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 */
	private function guard_access( int $topic_id ): void {
		// Match bbPress by making missing and inaccessible topics indistinguishable.
		// Use capability checks so authorized private-forum readers retain access.
		if ( ! $this->request_may_read_topic( $topic_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'bad_topic' ), 400 );
		}

		// Do not expose replies hidden by the singular view's password form.
		if ( $this->wp->is_password_required( $topic_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'protected' ), 403 );
		}
	}

	/**
	 * Whether the request names a real, readable topic in a forum the caller may
	 * view.
	 *
	 * Missing, non-readable, and inaccessible topics deliberately share one result.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return bool
	 */
	private function request_may_read_topic( int $topic_id ): bool {
		if ( ! $this->topic_is_readable( $topic_id ) ) {
			return false;
		}
		return $this->wp->user_can_view_forum( $this->wp->get_topic_forum_id( $topic_id ) );
	}

	/**
	 * Whether a topic exists, is a topic, and is itself publicly readable.
	 *
	 * Check the topic's own status, not only its forum. Public and closed topics are
	 * readable; private, pending, spam, and trashed topics are not.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return bool
	 */
	private function topic_is_readable( int $topic_id ): bool {
		if ( $topic_id <= 0 ) {
			return false;
		}
		if ( $this->wp->get_topic_post_type() !== $this->wp->get_post_type( $topic_id ) ) {
			return false;
		}
		$viewable = array( $this->wp->get_public_status_id(), $this->wp->get_closed_status_id() );
		return in_array( $this->wp->get_post_status( $topic_id ), $viewable, true );
	}
}
