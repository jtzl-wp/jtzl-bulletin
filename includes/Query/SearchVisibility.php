<?php
/**
 * Which posts a search may return to the reader running it.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Gives a search query back the post statuses bbPress computed for it and then
 * discarded (issue #68).
 *
 * `bbp_has_search_results()` works out, carefully, which statuses this reader may
 * see (`includes/search/template.php:50-62`): the public topic statuses, plus
 * `private` if they hold `read_private_topics`, plus `hidden` if they hold
 * `read_hidden_topics`. Four hook-priorities later
 * `bbp_pre_get_posts_normalize_forum_visibility()` throws all of it away, because a
 * search asks for `forum` among its post types and that function answers a forums
 * query by setting `post_status` to every forum visibility there is
 * (`includes/forums/functions.php:2338`):
 *
 * ```php
 * if ( in_array( bbp_get_forum_post_type(), $post_types, true ) ) {
 *     $posts_query->set( 'post_status', array_keys( bbp_get_forum_visibilities() ) );
 * ```
 *
 * The result is wrong in both directions at once, measured on real bbPress 2.6.14
 * across four roles:
 *
 *  - **`closed` disappears.** It is one of the two *public* topic statuses, and no
 *    forum visibility is named `closed`, so the substitution deletes it. A closed
 *    thread is then unfindable by search for every reader including a keymaster —
 *    while its replies keep coming back, so a search hands over fragments of a
 *    thread whose opening post it says does not exist. This needs no unusual data:
 *    it is what happens when a moderator uses the Close action Bulletin shipped in
 *    issue #36.
 *  - **`private` and `hidden` appear.** A topic or reply carrying either while
 *    sitting in a public forum is returned to a logged-out reader.
 *
 * ## What is restored, and what is not
 *
 * bbPress's list is *captured*, never recomputed. Bulletin does not ask
 * `current_user_can()` anything here and names no status of its own: the array is
 * lifted off the parsed arguments and put back. So the decline recorded in
 * Query\ForumQuery — that visibility is bbPress's to decide — still holds, and this
 * cannot drift from bbPress as its capability rules change.
 *
 * ## Why it takes two steps
 *
 * One status string means two things. `private` on a forum the reader is entitled to
 * see is correct; `private` on a topic is the leak. Simply putting the captured list
 * back would therefore lose forum results that are legitimately theirs — measured:
 * a participant holds `read_private_forums` but not `read_private_topics`, so their
 * captured list is `[publish, closed]` and a private forum they may read would drop
 * out; nobody at all is granted `read_hidden_topics` under bbPress's default role
 * map, so a moderator's list omits `hidden` and a hidden forum would drop out too.
 *
 * What actually protects forum rows is the exclusion list the same function builds
 * from `bbp_exclude_forum_ids()` — `post__not_in` for the forums themselves and a
 * meta query for their contents — and that is untouched here. Status was never doing
 * that work.
 *
 * So: **widen**, then **subtract**. `post_status` becomes the union of what bbPress
 * set and what bbPress computed — strictly wider than today, so no result can
 * regress and `closed` returns — and the WHERE clause then admits any forum row plus
 * any row whose status is in the captured list. Two independent steps, each of which
 * can be reverted without the other.
 *
 * ## Scope
 *
 * The captured list rides the query object itself, under a key of ours, and is the
 * only thing that arms either step. So both are scoped by construction to a query
 * built by `bbp_has_search_results()` — there is no screen-tier condition to keep
 * true, and no state held between calls that a second search on the same request
 * could inherit. bbPress's own search template is covered as well as ours, which is
 * right: the defect is upstream and predates the search takeover (issue #35).
 *
 * Written to depend only on running *after* the normalizer, never on its internals.
 * Trunk (2.7.0-alpha-2) still performs the same substitution but has broadened the
 * guard around it and added a re-entry flag, so nothing here reads its shape.
 *
 * @since 0.3.0
 */
class SearchVisibility {

