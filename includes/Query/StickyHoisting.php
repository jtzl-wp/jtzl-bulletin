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
 * @since 0.3.0
 */
class StickyHoisting {

	private ScreenClassifier $screen;

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
