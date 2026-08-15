<?php
/**
 * The doubled full stop at the end of an edit record.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Chrome;

/**
 * Removes the second full stop from "…was modified 3 days ago by Mara K..".
 *
 * The sentence upstream renders ends in a period — `'This reply was modified %1$s by
 * %2$s.'` (`replies/template.php:766`) — and `%2$s` is a display name. A great many forum
 * display names end in an initial: "Mara K.", "J.", "Smith Jr.". The two periods
 * meet and the line reads as a typo in a place a reader has no way to attribute to
 * anything but us, because the whole line is inside our chrome.
 *
 * ## Why this and not the obvious alternatives
 *
 * **Not by overriding the string.** It is translated, and a `gettext` filter naming
 * the English source would silently stop working in every other locale — which is
 * worse than the stop, because it fails invisibly and only for people who are not
 * reading the language it was tested in.
 *
 * **Not by trimming the display name.** The period belongs to the name; "Mara K"
 * is a different person's name than the one they chose, and it would then be wrong
 * everywhere the name appears rather than right here.
 *
 * So the correction is made where the two actually collide: in the rendered markup,
 * the only place both halves exist at once. A locale whose sentence does not end in
 * a period never matches, and a display name that does not end in one never matches
 * either — the pattern requires both.
 *
 * ⚠ **Not scoped to a screen tier**, deliberately, unlike most of this namespace.
 * The doubled stop is equally wrong on the reskin tier and it is a correction rather
 * than a restyle, so there is no surface on which the old rendering was preferable.
 *
 * @since 0.5.0
 */
class RevisionLogStop {

	/**
	 * A period ending the link text, immediately followed by the sentence's own.
	 *
	 * ⚠ **The two stops are not adjacent in the markup**, which is what a first
	 * attempt at this got wrong. `bbp_get_author_link()` wraps the name in its own
	 * span and the avatar in another, both inside the anchor, so the real shape is
	 * `Mara K.</span></a>.` — the closing tags sit between the pair. Hence the middle
	 * group, which swallows any number of closing tags and puts them back untouched.
	 *
	 * Still anchored to `</a>` on one side and a literal stop on the other, so it can
	 * only fire on the collision it is for. A locale whose sentence does not end in a
	 * period never matches; nor does a display name that does not. It needs both.
	 *
	 * @var string
	 */
	private const DOUBLED = '~\.((?:</[a-z]+>)*)</a>\.~i';

	/**
	 * Drop the second stop. Hooked on both revision-log getters.
	 *
	 * ⚠ **Both guards say the same thing: hand back what you were given rather than
	 * something you are not sure of.** The first covers `false`, which is what the
	 * getter returns for a post with no revisions — the commoner of the two cases by
	 * far. The second covers `preg_replace()` answering `null` on a PCRE failure
	 * (backtrack or recursion limits): `(string) null` is `''`, so casting would have
	 * blanked the whole edit record instead of leaving it alone. Unreachable on
	 * bbPress's own bounded markup, and the point is that the *failure mode* should
	 * not depend on that staying true — a filter that erases the thing it was asked to
	 * tidy is worse than one that does nothing. Raised by Gitar on PR 5.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $log Rendered revision log, or false when there is none.
	 * @return mixed
	 */
	public function filter_revision_log( $log ) {
		if ( ! is_string( $log ) ) {
			return $log;
		}

		$tidied = preg_replace( self::DOUBLED, '.$1</a>', $log );

		return is_string( $tidied ) ? $tidied : $log;
	}
}
