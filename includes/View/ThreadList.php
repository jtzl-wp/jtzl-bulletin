<?php
/**
 * A run of thread rows.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\View;

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
	 * Constructor.
	 *
	 * @param ContextInterface $wp  WordPress/bbPress seam.
	 * @param ThreadRow        $row Thread row renderer.
	 */
	public function __construct( ContextInterface $wp, ThreadRow $row ) {
		$this->wp  = $wp;
		$this->row = $row;
	}

	/**
	 * Render a topics query's rows and return them as markup.
	 *
	 * @param array<string,mixed> $args Topic query args (see Query\TopicQuery).
	 * @return string Markup, or '' when the query matched nothing.
	 */
	public function capture( array $args ): string {
		ob_start();

		if ( $this->wp->has_topics( $args ) ) {
			while ( $this->wp->the_topics_loop() ) {
				$this->wp->the_topic();

				$topic_id = $this->wp->get_topic_id();
				$this->row->render(
					array(
						'permalink' => $this->wp->get_topic_permalink( $topic_id ),
						'title'     => $this->wp->get_topic_title( $topic_id ),
						'author'    => $this->wp->get_topic_author_name( $topic_id ),
						'active'    => $this->wp->get_topic_last_active_time( $topic_id ),
						'replies'   => $this->wp->get_topic_reply_count( $topic_id ),
					)
				);
			}
		}

		return (string) ob_get_clean();
	}
}
