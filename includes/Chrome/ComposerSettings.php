<?php
/**
 * The editor bbPress hands the composer, on the screens Bulletin owns.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Two adjustments to the editor bbPress hands the composer, and no more.
 *
 * An editor arrives here from WordPress by way of `bbp_get_the_content()`, which
 * passes `tinymce => false` and `quicktags => true`. `bbp_use_wp_editor()` defaults
 * to `1` (`core/options.php:546`), so what lands is a plain textarea with core's
 * HTML button bar above it — **not** TinyMCE, which is a different decision from the
 * one this class would otherwise be making.
 *
 * @since 0.5.0
 */
class ComposerSettings {

	/**
	 * Textarea rows on the takeover tier.
	 *
	 * Twelve is bbPress's default, which is two-thirds of the reading view's ~700px
	 * content area spent on an empty field, on every thread including the ones nobody
	 * answers. Six is enough to see a paragraph while writing it and leaves the thread
	 * it belongs to on screen.
	 *
	 * @since 0.5.0
	 *
	 * @var int
	 */
	private const TAKEOVER_ROWS = 6;

	/**
	 * Screen-tier classifier.
	 *
	 * @var ScreenClassifier
	 */
	private ScreenClassifier $screen;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ScreenClassifier $screen Screen-tier classifier.
	 */
	public function __construct( ScreenClassifier $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Drop the `img` button from the formatting strip.
	 *
	 * One button of eleven, and the only one that asks a reader on a phone for a URL
	 * they do not have — every other button wraps text they have already typed. The
	 * SoW excludes attachments, so there is no picker behind it and nothing to paste
	 * but a link to an image hosted somewhere else. `code` and `link` stay: they are
	 * the two a forum reader actually reaches for.
	 *
	 * ⚠ Hooked on `bbp_get_quicktags_settings` rather than on core's own
	 * `quicktags_settings`, and that is the scoping. bbPress adds and removes its
	 * filter around each `wp_editor()` call it makes (`common/template.php:1936`,
	 * `:1956`), so ours can only ever reach an editor bbPress rendered — never one
	 * belonging to another plugin that happens to be on the same screen.
	 *
	 * `ScreenTier::None` is left alone on the same principle the style suppression
	 * follows: a `[bbp-topic-form]` shortcode on somebody's themed page is not ours
	 * to edit.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $settings Quicktags settings, as bbPress assembled them.
	 * @return mixed
	 */
	public function filter_quicktags( $settings ) {
		if ( ScreenTier::None === $this->screen->tier() || ! is_array( $settings ) ) {
			return $settings;
		}

		$buttons = isset( $settings['buttons'] ) && is_string( $settings['buttons'] )
			? $settings['buttons']
			: '';
		if ( '' === $buttons ) {
			return $settings;
		}

		$kept = array_diff( explode( ',', $buttons ), array( 'img' ) );

		$settings['buttons'] = implode( ',', $kept );

		return $settings;
	}

	/**
	 * Shrink the composer's textarea on the takeover tier.
	 *
	 * Takeover only. The reskin tier's edit forms are a different job — there a reader
	 * is looking at a post they already wrote, so the field wants to show it.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $args Parsed `bbp_get_the_content()` arguments.
	 * @return mixed
	 */
	public function filter_content_args( $args ) {
		if ( ScreenTier::Takeover !== $this->screen->tier() || ! is_array( $args ) ) {
			return $args;
		}

		$args['textarea_rows'] = (string) self::TAKEOVER_ROWS;

		return $args;
	}
}
