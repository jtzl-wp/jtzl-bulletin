<?php
/**
 * A run of forum rows.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\Unread\ReadState;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Runs a forums query and renders its rows, so the forums index, a forum's
 * sub-forum section and the load-forums endpoint all produce identical markup from
 * identical args — an appended forum is indistinguishable from one that arrived
 *
 * @since 0.3.0
 */
class ForumList {

	private const DESCRIPTION_WORDS = 22;

	private ContextInterface $wp;

	private ForumRow $row;

	private ReadState $reads;

	public function __construct( ContextInterface $wp, ForumRow $row, ReadState $reads ) {
		$this->wp    = $wp;
		$this->row   = $row;
		$this->reads = $reads;
	}

	/**
	 * Render a forums query's rows and return them as markup, and leave the global
	 * post as it was found.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Forum query args (see Query\ForumQuery).
	 * @return string Markup, or '' when the query matched nothing.
	 */
	public function capture( array $args ): string {
		$rows = array();

		if ( $this->wp->has_forums( $args ) ) {
			while ( $this->wp->the_forums_loop() ) {
				$this->wp->the_forum();
				$rows[] = $this->fields( $this->wp->get_forum_id() );
			}
			$this->wp->reset_postdata();
		}

		$unread = $this->reads->unread_forums(
			array_column( $rows, 'id' ),
			$this->wp->is_user_logged_in() ? $this->wp->get_current_user_id() : 0
		);

		ob_start();
		foreach ( $rows as $row ) {
			$row['unread'] = $unread[ $row['id'] ] ?? false;
			$this->row->render( $row );
		}

		return (string) ob_get_clean();
	}

	/**
	 * The row fields for one forum.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return array<string,mixed>
	 */
	private function fields( int $forum_id ): array {
		$description = $this->wp->is_password_required( $forum_id )
			? ''
			: wp_strip_all_tags( $this->wp->get_forum_content( $forum_id ) );
		$last_id     = $this->wp->get_forum_last_active_id( $forum_id );

		return array(
			'id'          => $forum_id,
			'permalink'   => $this->wp->get_forum_permalink( $forum_id ),
			'title'       => $this->wp->get_forum_title( $forum_id ),
			'description' => '' !== $description ? wp_trim_words( $description, self::DESCRIPTION_WORDS, '…' ) : '',
			'topics'      => $this->wp->get_forum_topic_count( $forum_id ),
			'author'      => $this->wp->get_author_name( $last_id ),

			// Guarded on the last-active ID so the row's byline stays whole. The two
			// halves come from independent meta: _bbp_last_active_id, which is what
			// names the author, and _bbp_last_active_time, which bbPress will also
			// derive from the forum's last reply or topic when its own key is empty.
			// They can disagree, and a freshness time with nobody attached to it reads
			// worse than no freshness at all.
			'active'      => $last_id > 0 ? $this->wp->get_forum_last_active_time( $forum_id ) : '',

			// Shown on a password-protected forum too, on the same reasoning as the
			// count and freshness beside it: a password gates what a forum holds,
			// not whether it is open.
			'closed'      => $this->wp->is_forum_closed( $forum_id ),
		);
	}
}
