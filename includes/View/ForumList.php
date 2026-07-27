<?php
/**
 * A run of forum rows.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Runs a forums query and renders its rows, so the forums index, a forum's
 * sub-forum section and the load-forums endpoint all produce identical markup from
 * identical args — an appended forum is indistinguishable from one that arrived
 * with the document. Sibling to View\ThreadList, which does this for threads.
 *
 * It is also where the row data is gathered, which the two screen templates used to
 * do separately. They did it differently: the index read the ambient forum
 * (`bbp_get_forum_title()` with no argument), the sub-forum list passed an explicit
 * ID. Only the second form is safe — bbPress resolves the ambient forum from the
 * forums loop before the viewed forum, so the ambient reading is right by accident
 * on one screen and would be wrong on any screen that ran two forum loops. Every
 * getter here is called with the looped forum's ID.
 *
 * Rows are captured to a string rather than echoed, for the reason ThreadList does
 * the same: a screen has to know whether a section is empty before it can decide
 * whether to print that section's label, and re-running the query to find out would
 * risk the two runs disagreeing.
 *
 * @since 0.3.0
 */
class ForumList {

	/**
	 * Words a forum description is trimmed to in a row.
	 *
	 * @var int
	 */
	private const DESCRIPTION_WORDS = 22;

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Forum row renderer.
	 *
	 * @var ForumRow
	 */
	private ForumRow $row;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp  WordPress/bbPress seam.
	 * @param ForumRow         $row Forum row renderer.
	 */
	public function __construct( ContextInterface $wp, ForumRow $row ) {
		$this->wp  = $wp;
		$this->row = $row;
	}

	/**
	 * Render a forums query's rows and return them as markup, and leave the global
	 * post as it was found.
	 *
	 * The reset is here rather than left to callers because this class is the only
	 * thing that knows whether the loop ran. bbPress does reset it — bbp_forums()
	 * calls wp_reset_postdata() the moment have_posts() comes back false — but only on
	 * a drained loop, which makes the guarantee a property of how a caller happens to
	 * iterate. Owning it here costs one call and stops every caller having to know.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Forum query args (see Query\ForumQuery).
	 * @return string Markup, or '' when the query matched nothing.
	 */
	public function capture( array $args ): string {
		ob_start();

		if ( $this->wp->has_forums( $args ) ) {
			while ( $this->wp->the_forums_loop() ) {
				$this->wp->the_forum();
				$this->row->render( $this->fields( $this->wp->get_forum_id() ) );
			}
			$this->wp->reset_postdata();
		}

		return (string) ob_get_clean();
	}

	/**
	 * The row fields for one forum.
	 *
	 * The description is resolved here rather than in ForumRow because bbPress hands
	 * it over as filtered post HTML, which a row has no room for — so it is stripped
	 * and trimmed to a line.
	 *
	 * A protected forum has no description at all, which is not the same as stripping
	 * one. WordPress replaces protected content with its password FORM, so
	 * bbp_get_forum_content() returns that markup, and stripping its tags leaves the
	 * form's own prose behind: rows read "This content is password-protected. To view
	 * it, please enter the password below. Password:" as though it were what the forum
	 * is about. Withheld outright instead.
	 *
	 * The count and freshness are not withheld, matching bbPress's own forum loop: a
	 * password gates what a forum *holds*, not that it exists and is active.
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
