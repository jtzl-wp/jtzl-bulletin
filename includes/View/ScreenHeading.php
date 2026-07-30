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
 * Gives a reskin screen the `h1` neither bbPress nor Bulletin was emitting.
 *
 * The reskin template used to say that bbPress's own content carries the screen's
 * heading, and left the app bar's title a `<span>` so as not to compete with it.
 * That premise was wrong. Measured across eight reskin routes on the fixture, **not
 * one of them emits an `h1` at all**:
 *
 * ```
 * /topics/                            no headings of any level
 * /forums/topic-tag/{slug}/           no headings of any level
 * /forums/view/{slug}/                no headings of any level
 * /forums/users/{slug}/subscriptions/ no headings of any level
 * /forums/users/{slug}/               starts at h2 (`@nicename`), no h1
 * /forums/users/{slug}/topics/        starts at h2
 * /forums/users/{slug}/replies/       starts at h2
 * /forums/users/{slug}/engagements/   starts at h2
 * ```
 *
 * So half this tier had no document outline, and the profile suite had one rooted
 * at h2 — WCAG 1.3.1 and 2.4.6, and the half of the review's title finding that
 * Chrome\DocumentTitle did not answer. Because no route emits an `h1`, this one can
 * be unconditional: there is nothing for it to compete with or duplicate.
 *
 * ## Announced, not shown
 *
 * `.bltn-sr-only`, and that is the whole design decision. Every reskin screen
 * already names itself visibly — the tag, view and topic-archive routes carry
 * bbPress's breadcrumb, whose last item is the current screen, and the profile
 * routes lead with the member's name and handle (View\ProfileIdentity). A second
 * visible name would be chrome added to say what the screen already says, on a
 * product whose rule is that when a decision is close you take the thing away.
 * What was missing was the *structure*, so structure is what this adds.
 *
 * ## Named by bbPress
 *
 * The text is `bbp_title()`, the same seam getter Chrome\DocumentTitle uses — so the
 * heading and the tab agree wherever core had no name of its own, which is most of
 * this tier. They are allowed to differ: DocumentTitle fills a *blank* title only,
 * and on a topic-tag route WordPress already supplies the term name, so the tab
 * reads "maintenance" where this heading reads "Topic Tag: maintenance". Both name
 * the screen and the more explicit one is the heading, which is the right way round.
 *
 * Not re-derived into something tidier, either way: bbPress names these screens
 * across about six branches, and a second implementation would be a second thing to
 * keep in parity with it for no gain a reader can see.
 *
 * @since 0.3.0
 */
class ScreenHeading {

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
