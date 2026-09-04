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
 * @since 0.3.0
 */
class SubForumCountLabels {

	private const SEPARATOR = '·';

	private const BIND = "\u{00A0}";

	private ScreenClassifier $screen;

	public function __construct( ScreenClassifier $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Name both counts on a reskin screen. Hooked on
	 * `bbp_after_list_forums_parse_args`, which hands over the merged arguments in
	 * the moment before the list is built.
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
	 * @since 0.3.0
	 *
	 * @param string $label A translated, escaped label.
	 * @return string
	 */
	private function bind( string $label ): string {
		return (string) preg_replace( '/\s+/u', self::BIND, $label );
	}
}
