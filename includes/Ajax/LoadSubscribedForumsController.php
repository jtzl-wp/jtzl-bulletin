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
 * The fourth load-more endpoint, and the first on a reskin screen: it continues the
 * Subscribed Forums list on a member profile's Subscriptions tab (issue #50). Same
 * bbPress AJAX router as its siblings (`bbp_get_ajax_url()` dispatches to
 * `bbp_ajax_{action}`), same paging contract — page 1 ships with the document, so a
 * continuation starts at page 2.
 *
 * It differs from the other three in what identifies its subject. They name a topic
 * or a forum in the request body; this one names nothing, because the subject is a
 * *user* and the request already carries which one. `bbp_get_ajax_url()` is the
 * current page's own URL with `bbp-ajax=true` added, so a control rendered on
 * `/forums/users/mara/subscriptions/` posts back to that same route: WordPress parses
 * it, bbPress resolves the displayed user from it during `parse_query`, and
 * `bbp_do_ajax()` only runs afterwards, on `bbp_template_redirect`. So by the time
 * this handler is reached the profile is established exactly as it was for the page
 * render — which is also what keeps the appended rows identical to the ones above
 * them, since `loop-single-forum.php` prints its unsubscribe toggle only when
 * `bbp_is_user_home() && bbp_is_subscriptions()`.
 *
 * Reading the user from the URL rather than the body does not soften the check on it.
 * The URL is the client's to choose too, so authorisation is re-derived here from
 * scratch: bbPress's own template shows this section only to the profile's owner or
 * to someone who may edit that user, and the continuation refuses anyone else. A
 * reader's subscription list is not public — it is the one load-more list on this
 * plugin's screens that is not, and the reason this endpoint has an authorisation
 * guard where its siblings have visibility guards.
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

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface     $wp     WordPress/bbPress seam.
	 * @param SubscribedForumQuery $query  Shared subscribed-forum query builder.
	 * @param SubscribedForumList  $forums Subscribed-forum list renderer.
	 * @param RequestedPage        $paging Shared reader for `paged`.
	 */
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
	 * The profile whose subscriptions may be served to this reader, or no return at
	 * all — send_json_error() ends the request, so a refusal has nothing to return to.
	 *
	 * The pair at the end is bbPress's own policy, lifted from `user-subscriptions.php`:
	 * either the profile is the reader's own, or the reader may edit that user (a
	 * moderator or administrator). Nobody else is shown this list on the screen, so
	 * nobody else is served a page of it here.
	 *
	 * The first half is asked as a comparison of IDs rather than through
	 * `bbp_is_user_home()`, which the template uses. They agree on a real request, but
	 * the conditional is a property of the *parsed query* — bbPress decides it while
	 * routing, from the user the request arrived as — and it is filterable besides. An
	 * authorisation gate should be a question about the reader in front of it at the
	 * moment it decides, not a value another decision left behind.
	 *
	 * @since 0.3.0
	 *
	 * @return int Displayed user ID.
	 */
	private function authorized_user(): int {
		// Subscriptions can be switched off site-wide, and bbPress then renders no
		// section at all. Refuse rather than serve a list the screen would not show.
		if ( ! $this->wp->is_subscriptions_active() ) {
			$this->wp->send_json_error( array( 'message' => 'inactive' ), 403 );
		}

		// The subject rides on the route, so a request made against anything but a
		// Subscriptions tab has not named one — and its rows would be assembled
		// outside the context that gives them their subscription toggle.
		if ( ! $this->wp->is_subscriptions() ) {
			$this->wp->send_json_error( array( 'message' => 'no_subscriptions' ), 400 );
		}

		// bbPress resolves the tab's user before it decides the tab is a
		// subscriptions one, so this holds in practice; it is checked because a
		// query with no user would otherwise ask for the subscriptions of nobody,
		// and `bbp_is_subscriptions` is a filtered conditional another plugin can
		// answer for.
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
	 * The bound is derived rather than declared: the relationship itself says how many
	 * forums the user subscribes to, so the last page that could hold a row is
	 * `ceil( subscriptions / page size )`. Reading the IDs is a single engagement
	 * lookup, where serving the page is a sorted, offset query over the posts table —
	 * so a request for page 20,000 costs the cheap answer, not the expensive one
	 * (CWE-770; issue #51, item 2).
	 *
	 * The three public endpoints reach the same hardening by a different route. An
	 * earlier note here proposed giving them this exact shape, with a forum's topic
	 * count and a topic's reply count as the bound; that was wrong, because those
	 * counts live in postmeta maintained by bbPress's own write paths and go stale
	 * under an import — a bound derived from a stale count refuses pages a reader can
	 * legitimately reach. They take a declared ceiling instead, and Ajax\RequestedPage
	 * writes out the reasoning. This endpoint keeps the exact bound, which is tighter
	 * than that ceiling and subsumes it, so it reuses the shared reader and not the
	 * shared refusal.
	 *
	 * The count is an upper bound, not the page count: forum visibility can still
	 * remove rows the reader may not see. That is the right direction to err — a page
	 * the query answers with nothing is a well-formed empty answer, while refusing a
	 * page a reader can legitimately reach would truncate the list all over again.
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
