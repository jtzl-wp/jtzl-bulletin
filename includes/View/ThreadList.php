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
 * Runs a topics query and renders its rows, so the forum screen and the
 * load-more endpoint produce identical markup from identical args — an appended
 * thread is indistinguishable from one that arrived with the document.
 *
 * Rows are captured to a string rather than echoed. The forum screen has to know
 * whether a section is empty before it can decide whether to print that
 * section's label at all, and re-running the query to find out would risk the
 * two runs disagreeing.
 *
 * @since 0.1.0
 */
class ThreadList {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Thread row renderer.
	 *
	 * @var ThreadRow
	 */
	private ThreadRow $row;

	/**
	 * Read state, for the unread accent.
	 *
	 * @var ReadState
	 * @since 0.5.0
	 */
	private ReadState $reads;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp    WordPress/bbPress seam.
	 * @param ThreadRow        $row   Thread row renderer.
	 * @param ReadState        $reads Read state, for the unread accent.
	 */
	public function __construct( ContextInterface $wp, ThreadRow $row, ReadState $reads ) {
		$this->wp    = $wp;
		$this->row   = $row;
		$this->reads = $reads;
	}

	/**
	 * Render a topics query's rows and return them as markup.
	 *
	 * The loop gathers every row before any of them is rendered, which is the shape
	 * unread forced (#102): the accent is resolved for the whole page in one query
	 * rather than one per row, and that is only possible once the full set of IDs is
	 * known. Rendering inside the loop would have meant a lookup per row — fifteen
	 * queries a page where the SoW promises the plugin "adds no theme weight".
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
