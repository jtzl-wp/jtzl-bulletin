<?php
/**
 * A run of search result rows.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders identical search rows for the initial screen and load-more endpoint.
 *
 * @since 0.3.0
 */
class SearchList {

	private const EXCERPT_LENGTH = 120;

	private ContextInterface $wp;

	private SearchRow $row;

	public function __construct( ContextInterface $wp, SearchRow $row ) {
		$this->wp  = $wp;
		$this->row = $row;
	}

	/**
	 * Render a search query's rows and return them as markup.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Search query args (see Query\SearchQuery).
	 * @return string Markup, or '' when the query matched nothing.
	 */
	public function capture( array $args ): string {
		// Use the query's actual terms. WordPress accepts array-valued `s`, which must
		// not be cast to the visible string "Array" and highlighted in every row.
		$raw   = $args['s'] ?? '';
		$terms = is_scalar( $raw ) ? (string) $raw : '';

		ob_start();

		if ( $this->wp->has_search_results( $args ) ) {
			while ( $this->wp->the_search_results_loop() ) {
				$this->wp->the_search_result();

				$row = $this->row_for( $this->wp->get_search_result_post_type(), $this->wp->get_search_result_id() );
				if ( array() !== $row ) {
					$row['terms'] = $terms;
					$this->row->render( $row );
				}
			}
		}

		return (string) ob_get_clean();
	}

	/**
	 * Build the row for one result, or nothing for a post type we don't render.
	 *
	 * Unknown bbPress post types must not be mislabelled.
	 *
	 * @since 0.3.0
	 *
	 * @param string $post_type Result post type.
	 * @param int    $id        Result ID.
	 * @return array<string,mixed>
	 */
	private function row_for( string $post_type, int $id ): array {
		if ( $post_type === $this->wp->get_forum_post_type() ) {
			return $this->forum_row( $id );
		}
		if ( $post_type === $this->wp->get_topic_post_type() ) {
			return $this->topic_row( $id );
		}
		if ( $post_type === $this->wp->get_reply_post_type() ) {
			return $this->reply_row( $id );
		}

		return array();
	}

	/**
	 * A forum result.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return array<string,mixed>
	 */
	private function forum_row( int $forum_id ): array {
		$topics = $this->wp->get_forum_topic_count( $forum_id );

		return array(
			'permalink' => $this->wp->get_forum_permalink( $forum_id ),
			'title'     => $this->wp->get_forum_title( $forum_id ),
			'kind'      => __( 'Forum', 'jtzl-bulletin' ),
			'closed'    => $this->wp->is_forum_closed( $forum_id ),
			'author'    => '',
			/* translators: %s: formatted thread count. */
			'date'      => sprintf( _n( '%s thread', '%s threads', $topics, 'jtzl-bulletin' ), number_format_i18n( $topics ) ),
			'sub'       => $this->wp->get_forum_excerpt( $forum_id, self::EXCERPT_LENGTH ),
		);
	}

	/**
	 * A thread result.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return array<string,mixed>
	 */
	private function topic_row( int $topic_id ): array {
		return array(
			'permalink' => $this->wp->get_topic_permalink( $topic_id ),
			'title'     => $this->wp->get_topic_title( $topic_id ),
			'kind'      => __( 'Thread', 'jtzl-bulletin' ),
			'closed'    => $this->wp->is_topic_closed( $topic_id ),
			'author'    => $this->wp->get_topic_author_name( $topic_id ),
			'date'      => $this->wp->get_topic_post_date( $topic_id ),
			'sub'       => $this->wp->get_topic_excerpt( $topic_id, self::EXCERPT_LENGTH ),
		);
	}

	/**
	 * A reply result, headlined by what the reply actually says.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return array<string,mixed>
	 */
	private function reply_row( int $reply_id ): array {
		$topic_id = $this->wp->get_reply_topic_id( $reply_id );
		$thread   = $topic_id > 0 ? $this->wp->get_topic_title( $topic_id ) : '';
		$words    = $this->wp->get_reply_excerpt( $reply_id, self::EXCERPT_LENGTH );
		$named    = '' !== $thread ? $thread : $this->wp->get_reply_title( $reply_id );

		if ( '' === $words && '' === $named ) {
			return array();
		}

		return array(
			'permalink' => $this->wp->get_reply_url( $reply_id ),
			'title'     => '' !== $words ? $words : $named,
			'kind'      => __( 'Reply', 'jtzl-bulletin' ),
			'author'    => $this->wp->get_reply_author_display_name( $reply_id ),
			'date'      => $this->wp->get_reply_post_date( $reply_id ),
			/* translators: %s: the thread a reply was posted in. */
			'sub'       => '' !== $words && '' !== $thread ? sprintf( __( 'in %s', 'jtzl-bulletin' ), $thread ) : '',
		);
	}
}
