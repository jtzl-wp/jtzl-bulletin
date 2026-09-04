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
 * Continues a forum's threads through bbPress's front-end AJAX router.
 * Page 1 ships with the document; later pages use the same ThreadList rows.
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

		// Match bbPress by making missing and inaccessible forums indistinguishable.
		// Capability checks retain access for authorized private-forum readers.
		if ( ! $this->forum_is_readable( $forum_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'bad_forum' ), 400 );
		}

		// Do not expose threads hidden by the singular view's password form.
		if ( $this->wp->is_password_required( $forum_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'protected' ), 403 );
		}

		// Check access before paging so an inaccessible forum does not disclose range.
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
	 * the same way, so the caller learns nothing about which it was.
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
