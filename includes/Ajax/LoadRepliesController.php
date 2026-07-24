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
 * Extends bbPress's own front-end AJAX router (bbp_get_ajax_url() dispatches to
 * `bbp_ajax_{action}`) rather than a standalone wp_ajax_ handler, so we stay
 * inside bbPress's request lifecycle. Paging matches the reading view exactly:
 * page 1 ships with the document, so load-more starts at page 2, and every page
 * is replies-only (see Query\ReplyQuery).
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
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp         WordPress/bbPress seam.
	 * @param ReplyQuery       $query      Shared reply-query builder.
	 * @param ReplyView        $reply_view Reply renderer.
	 */
	public function __construct( ContextInterface $wp, ReplyQuery $query, ReplyView $reply_view ) {
		$this->wp         = $wp;
		$this->query      = $query;
		$this->reply_view = $reply_view;
	}

	/**
	 * Return a rendered page of replies for a topic as JSON.
	 *
	 * @since 0.1.0
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public read-only endpoint; see Asset\AssetManager for why there is no nonce.
		$topic_id = isset( $_POST['topic'] ) ? (int) $_POST['topic'] : 0;
		$page     = isset( $_POST['paged'] ) ? (int) $_POST['paged'] : 2;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Clamp: page 1 ships with the document, so load-more starts at 2.
		if ( $page < 2 ) {
			$page = 2;
		}

		// Refuse the request unless the topic may be read; a passing check falls
		// through to serving. send_json_error() ends the request from inside.
		$this->guard_access( $topic_id );

		ob_start();
		if ( $this->wp->has_replies( $this->query->args( $topic_id, $page ) ) ) {
			while ( $this->wp->the_replies_loop() ) {
				$this->wp->the_reply();
				$this->reply_view->render();
			}
		}
		$html = (string) ob_get_clean();

		$max = $this->wp->get_max_reply_pages();

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
	 * Each gate ends the request through send_json_error() (which never returns),
	 * so returning normally is the single "allowed" outcome: the topic is a
	 * readable topic, its forum is one the reader may view, and no unmet password
	 * stands in the way. bbPress applies these on its own singular views but not
	 * on this AJAX route, so the continuation must apply them itself.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 */
	private function guard_access( int $topic_id ): void {
		// The topic itself: a real topic whose own status is public or closed, so a
		// private/trashed topic in a public forum cannot leak its replies.
		if ( ! $this->topic_is_readable( $topic_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'bad_topic' ), 400 );
		}

		// Gate on what the *user* may view, not on the forum's status label: a
		// keymaster, moderator, or member of a private forum must still get their
		// replies, while an unauthorised visitor is refused — including for a public
		// forum nested under a restricted ancestor.
		$forum_id = $this->wp->get_topic_forum_id( $topic_id );
		if ( ! $this->wp->user_can_view_forum( $forum_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		// A password-protected topic masks its content behind the password form on
		// bbPress's own view, so the reading view declines takeover there (see
		// ReadingScreen). This continuation refuses the same way rather than stream
		// the replies bbPress withholds until the password is supplied.
		if ( $this->wp->is_password_required( $topic_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'protected' ), 403 );
		}
	}

	/**
	 * Whether a topic exists, is a topic, and is itself publicly readable.
	 *
	 * A topic that is private/pending/spam/trashed inside a public forum must not
	 * leak its replies, so its own status is checked — not just the forum's.
	 * "Readable" is public OR closed: closed topics are read-only but still
	 * publicly viewable, matching the thread-navigation query.
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
