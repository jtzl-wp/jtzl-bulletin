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
 * The third of the load-more endpoints, alongside LoadRepliesController and
 * LoadTopicsController: same bbPress AJAX router (bbp_get_ajax_url() dispatches to
 * `bbp_ajax_{action}`), same paging contract — page 1 ships with the document, so
 * load-more starts at page 2 — and the same rows, here rendered through
 * View\ForumList.
 *
 * The subject is a parent forum, and 0 is a legitimate value rather than a missing
 * one: it is the root list the forums index shows. That is the one difference from
 * the sibling endpoints, and it is why the guards below key off "is this zero" before
 * "is this a forum" — asking bbPress whether the reader may view forum 0 would be a
 * question about nothing.
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

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp     WordPress/bbPress seam.
	 * @param ForumQuery       $query  Shared forum-query builder.
	 * @param ForumList        $forums Forum list renderer.
	 * @param RequestedPage    $paging Shared reader and bound for `paged`.
	 */
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

		// Then the page, after access and never before it: a reader who may not see
		// the named parent is told that, whatever page they asked for. The root list
		// has no access check to come after, so this is its only refusal.
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
	 * The root list needs none of this — the forums index is public, and which forums
	 * appear in it is bbPress's own visibility pass either way (see Query\ForumQuery).
	 * A named parent does, and for the reasons LoadTopicsController spells out: the
	 * capability check covers a public forum nested under a restricted ancestor, and
	 * the password check refuses what the screen itself withholds behind WordPress's
	 * password form until the password is supplied (issue #18).
	 *
	 * @since 0.3.0
	 *
	 * @param int $parent_id Parent forum the request names.
	 */
	private function guard_parent( int $parent_id ): void {
		// send_json_error() ends the request, so there is nothing to return to. A
		// parent the caller may not view answers exactly like one that doesn't
		// exist — bbPress's own singular views present inaccessible private/hidden
		// resources as not found, and this route must not let an anonymous caller
		// distinguish "no such forum" from "a forum you can't see" (issue #78). A
		// negative id lands here too: it has no post type, so it fails the same way
		// rather than reaching the query.
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
	 * the same way, so the caller learns nothing about which it was (issue #78).
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
