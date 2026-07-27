<?php
/**
 * The continuation control under a profile's Subscribed Forums list.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Hangs a "Load more forums" control off the end of bbPress's own forums loop on a
 * member profile's Subscriptions tab, which is the reskin screen where that loop
 * truncates at `_bbp_forums_per_page` with nothing to page it (issue #50, and
 * Query\SubscribedForumQuery for the query half).
 *
 * Two things scope it, and the first is the load-bearing one:
 *
 *  - `bbp_template_after_forums_loop`, the hook bbPress fires at the very end of
 *    `loop-forums.php`. That puts the control immediately below the list it grows and
 *    above the "Subscribed Topics" heading that follows — where
 *    `bbp_template_after_user_subscriptions` would have put it below both lists,
 *    pointing at the wrong one.
 *  - `bbp_is_subscriptions()`, because that hook fires for every forums loop bbPress
 *    renders, including the `[bbp-forum-index]` shortcode on an ordinary page, where
 *    the list is not truncated and not ours to change. On the two takeover forum
 *    screens the question never arises: they render View\ForumList and their own
 *    control, so bbPress's loop-forums is never reached there at all.
 *
 * bbPress's own template has already gated this section on
 * `bbp_is_user_home() || current_user_can( 'edit_user', … )`, so a control can only be
 * rendered to a reader the screen is already showing the list to. That is placement,
 * not access control: what the control asks for is authorised again, from scratch, in
 * Ajax\LoadSubscribedForumsController.
 *
 * @since 0.3.0
 */
class SubscribedForumsMore {

	/**
	 * The element appended rows land in.
	 *
	 * @var string
	 */
	private const TARGET = 'bltn-subscribed-forums';

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * The inline load-more control.
	 *
	 * @var LoadMore
	 */
	private LoadMore $more;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp   WordPress/bbPress seam.
	 * @param LoadMore         $more The inline load-more control.
	 */
	public function __construct( ContextInterface $wp, LoadMore $more ) {
		$this->wp   = $wp;
		$this->more = $more;
	}

	/**
	 * Render the append target and the control, when there is a page to reach.
	 *
	 * The page count is bbPress's own, and comes from a global rather than an
	 * argument: `bbpress()->forum_query` holds whichever forums query ran last, which
	 * on this hook is the subscribed-forums query the loop above just drained. It is
	 * answerable at all only because Query\SubscribedForumQuery switched
	 * `no_found_rows` back off. A list that fits on one page renders nothing, so the
	 * screen is unchanged for every reader whose subscriptions were never truncated.
	 *
	 * @since 0.3.0
	 */
	public function render(): void {
		if ( ! $this->wp->is_subscriptions() || 1 >= $this->wp->get_max_forum_pages() ) {
			return;
		}

		printf( '<div id="%s"></div>', esc_attr( self::TARGET ) );

		// No subject: which user's subscriptions these are rides on the URL the
		// control posts back to, the same URL that established it for the page.
		$this->more->render(
			array(
				'action' => 'bulletin_load_subscribed_forums',
				'target' => self::TARGET,
				'next'   => 2,
				'label'  => __( 'Load more forums', 'jtzl-bulletin' ),
			)
		);
	}
}
