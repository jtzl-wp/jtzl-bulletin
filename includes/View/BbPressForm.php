<?php
/**
 * Rendering one of bbPress's own forms on the takeover tier.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

/**
 * Renders a bbPress form template unmodified, with two corrections applied around
 * the call and removed after it.
 *
 * This plugin registers no `bbp_register_template_stack()` and never has, so the
 * fields, the nonces, the anonymous block and the whole write path stay upstream's.
 * What this class owns is the two things that stop being safe once those templates
 * render inside **our** document — and it owns them in one place, because both
 * composers hit both.
 *
 * ## The unescaped legend
 *
 * bbPress builds each form's legend by interpolating a title into an escaped format
 * string, which escapes the format and not the title:
 *
 * - `printf( esc_html__( 'Reply To: %s' ), bbp_get_topic_title() )` — `form-reply.php:30`
 * - `printf( esc_html__( 'Create New Topic in &ldquo;%s&rdquo;' ), bbp_get_forum_title() )`
 *   — `form-topic.php:46`
 *
 * That is upstream behaviour on every bbPress theme and not a bug this plugin
 * introduced. But until P4 no takeover screen rendered those templates: the reading
 * view escaped the topic title in all three places it appeared, and the forum screen
 * did the same with the forum's. Rendering the forms is what puts both in reach, so
 * closing it is this phase's job — and doing it here rather than in each caller is
 * what stops the second composer rediscovering it.
 *
 * ⚠ `wp_strip_all_tags()` and not `esc_html()`. A title legitimately containing
 * `&amp;` would come back `&amp;amp;` from escaping something bbPress escapes again.
 * Stripping removes markup and leaves entities alone — and it is the right transform
 * on its own terms, because a legend is an accessible name, and a name is text.
 *
 * ## The allowed-tags list
 *
 * A notice box enumerating every permitted HTML tag with its attributes, nested
 * between the field and the submit. Dropped on this tier because **decision 1 supplies
 * the replacement**: with the quicktags strip kept, the buttons *are* the discoverable
 * version of that information, and the list is the desktop-era fallback for people
 * hand-writing HTML. Removing something that has a better replacement in place is the
 * strongest form of the subtraction rule, not the weakest. It stays on the reskin
 * tier, where bbPress's own layout makes it unremarkable.
 *
 * Both filters are added and removed around the single call, in a `finally` for the
 * reason `WordPressContext::moderation_links()` uses one: bbPress runs plugin hooks
 * while rendering, and one of those throwing would otherwise leave them attached for
 * the rest of the request — stripping markup out of a title somewhere that wanted it.
 *
 * @since 0.5.0
 */
class BbPressForm {

	/**
	 * A template name chosen so that bbPress will not find it.
	 *
	 * ⚠ **An empty array would be the obvious way to suppress a template part, and it
	 * raises a PHP warning.** `bbp_locate_template()` reads `$template_name` after its
	 * own `foreach` to pass into the `bbp_locate_template` action
	 * (`core/template-functions.php:99`), so with nothing to iterate the variable was
	 * never assigned. Naming a file that cannot exist lets the loop run once, find
	 * nothing, and leave `$located` false — the same outcome, without reaching into
	 * upstream's scope.
	 *
	 * @since 0.5.0
	 *
	 * @var string
	 */
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
