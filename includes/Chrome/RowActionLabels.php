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
 * The glyphs are hardcoded in bbPress's own loop templates — `loop-single-forum.php:24` and
 * `loop-single-topic.php:26,38` pass `'subscribe' => '+'` and
 * `'unsubscribe' => '&times;'` — so on a profile's Subscriptions and Favourites tabs
 * the only control on each row resolves to the accessible name **"times"**. It is
 * also destructive and unconfirmed: the one tap that removes a subscription is the
 * one a reader cannot identify.
 *
 * The visible glyph is kept. A visually-hidden word is put in front of it instead,
 * which is why this is an args filter rather than an `aria-label`: the name then
 * *is* the label, it travels through bbPress's own AJAX toggle (which re-renders the
 * link server-side, back through this filter), and it needs no ARIA to contradict.
 *
 * Only a value carrying **no letters** is treated as a glyph. That matters because
 * the same filter fires for Bulletin's own Subscribe control on the reading view,
 * where the label is already a word and must be left exactly as it is. The test is
 * the value, not the caller, so a theme or plugin passing its own wording is also
 * left alone.
 *
 * @since 0.3.0
 */
class RowActionLabels {

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
	 * @param ScreenClassifier $screen Screen-tier classifier.
	 */
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
	 * These default to `&larr;` / `&rarr;` in bbPress (`core/abstraction.php:294`), so
	 * the "next page" control on the reskin archives resolved to the bare glyph
	 * **"→"** — the only unnamed control measured anywhere in the build. Its
	 * siblings are page numbers, which name themselves; the arrows do not.
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
			 * A label that already contains a letter is already a name. `\p{L}` rather
			 * than `[a-z]`, so a translated word in any script counts as one.
			 *
			 * Decoded and stripped BEFORE the test, which is the whole subtlety here:
			 * bbPress writes the glyph as the entity `&times;`, and that string
			 * contains the letters t-i-m-e-s. Testing it raw therefore concluded the
			 * button was already named — the exact wrong answer, and silently, since
			 * a filter that declines looks identical to one that never ran. Decoded,
			 * `&times;` is U+00D7, a maths symbol with no letter in it.
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
