<?php
/**
 * A run of subscribed-forum rows, in bbPress's own markup.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders a page of the Subscribed Forums list for the continuation endpoint —
 * View\ForumList's counterpart for the reskin tier, and different from it in the one
 * way that matters: the rows are bbPress's, not ours.
 *
 * @since 0.3.0
 */
class SubscribedForumList {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Render one page of the list and return it as markup, leaving the global post as
	 * it was found (the reset is owned here for the reason View\ForumList owns it:
	 * this is the only thing that knows whether the loop ran).
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query args (see Query\SubscribedForumQuery).
	 * @return string Markup, or '' when the page matched nothing.
	 */
	public function capture( array $args ): string {
		ob_start();

		if ( $this->wp->has_forum_subscriptions( $args ) ) {
			echo '<ul class="bbp-forums"><li class="bbp-body">';
			while ( $this->wp->the_forums_loop() ) {
				$this->wp->the_forum();
				$this->wp->render_forum_row();
			}
			echo '</li></ul>';
			$this->wp->reset_postdata();
		}

		return (string) ob_get_clean();
	}
}
