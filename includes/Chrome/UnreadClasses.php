<?php
/**
 * The unread accent on the rows bbPress renders itself.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;
use JTZL\Bulletin\Unread\ReadState;
use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Marks an unread row on the reskin tier by adding a class to the markup bbPress
 * prints, so the dot is drawn in CSS.
 *
 * @since 0.5.0
 */
class UnreadClasses {

	public const UNREAD_CLASS = 'bltn-is-unread';

	private ContextInterface $wp;

	private ScreenClassifier $screen;

	private ReadState $reads;

	private array $topics = array();

	private array $forums = array();

	public function __construct( ContextInterface $wp, ScreenClassifier $screen, ReadState $reads ) {
		$this->wp     = $wp;
		$this->screen = $screen;
		$this->reads  = $reads;
	}

	/**
	 * Resolve read state for a whole topics loop, on `bbp_has_topics`.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $has_topics Whether the loop has rows.
	 * @param mixed $query      The query bbPress just ran.
	 * @return mixed The first argument, untouched.
	 */
	public function prime_topics( $has_topics, $query = null ) {
		if ( $this->applies() ) {
			$this->topics += $this->reads->unread_topics( $this->ids_from( $query ), $this->reader() );
		}

		return $has_topics;
	}

	/**
	 * Resolve read state for a whole forums loop, on `bbp_has_forums`.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $has_forums Whether the loop has rows.
	 * @param mixed $query      The query bbPress just ran.
	 * @return mixed The first argument, untouched.
	 */
	public function prime_forums( $has_forums, $query = null ) {
		if ( $this->applies() ) {
			$this->forums += $this->reads->unread_forums( $this->ids_from( $query ), $this->reader() );
		}

		return $has_forums;
	}

	/**
	 * Add the unread class to a topic row, on `bbp_get_topic_class`.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $classes  The row's classes.
	 * @param mixed $topic_id The topic being classed.
	 * @return mixed
	 */
	public function filter_topic_class( $classes, $topic_id = 0 ) {
		return $this->add_class( $classes, $this->topics[ (int) $topic_id ] ?? false );
	}

	/**
	 * Add the unread class to a forum row, on `bbp_get_forum_class`.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $classes  The row's classes.
	 * @param mixed $forum_id The forum being classed.
	 * @return mixed
	 */
	public function filter_forum_class( $classes, $forum_id = 0 ) {
		return $this->add_class( $classes, $this->forums[ (int) $forum_id ] ?? false );
	}

	/**
	 * Announce an unread topic, on `bbp_theme_before_topic_title`.
	 *
	 * The dot itself is drawn in CSS from the row's class, which is what keeps this
	 * tier on bbPress's own markup. A pseudo-element cannot be relied on to reach a
	 * screen reader, though, and unread would then be carried by colour alone — so the
	 * words are printed into the row the way Chrome\RowActionLabels prints its own.
	 *
	 * @since 0.5.0
	 */
	public function announce_topic(): void {
		$this->announce( $this->topics[ $this->wp->get_topic_id() ] ?? false );
	}

	/**
	 * Announce an unread forum, on `bbp_theme_before_forum_title`.
	 *
	 * @since 0.5.0
	 */
	public function announce_forum(): void {
		$this->announce( $this->forums[ $this->wp->get_forum_id() ] ?? false );
	}

	/**
	 * Print the hidden phrase for an unread row.
	 *
	 * @since 0.5.0
	 *
	 * @param bool $unread Whether the row is unread.
	 */
	private function announce( bool $unread ): void {
		if ( ! $unread || ! $this->applies() ) {
			return;
		}

		printf(
			'<span class="bltn-sr-only">%s </span>',
			esc_html__( 'Unread — new posts.', 'jtzl-bulletin' )
		);
	}

	/**
	 * Whether this request is one whose rows we mark.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	private function applies(): bool {
		return ScreenTier::Reskin === $this->screen->tier() && $this->wp->is_user_logged_in();
	}

	/**
	 * The member whose read state applies, or 0 when nobody is signed in.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	private function reader(): int {
		return $this->wp->is_user_logged_in() ? $this->wp->get_current_user_id() : 0;
	}

	/**
	 * The post IDs in a bbPress loop's query.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $query The query bbPress just ran.
	 * @return array<int,int>
	 */
	private function ids_from( $query ): array {
		if ( ! is_object( $query ) || ! isset( $query->posts ) || ! is_array( $query->posts ) ) {
			return array();
		}

		$ids = array();
		foreach ( $query->posts as $post ) {
			// bbPress runs these loops with the default `fields`, so $post is a
			// WP_Post; a caller that asked for `ids` would hand over integers, and
			// both are cheaper to accept than to guard against.
			$ids[] = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
		}

		return $ids;
	}

	/**
	 * Append the unread class when the row is unread.
	 *
	 * An array arrives here — `bbp_get_topic_class()` passes the result of
	 * get_post_class() — but the value has been through a filter by then, so it is
	 * treated as untrusted: anything that is not an array is returned as it arrived
	 * rather than being coerced into one and quietly replacing another plugin's work.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $classes The row's classes.
	 * @param bool  $unread  Whether the row is unread.
	 * @return mixed
	 */
	private function add_class( $classes, bool $unread ) {
		if ( ! $unread || ! is_array( $classes ) ) {
			return $classes;
		}

		$classes[] = self::UNREAD_CLASS;

		return $classes;
	}
}
