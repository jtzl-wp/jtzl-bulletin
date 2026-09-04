<?php
/**
 * Load-more endpoint for a profile's Subscribed Forums list.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Ajax;

use JTZL\Bulletin\Query\SubscribedForumQuery;
use JTZL\Bulletin\View\SubscribedForumList;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Continues the Subscribed Forums list on a profile's Subscriptions tab.
 *
 * The AJAX URL preserves the profile route, so bbPress resolves the displayed user
 * before dispatch. This also preserves the context used to render unsubscribe links.
 *
 * Because the client controls that URL, authorization is recalculated. Only the
 * profile owner or a user who can edit that profile may read this private list.
 *
 * @since 0.3.0
 */
class LoadSubscribedForumsController {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Shared subscribed-forum query builder.
	 *
	 * @var SubscribedForumQuery
	 */
	private SubscribedForumQuery $query;

	/**
	 * Subscribed-forum list renderer.
	 *
	 * @var SubscribedForumList
	 */
	private SubscribedForumList $forums;

	/**
	 * Shared reader for the requested page.
	 *
	 * @var RequestedPage
	 */
	private RequestedPage $paging;

	public function __construct( ContextInterface $wp, SubscribedForumQuery $query, SubscribedForumList $forums, RequestedPage $paging ) {
		$this->wp     = $wp;
		$this->query  = $query;
		$this->forums = $forums;
		$this->paging = $paging;
	}

	/**
	 * Return a rendered page of the subscribed-forums list as JSON.
	 *
	 * @since 0.3.0
	 */
	public function handle(): void {
		$page    = $this->paging->requested();
		$user_id = $this->authorized_user();

		$this->guard_page( $page, $user_id );

		$html = $this->forums->capture( $this->query->args( $page ) );
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
	 * The profile whose subscriptions may be served to this reader.
	 *
	 * Match user-subscriptions.php: allow the profile owner or someone who can edit
	 * that user. Compare IDs directly instead of trusting the filterable parsed-query
	 * conditional `bbp_is_user_home()`.
	 *
	 * @since 0.3.0
	 *
	 * @return int Displayed user ID.
	 */
	private function authorized_user(): int {
		// Do not serve a list when bbPress hides the section site-wide.
		if ( ! $this->wp->is_subscriptions_active() ) {
			$this->wp->send_json_error( array( 'message' => 'inactive' ), 403 );
		}

		// The route supplies both the subject and the row-rendering context.
		if ( ! $this->wp->is_subscriptions() ) {
			$this->wp->send_json_error( array( 'message' => 'no_subscriptions' ), 400 );
		}

		// Recheck the user because another plugin can filter `bbp_is_subscriptions`.
		$user_id = $this->wp->get_displayed_user_id();
		if ( $user_id <= 0 ) {
			$this->wp->send_json_error( array( 'message' => 'no_user' ), 400 );
		}

		if ( $user_id !== $this->wp->get_current_user_id() && ! $this->wp->current_user_can_edit_user( $user_id ) ) {
			$this->wp->send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		return $user_id;
	}

	/**
	 * Refuse a page the list cannot have, before issuing an offset query for it.
	 *
	 * Derive the bound from the cheap subscription relationship before issuing the
	 * sorted offset query (CWE-770).
	 *
	 * This exact relationship count is more reliable than bbPress's import-sensitive
	 * postmeta counts. Visibility may remove rows, so the result remains an upper bound.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page    Requested page.
	 * @param int $user_id Displayed user ID.
	 */
	private function guard_page( int $page, int $user_id ): void {
		$per_page = max( 1, $this->wp->get_forums_per_page() );
		$pages    = (int) ceil( count( $this->wp->get_subscribed_forum_ids( $user_id ) ) / $per_page );

		if ( $page > $pages ) {
			$this->wp->send_json_error( array( 'message' => 'out_of_range' ), 400 );
		}
	}
}
