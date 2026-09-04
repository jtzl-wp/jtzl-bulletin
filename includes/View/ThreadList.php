<?php
/**
 * A run of thread rows.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\Unread\ReadState;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders identical topic rows for the initial forum and load-more endpoint.
 *
 * Capturing lets the forum omit empty section labels without running the query twice.
 *
 * @since 0.1.0
 */
class ThreadList {

	private ContextInterface $wp;

	private ThreadRow $row;

	private ReadState $reads;

	public function __construct( ContextInterface $wp, ThreadRow $row, ReadState $reads ) {
		$this->wp    = $wp;
		$this->row   = $row;
		$this->reads = $reads;
	}

	/**
	 * Render a topics query's rows and return them as markup.
	 *
	 * Gather IDs before rendering so unread state is resolved once for the page.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $args Topic query args (see Query\TopicQuery).
	 * @return string Markup, or '' when the query matched nothing.
	 */
	public function capture( array $args ): string {
		$rows = array();

		if ( $this->wp->has_topics( $args ) ) {
			while ( $this->wp->the_topics_loop() ) {
				$this->wp->the_topic();

				$topic_id = $this->wp->get_topic_id();
				$rows[]   = array(
					'id'        => $topic_id,
					'permalink' => $this->wp->get_topic_permalink( $topic_id ),
					'title'     => $this->wp->get_topic_title( $topic_id ),
					'author'    => $this->wp->get_topic_author_name( $topic_id ),
					'active'    => $this->wp->get_topic_last_active_time( $topic_id ),
					'replies'   => $this->wp->get_topic_reply_count( $topic_id ),

					// The topic's own status. A thread inside a closed forum is
					// marked on the forum, not on every one of its threads.
					'closed'    => $this->wp->is_topic_closed( $topic_id ),
				);
			}
		}

		$unread = $this->reads->unread_topics(
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
}
