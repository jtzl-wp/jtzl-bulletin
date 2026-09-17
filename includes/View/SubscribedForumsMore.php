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
 * Adds pagination to the profile's subscribed-forums loop.
 *
 * @since 0.3.0
 */
class SubscribedForumsMore {

	private const TARGET = 'bltn-subscribed-forums';

	private ContextInterface $wp;

	private LoadMore $more;

	public function __construct( ContextInterface $wp, LoadMore $more ) {
		$this->wp   = $wp;
		$this->more = $more;
	}

	/**
	 * Render the append target and the control, when there is a page to reach.
	 *
	 * The drained bbPress loop retains the page count enabled by
	 * Query\SubscribedForumQuery.
	 *
	 * @since 0.3.0
	 */
	public function render(): void {
		if ( ! $this->wp->is_subscriptions() || 1 >= $this->wp->get_max_forum_pages() ) {
			return;
		}

		printf( '<div id="%s"></div>', esc_attr( self::TARGET ) );

		// The current profile URL carries the subscription owner into the AJAX request.
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
