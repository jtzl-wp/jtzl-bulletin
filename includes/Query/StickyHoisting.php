<?php
/**
 * Sticky hoisting in the loops bbPress queries on our behalf.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Switches off bbPress's sticky hoisting in the topic loops it builds for a reskin
 * screen, leaving a plain freshness list.
 *
 * Upstream prepends stickies to page 1 of a topic list — and then neither excludes
 * them from later pages nor trims page 1 back to its own page size, while adding the
 * separately-fetched ones to `found_posts` and `post_count`. One injection, three
 * wrong answers: a sticky whose activity puts it on a later page is served twice, the
 * first page carries more rows than a page, and the total the screen prints counts
 * those stickies again — they are ordinary topics, so they were already in it. On the
 * dev fixture that read "Viewing 16 topics - 1 through 15 (of 28 total)" against a
 * real 27 (issue #38, split out of #45).
 *
 * Only one reskin screen is affected, because `show_stickies` defaults to
 * `bbp_is_single_forum() || bbp_is_topic_archive()` and a single forum is a takeover:
 * the topic archive. Which is also why declining the hoist costs nothing here.
 *
 * - The archive does not say anything about pinning in the first place. Pinned rows
 *   are shown unmarked there (issue #43) — deliberately, because bbPress hoists only
 *   SUPER stickies on the archive, so a forum-level sticky keeps its date position and
 *   a marker would promise a place most pinned rows don't have. An unexplained row at
 *   the top was the whole of what hoisting bought.
 * - The signal keeps a home, a better one. The takeover forum screen queries pinned
 *   topics separately under a "Pinned" section label, and its list includes site-wide
 *   super stickies in every forum (see Query\TopicQuery and
 *   ContextInterface::get_sticky_topic_ids), so a super sticky is labelled and first
 *   on every forum a reader actually navigates to — rather than silently first on an
 *   archive nothing links.
 *
 * Everything else keeps bbPress's behaviour: a takeover screen builds its own thread
 * list (TopicQuery, which excludes stickies from the paginated set and queries them
 * separately, precisely to avoid this), and off our screens the query belongs to the
 * theme or another plugin.
 *
 * @since 0.3.0
 */
class StickyHoisting {

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
	 * Decline the hoist on a reskin screen. Hooked on
	 * `bbp_after_has_topics_parse_args`, which hands over the merged arguments in the
	 * moment before bbPress runs the query.
	 *
	 * On the screens we render, this is our call to make — so an explicit
	 * `show_stickies` from a caller is overridden rather than honoured. Elsewhere the
	 * arguments are returned exactly as they arrived.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed query arguments.
	 * @return mixed
	 */
	public function filter_topic_args( $args ) {
		if ( ! is_array( $args ) || ScreenTier::Reskin !== $this->screen->tier() ) {
			return $args;
		}

		$args['show_stickies'] = false;

		return $args;
	}
}
