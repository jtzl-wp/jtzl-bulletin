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
 * @since 0.3.0
 */
class DocumentTitle {

	private ContextInterface $wp;

	private ScreenClassifier $screen;

	public function __construct( ContextInterface $wp, ScreenClassifier $screen ) {
		$this->wp     = $wp;
		$this->screen = $screen;
	}

	/**
	 * Fill in the title part when WordPress resolved none.
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
