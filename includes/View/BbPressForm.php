<?php
/**
 * Rendering one of bbPress's own forms on the takeover tier.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

/**
 * Owns safe rendering of bbPress forms in Bulletin templates.
 *
 * Form titles must retain bbPress markup, while user-controlled values remain
 * escaped. Allowed-tag guidance uses bbPress's generated HTML.
 */
class BbPressForm {

	private const NOWHERE = 'jtzl-bulletin-suppressed.php';

	/**
	 * Render `form-{$name}.php` with both corrections in place.
	 *
	 * @since 0.5.0
	 *
	 * @param string $name Template part name — 'reply' or 'topic'.
	 */
	public function render( string $name ): void {
		$text_only = static fn( $title ) => is_string( $title ) ? wp_strip_all_tags( $title ) : $title;

		$no_allowed_tags = static fn( $templates, $slug, $part ) =>
			( 'form' === $slug && 'allowed-tags' === $part )
				? array( self::NOWHERE )
				: $templates;

		add_filter( 'bbp_get_topic_title', $text_only );
		add_filter( 'bbp_get_forum_title', $text_only );
		add_filter( 'bbp_get_template_part', $no_allowed_tags, 10, 3 );

		try {
			bbp_get_template_part( 'form', $name );
		} finally {
			remove_filter( 'bbp_get_topic_title', $text_only );
			remove_filter( 'bbp_get_forum_title', $text_only );
			remove_filter( 'bbp_get_template_part', $no_allowed_tags, 10 );
		}
	}
}
