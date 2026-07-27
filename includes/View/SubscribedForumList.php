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
 * That is what the reskin tier is. bbPress renders its own `loop-single-forum.php`
 * inside Bulletin's chrome and our stylesheet restyles the result, so a row appended
 * to that list has to *be* that template rather than a copy of it — otherwise page 2
 * loses the unsubscribe toggle, the sub-forum list, and the counts that page 1 shows,
 * and any theme override of the row stops applying halfway down the list.
 *
 * The rows arrive nested as bbPress nests them: `ul.bbp-forums > li.bbp-body > row`.
 * Both bbPress's stylesheet and ours select on that chain, so a page appended outside
 * it would be styled as something else. Each page therefore arrives as its own
 * complete block, appended into the empty target View\SubscribedForumsMore renders —
 * rather than into bbPress's own `li.bbp-body`, which carries no ID to append to and
 * whose hidden header and footer rows would then bracket the first page alone.
 *
 * @since 0.3.0
 */
class SubscribedForumList {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
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
