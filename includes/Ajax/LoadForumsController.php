<?php
/**
 * Load-more forums endpoint.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Ajax;

use JTZL\Bulletin\Query\ForumQuery;
use JTZL\Bulletin\View\ForumList;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Continues a forum list through bbPress's front-end AJAX router.
 *
 * Page 1 ships with the document. Parent 0 denotes the public root list; named
 * parents require forum visibility and password checks.
 *
 * @since 0.3.0
 */
class LoadForumsController {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Shared forum-query builder.
	 *
	 * @var ForumQuery
	 */
	private ForumQuery $query;

	/**
	 * Forum list renderer.
	 *
	 * @var ForumList
	 */
	private ForumList $forums;

	/**
	 * Shared reader and bound for the requested page.
	 *
	 * @var RequestedPage
	 */
	private RequestedPage $paging;

	public function __construct( ContextInterface $wp, ForumQuery $query, ForumList $forums, RequestedPage $paging ) {
		$this->wp     = $wp;
		$this->query  = $query;
		$this->forums = $forums;
		$this->paging = $paging;
	}

	/**
	 * Return a rendered page of a forum list as JSON.
	 *
	 * @since 0.3.0
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public read-only endpoint; see Asset\AssetManager for why there is no nonce.
		$parent_id = isset( $_POST['forum'] ) ? (int) $_POST['forum'] : 0;
		$page      = $this->paging->requested();

		if ( 0 !== $parent_id ) {
			$this->guard_parent( $parent_id );
		}

		// Check access before paging so an inaccessible parent does not disclose range.
		$this->paging->guard( $page );

		$html = $this->forums->capture( $this->query->args( $parent_id, $page ) );
		$max  = $this->wp->get_max_forum_pages();

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
	 * Refuse a continuation the reader could not have been served on the page.
	 *
	 * The root index is public and ForumQuery applies bbPress visibility. Named
	 * parents need both capability and password checks, including public forums under
	 * restricted ancestors.
	 *
	 * @since 0.3.0
	 *
	 * @param int $parent_id Parent forum the request names.
	 */
	private function guard_parent( int $parent_id ): void {
		// Match bbPress by making missing and inaccessible forums indistinguishable.
		if ( ! $this->forum_is_readable( $parent_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'bad_forum' ), 400 );
		}

		if ( $this->wp->is_password_required( $parent_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'protected' ), 403 );
		}
	}

	/**
	 * Whether the request names a real forum the caller may view.
	 *
	 * Deliberately answers a nonexistent ID and an existing-but-inaccessible one
	 * the same way, so the caller learns nothing about which it was.
	 *
	 * @since 0.3.0
	 *
	 * @param int $parent_id Parent forum the request names.
	 * @return bool
	 */
	private function forum_is_readable( int $parent_id ): bool {
		if ( $this->wp->get_forum_post_type() !== $this->wp->get_post_type( $parent_id ) ) {
			return false;
		}
		return $this->wp->user_can_view_forum( $parent_id );
	}
}
