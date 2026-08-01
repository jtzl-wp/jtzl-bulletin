<?php
/**
 * Labels for the child-forum counts printed inside a forum row.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Names the two numbers `bbp_list_forums()` prints beside each child forum, which
 * upstream renders as a bare pair in brackets — `Accessories (2, 0)`.
 *
 * The pair is `(topic count, reply count)`. Nothing on the screen says so, and the
 * same forum's own row two lines up carries a labelled `Topics 2` `Posts 2` — where
 * `Posts` is `bbp_get_forum_post_count()`, topics AND replies together. So one forum
 * states its size twice in two shapes that share no arithmetic: on the dev fixture
 * Espresso Machines reads `Topics 23` `Posts 71` in its own row and `(23, 48)` as a
 * child of Gear. A reader who notices both is not missing a label, they are looking
 * at what appears to be two different answers (issue #74; 23 + 48 = 71).
 *
 * Labelling is the whole fix, and it needed no field policy to justify it: the
 * reskin tier's standing rule already requires every kept column to carry its label,
 * so the bracket was a defect against that rule rather than a case it had not
 * reached. Issue #74 settled the wider question as **C** — keep every field, and
 * make "every kept field carries a label" a standing rule rather than a description
 * of what the tier happened to do.
 *
 * Every other label on this tier is a CSS `::before` on the element holding the
 * value. That cannot work here: upstream concatenates both counts into a single text
 * node with no element around either. Hence the one label written in PHP — and still
 * no markup of ours, since the arguments bbPress already exposes for these strings
 * are what gets set.
 *
 * Scoped to the reskin tier, and that scope is the whole of the surface: the only caller
 * of `bbp_list_forums()` anywhere is bbPress's own `loop-single-forum.php`, and the takeover
 * screens render none of bbPress's templates — they build their forum rows from
 * `View\ForumRow`. So a call arriving here from a takeover screen is another plugin's, and
 * is left with bbPress's wording.
 *
 * @since 0.3.0
 */
class SubForumCountLabels {

	/**
	 * Separator between the two counts.
	 *
	 * A middot, not the comma upstream uses. `bbp_list_forums()` also joins the
	 * forums to each other with `", "` (bbPress's own `.css-sep::after` rule), so a
	 * comma inside the brackets and a comma between them would be one mark doing two
	 * jobs in a single line — half of why the bracket was hard to read at all. The
	 * tier already separates meta with a middot.
	 *
	 * @var string
	 */
	private const SEPARATOR = '·';

	/**
	 * The space that binds a label to its number, and both pairs to the middot.
	 *
	 * Non-breaking, so a bracket is never split across two lines. Naming the counts
	 * roughly doubles the length of this list — three forums at 12px no longer fit one
	 * line at 390px — and the wrap it now takes fell inside a bracket, ending a line on
	 * "(Topics 23 ·". Held together, the only break offered is between a forum's name
	 * and its counts, so whatever lands on a line is readable on that line.
	 *
	 * It replaces the spaces *inside* a label as well as the ones around it; see
	 * `bind()`.
	 *
	 * @var string
	 */
	private const BIND = "\u{00A0}";

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
	 * Name both counts on a reskin screen. Hooked on
	 * `bbp_after_list_forums_parse_args`, which hands over the merged arguments in
	 * the moment before the list is built.
	 *
	 * The label goes before its value (`Topics 2`, not `2 topics`) so it reads
	 * correctly at a count of one — the same rule the tier's CSS labels follow.
	 *
	 * Which counts appear is decided upstream and per forum: a category is given
	 * neither, so it yields an empty set and no stray bracket. Where only one of the
	 * two is asked for, that one still has to be named correctly — so the opening
	 * label is chosen rather than assumed, and the second label rides the separator,
	 * which `implode()` reaches only when both counts are present.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed list arguments.
	 * @return mixed
	 */
	public function filter_list_args( $args ) {
		if ( ! is_array( $args ) || ScreenTier::Reskin !== $this->screen->tier() ) {
			return $args;
		}

		$topics  = ! empty( $args['show_topic_count'] );
		$replies = ! empty( $args['show_reply_count'] );

		if ( ! $topics && ! $replies ) {
			return $args;
		}

		$reply_label = $this->bind( esc_html__( 'Replies', 'jtzl-bulletin' ) );
		$opening     = $topics
			? $this->bind( esc_html__( 'Topics', 'jtzl-bulletin' ) )
			: $reply_label;

		$args['count_before'] = ' (' . $opening . self::BIND;
		$args['count_sep']    = self::BIND . self::SEPARATOR . self::BIND . $reply_label . self::BIND;
		$args['count_after']  = ')';

		return $args;
	}

	/**
	 * Hold a label together, internally as well as against its number.
	 *
	 * Binding only the joins this class writes would leave the guarantee true in
	 * English and false everywhere else: the labels are translated, and a translation
	 * is under no obligation to be one word. A two-word rendering of "Replies" would
	 * put a break opportunity straight back inside the bracket — the one thing the
	 * binding exists to remove. Every run of whitespace in a label therefore collapses
	 * to a single non-breaking space, so the invariant holds in any locale.
	 *
	 * Safe on the escaped string rather than before it: escaping introduces entities,
	 * and no entity contains a space.
	 *
	 * @since 0.3.0
	 *
	 * @param string $label A translated, escaped label.
	 * @return string
	 */
	private function bind( string $label ): string {
		return (string) preg_replace( '/\s+/u', self::BIND, $label );
	}
}
