<?php
/**
 * Names the screens WordPress cannot name for itself.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Gives every Bulletin screen a document title.
 *
 * The gap is upstream and precise: bbPress 2.6.14 filters only the **legacy**
 * `wp_title` (`core/filters.php:43`), which `wp_get_document_title()` does not call —
 * the modern path runs `document_title_parts` and `document_title`. bbPress predates
 * `title-tag` support and never caught up, so on any document that titles itself the
 * modern way, bbPress's screens arrive with nothing to be called.
 *
 * Bulletin renders its own document and titles it through `wp_get_document_title()`
 * (see templates/partials/head.php), so the symptom was four screens sharing one
 * name. Measured on the fixture: `/forums/users/mara/`, `/forums/view/no-replies/` and
 * `/forums/search/{terms}/` all produced `<title>bulletin</title>` — indistinguishable
 * in a tab strip, in a bookmark, in a browser's history, and to a screen reader
 * announcing the page it just landed on (WCAG 2.4.2).
 *
 * The name is taken from **bbPress's own** `bbp_title()` — reached through the context
 * seam as `get_bbpress_screen_title()`, like every other bbPress call in this codebase —
 * not invented here. That function already knows how to name every bbPress screen, and
 * it is what a bbPress site would show if WordPress still called `wp_title`. So this
 * restores bbPress's answer on the modern hook rather than authoring a second set of
 * titles that could drift from it.
 *
 * Going through the seam also removed a `function_exists( 'bbp_title' )` guard that was
 * unreachable: getting past the tier check above already means bbPress is loaded, since
 * the classifier resolves the tier by asking bbPress's own conditionals.
 *
 * @since 0.3.0
 */
class DocumentTitle {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Screen-tier classifier.
	 *
	 * @var ScreenClassifier
	 */
	private ScreenClassifier $screen;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp     WordPress/bbPress seam.
	 * @param ScreenClassifier $screen Screen-tier classifier.
	 */
	public function __construct( ContextInterface $wp, ScreenClassifier $screen ) {
		$this->wp     = $wp;
		$this->screen = $screen;
	}

	/**
	 * Fill in the title part when WordPress resolved none.
	 *
	 * Only fills a **blank**. Where WordPress already names the screen — a single
	 * topic and a single forum both have a queried object, so those were never
	 * affected — its answer stands, and bbPress's is not consulted. That keeps this to
	 * the routes with the defect and leaves every working title untouched.
	 *
	 * `bbp_title()` is called with empty separators so it returns the name alone:
	 * `wp_get_document_title()` adds the site name and the separator itself, and a
	 * second copy of either would read as "bulletin – bulletin".
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $parts Document title parts, per WordPress's `document_title_parts`.
	 * @return mixed
	 */
	public function filter_document_title_parts( $parts ) {
		if ( ! is_array( $parts ) || ScreenTier::None === $this->screen->tier() ) {
			return $parts;
		}

		$existing = isset( $parts['title'] ) && is_string( $parts['title'] ) ? trim( $parts['title'] ) : '';
		if ( '' !== $existing ) {
			return $parts;
		}

		$title = $this->wp->get_bbpress_screen_title();
		if ( '' === $title ) {
			return $parts;
		}

		$parts['title'] = $title;

		return $parts;
	}
}
