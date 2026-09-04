<?php
/**
 * Accessible names for bbPress's glyph-only row actions.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Gives bbPress's `+` / `×` row toggles a name a screen reader can announce.
 *
 * @since 0.3.0
 */
class RowActionLabels {

	private ScreenClassifier $screen;

	public function __construct( ScreenClassifier $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Name the subscribe / unsubscribe toggle.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Incoming args, before bbPress merges its defaults.
	 * @return mixed
	 */
	public function filter_subscribe_args( $args ) {
		return $this->name_glyphs(
			$args,
			array(
				'subscribe'   => __( 'Subscribe', 'jtzl-bulletin' ),
				'unsubscribe' => __( 'Unsubscribe', 'jtzl-bulletin' ),
			)
		);
	}

	/**
	 * Name the favourite toggle.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Incoming args, before bbPress merges its defaults.
	 * @return mixed
	 */
	public function filter_favorite_args( $args ) {
		return $this->name_glyphs(
			$args,
			array(
				'favorite'  => __( 'Add to favourites', 'jtzl-bulletin' ),
				'favorited' => __( 'Remove from favourites', 'jtzl-bulletin' ),
			)
		);
	}

	/**
	 * Name the pagination arrows.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Incoming args, before bbPress merges its defaults.
	 * @return mixed
	 */
	public function filter_pagination_args( $args ) {
		return $this->name_glyphs(
			$args,
			array(
				'prev_text' => __( 'Previous page', 'jtzl-bulletin' ),
				'next_text' => __( 'Next page', 'jtzl-bulletin' ),
			)
		);
	}

	/**
	 * Prefix a hidden word to any of the named args whose value is glyph-only.
	 *
	 * The args arrive as whatever the caller passed, so a non-array is handed back
	 * untouched rather than coerced: bbPress accepts a query string or an object here
	 * too, and rewriting one of those would be a larger promise than naming a button.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed                $args  Incoming args.
	 * @param array<string,string> $names Arg key => the word to announce.
	 * @return mixed
	 */
	private function name_glyphs( $args, array $names ) {
		if ( ! is_array( $args ) || ScreenTier::None === $this->screen->tier() ) {
			return $args;
		}

		foreach ( $names as $key => $word ) {
			if ( ! isset( $args[ $key ] ) || ! is_string( $args[ $key ] ) ) {
				continue;
			}
			$value = $args[ $key ];

			/*
			 * Decode entities before detecting letters: raw `&times;` contains letters,
			 * while its rendered multiplication glyph does not. `\p{L}` supports every script.
			 */
			$plain = wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' === trim( $plain ) || preg_match( '/\p{L}/u', $plain ) ) {
				continue;
			}
			$args[ $key ] = sprintf(
				'<span class="bltn-sr-only">%s </span>%s',
				esc_html( $word ),
				$value
			);
		}

		return $args;
	}
}
