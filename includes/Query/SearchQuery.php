<?php
/**
 * Canonical search-query arguments.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Builds the bbp_has_search_results() args used by BOTH the initial search render
 * and the load-more AJAX handler, so the two paths paginate identically.
 *
 * The order is the whole reason this class exists. bbPress defaults the search
 * query to `orderby => date, order => DESC` with no tiebreak, which is CLAUDE.md
 * trap #4 exactly: tied timestamps come back from MySQL in an undefined order, so
 * LIMIT/OFFSET paging puts different rows on page 1 than the continuation expects
 * — some results appear twice, others never appear at all. It is likelier here
 * than it was for replies, because a search spans three post types at once: a
 * topic and its own first reply routinely share a second, and an import that
 * carries only a date lands whole forums on one timestamp.
 *
 * Adding the ID tiebreak in one shared builder is what actually fixes it. The reply
 * bug was not that the order was wrong in one place — it was that the page render
 * and the continuation each derived their own, and the two disagreed.
 *
 * Everything else is left to bbPress: which post types are searched, which statuses
 * this reader may see (`read_private_topics` / `read_hidden_topics`), and the
 * per-page count all come from its defaults, and `bbp_has_search_results()` is what
 * maintains `found_posts` on the query object that max_pages() reads back.
 *
 * @since 0.3.0
 */
class SearchQuery {

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
	 * Search-query args for a set of terms and a 1-based page.
	 *
	 * ⚠ **The page size is omitted unless one is asked for**, rather than defaulted
	 * to `bbp_get_replies_per_page()` here. bbPress fills it in through
	 * `bbp_parse_args()`, which is a filter — a site that repaginates its own search
	 * does it there, and a value written into the arguments beforehand would override
	 * the site rather than defer to it. The website has never passed one; the API
	 * always does, because it pages to a size the request names.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $terms    Search terms.
	 * @param int      $page     1-based page number.
	 * @param int|null $per_page Rows per page, or null for the site's own.
	 * @return array<string,mixed>
	 */
	public function args( string $terms, int $page, ?int $per_page = null ): array {
		$args = array(
			's'       => $terms,
			'paged'   => max( 1, $page ),
			'orderby' => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
		);

		if ( null !== $per_page ) {
			$args['posts_per_page'] = max( 1, $per_page );
		}

		return $args;
	}

	/**
	 * How many pages of results the last search matched.
	 *
	 * Read off the query object rather than derived, unlike Query\ReplyQuery's: a
	 * page here really is a LIMIT over the whole result set, so bbPress's own count
	 * is the true one and a second derivation could only drift from it.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function max_pages(): int {
		return $this->wp->get_max_search_pages();
	}

	/**
	 * Whether a set of terms is worth running a query for.
	 *
	 * The terms-less search screen is a real screen, not an error — it is where the
	 * magnifier lands — but bbPress does not survive being asked to render it
	 * through us. `bbp_has_search_results()` only assigns `bbpress()->search_query`
	 * when `s` is non-empty, then reads `->posts` off it regardless; on the request
	 * that never searched, that property has been unset since `WP_Query::init()`
	 * ran. bbPress's own templates step around this by asking
	 * `bbp_get_search_terms()` first, in feedback-no-search.php, and so do we.
	 *
	 * @since 0.3.0
	 *
	 * @param string $terms Search terms.
	 * @return bool
	 */
	public function is_runnable( string $terms ): bool {
		return '' !== trim( $terms );
	}
}
