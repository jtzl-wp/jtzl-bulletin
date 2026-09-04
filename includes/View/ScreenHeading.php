<?php
/**
 * The reskin document's own heading.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Owns the accessible h1 for reskinned bbPress screens.
 *
 * The heading remains visually hidden because each screen already has a visible
 * name. `bbp_title()` keeps its accessible name aligned with bbPress routing.
 */
class ScreenHeading {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Echo the screen's heading.
	 *
	 * `data-bltn-heading` and `tabindex="-1"` match what the four takeover templates
	 * put on theirs, so "the heading of the screen" is one selector across both tiers
	 * rather than one per tier with an exception.
	 *
	 * @since 0.3.0
	 */
	public function render(): void {
		$title = $this->wp->get_bbpress_screen_title();

		// A screen bbPress declines to name falls back to the site's. An unnamed
		// document is what this class exists to prevent, so it does not return empty
		// handed — and the site name is what the app bar shows on the same screen.
		if ( '' === $title ) {
			$title = $this->wp->get_bloginfo( 'name' );
		}

		if ( '' === $title ) {
			return;
		}

		printf(
			'<h1 class="bltn-sr-only" data-bltn-heading tabindex="-1">%s</h1>',
			esc_html( $title )
		);
	}
}