	/**
	 * The query variable bbPress's own status list rides on between the hook that
	 * captures it and the two that read it back.
	 *
	 * @var string
	 */
	private const CAPTURED = 'bltn_search_post_status';

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Take a copy of the statuses bbPress worked out, in the moment before the
	 * query that will discard them is built. Hooked on
	 * `bbp_after_has_search_results_parse_args`.
	 *
	 * The copy is added to the arguments rather than kept on this object, so it
	 * reaches `WP_Query::$query_vars` and can be read back off the query itself. An
	 * unrecognised argument is carried there untouched, which is what makes the
	 * rest of this stateless.
	 *
	 * Nothing is captured when there is nothing bbPress decided to restore, and
	 * with no copy on the query neither of the other two steps arms at all.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed search-query arguments.
	 * @return mixed
	 */
	public function capture_statuses( $args ) {
		if ( ! is_array( $args ) || ! isset( $args['post_status'] ) ) {
			return $args;
		}

		$statuses = $this->clean( $args['post_status'] );

		if ( array() === $statuses || $this->is_wildcard( $statuses ) ) {
			return $args;
		}

		$args[ self::CAPTURED ] = $statuses;

		return $args;
	}

	/**
	 * Whether a list is WP_Query's `any` rather than a list of statuses.
	 *
	 * `any` is a token, not a status: it means every status not flagged
	 * `exclude_from_search`, which no list of concrete names can stand in for. A
	 * site that filters the search arguments to it — `bbp_parse_args()` invites
	 * exactly that — has overridden the computation this class exists to restore,
	 * so the honest answer is to take no copy and leave their query alone. Binding
	 * the token literally instead would put `post_status IN ( 'any' )` in the WHERE
	 * clause and match no topic or reply at all.
	 *
	 * @since 0.3.0
	 *
	 * @param string[] $statuses Cleaned status list.
	 * @return bool
	 */
	private function is_wildcard( array $statuses ): bool {
		return in_array( 'any', $statuses, true );
	}

	/**
	 * Put the captured statuses back beside the ones the normalizer set. Hooked on
	 * `pre_get_posts` at priority 5 — immediately after bbPress's own pass at 4,
	 * which is the narrowest way to say "undo that one substitution".
	 *
	 * A union rather than a replacement: the visibilities the normalizer added are
	 * how a private or hidden *forum* the reader may see gets returned at all.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $query The query about to run.
	 */
	public function widen_statuses( $query ): void {
		$captured = $this->captured( $query );

		if ( array() === $captured ) {
			return;
		}

		$current = $this->clean( $this->wp->get_query_arg( $query, 'post_status' ) );

		$this->wp->set_query_arg(
			$query,
			'post_status',
			array_values( array_unique( array_merge( $current, $captured ) ) )
		);
	}

	/**
	 * And subtract again what only the forums half of that union was for: admit
	 * every forum row, and otherwise only a status bbPress said this reader may
	 * see. Hooked on `posts_where`.
	 *
	 * Forums are exempted rather than status-checked because their protection is
	 * the exclusion list, not the status — see the class docblock.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $where The WHERE clause built so far.
	 * @param mixed $query The query it belongs to.
	 * @return mixed
	 */
	public function restrict_statuses( $where, $query = null ) {
		$captured = $this->captured( $query );

		if ( ! is_string( $where ) || array() === $captured ) {
			return $where;
		}

		return $where . $this->wp->post_status_where_clause( $this->wp->get_forum_post_type(), $captured );
	}

	/**
	 * The captured list riding a query, or an empty array on any query that is not
	 * a bbPress search.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $query The query to read.
	 * @return string[]
	 */
	private function captured( $query ): array {
		return $this->clean( $this->wp->get_query_arg( $query, self::CAPTURED ) );
	}

	/**
	 * A list of post statuses as a plain, gap-free array of non-empty strings.
	 *
	 * `post_status` may reach us as a string, an array, or absent — WP_Query accepts
	 * all three and answers an unset variable with `''`.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $statuses Whatever was found.
	 * @return string[]
	 */
	private function clean( $statuses ): array {
		if ( ! is_array( $statuses ) ) {
			$statuses = array( $statuses );
		}

		$clean = array();

		foreach ( $statuses as $status ) {
			if ( is_string( $status ) && '' !== $status ) {
				$clean[] = $status;
			}
		}

		return array_values( array_unique( $clean ) );
	}
}
