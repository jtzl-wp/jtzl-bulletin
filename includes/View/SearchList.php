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
 * Runs a search query and renders its rows, so the search screen and its
 * load-more endpoint produce identical markup from identical args — an appended
 * result is indistinguishable from one that arrived with the document. Sibling to
 * View\ThreadList and View\ForumList, and captured to a string for the same
 * reason: the screen has to know whether anything matched before it can decide
 * between the list and the empty state, and re-running the query to find out would
 * risk the two runs disagreeing.
 *
 * What has no sibling is the dispatch. Every other loop we render returns one post
 * type; this one returns forums, topics and replies interleaved by date, which is
 * what bbPress's own loop-search.php picks a partial for. So each row is built by
 * the kind's own method and handed to one renderer.
 *
 * @since 0.3.0
 */
class SearchList {

	/**
	 * How much of a result's own words to show. Long enough that a matched phrase
	 * is usually inside it, short enough that the list still reads as a list.
	 */
	private const EXCERPT_LENGTH = 120;

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Result row renderer.
	 *
	 * @var SearchRow
	 */
	private SearchRow $row;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp  WordPress/bbPress seam.
	 * @param SearchRow        $row Result row renderer.
	 */
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
		ob_start();

		if ( $this->wp->has_search_results( $args ) ) {
			while ( $this->wp->the_search_results_loop() ) {
				$this->wp->the_search_result();

				$row = $this->row_for( $this->wp->get_search_result_post_type(), $this->wp->get_search_result_id() );
				if ( array() !== $row ) {
					$this->row->render( $row );
				}
			}
		}

		return (string) ob_get_clean();
	}

	/**
	 * Build the row for one result, or nothing for a post type we don't render.
	 *
	 * The empty return is not defensive padding. `bbp_get_post_types()` is a filter,
	 * and a plugin that registers a fourth bbPress post type puts it in the search
	 * query — at which point rendering it as one of the three we do know would put a
	 * wrong noun and someone else's data in front of the reader.
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
	 * The only kind with no author and no date: a forum is a place, and neither
	 * "who wrote it" nor "when" says anything a reader would use. Its thread count
	 * does, so that takes the slot.
	 *
	 * Its description goes through the seam's excerpt getter rather than
	 * get_forum_content() directly, which is what bounds it to the same length the
	 * other two rows use and what keeps a protected forum's password form from being
	 * stripped down and read as a description (raised by Qodo on #69).
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
	 * Carries whether it is closed, as View\ThreadRow and View\ForumRow already do
	 * for the same subjects on their own screens. Reachable only since issue #68 —
	 * a closed thread could not appear in a search result at all before that, so
	 * the row had never needed to say so.
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
	 * The one row whose headline is not a name, because a reply has none — bbPress
	 * writes "Reply To: {thread}" into the field and nothing displays it. Borrowing
	 * the thread's title instead is what was tried first, and it manufactured the
	 * duplicate: three replies matching inside one thread came back as three rows
	 * with the same headline, differing only in the smallest, greyest line on each.
	 *
	 * Headlining the reply's own words fixes that at the root, and it is also what
	 * the design says to do — the serif face is the reading face, reserved for post
	 * content, and a reply's content is the only content this row has. The thread it
	 * sits in is context, so it goes to the supporting line, where a thread result
	 * carries its preview and a forum result its description: same three lines
	 * everywhere, each holding the most useful thing that kind of result has.
	 *
	 * When there are no words to headline — a password-protected reply, which the
	 * seam withholds — the thread's name takes the headline back and the supporting
	 * line drops, rather than naming the thread twice.
	 *
	 * The link is bbp_get_reply_url(), which resolves to the thread on the right
	 * page with a #post-N anchor. P1 already made that landing work past page one.
	 *
	 * The one kind carrying no closed marker, deliberately. A reply is never closed;
	 * the thread around it is, and saying so on this row would be a statement about
	 * a post the reader is not looking at. The other two rows mark themselves.
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
