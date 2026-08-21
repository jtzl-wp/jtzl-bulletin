<?php
/**
 * The arguments the REST collections that answer for no one place are queried with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\SearchQuery;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Search, and a member's own posts: the three collections with no anchor.
 *
 * ## Why these are not in Rest\CollectionRepository
 *
 * Two reasons, and the second is the one that matters. The first is arithmetic — that
 * class is at its size gate and these would not fit. The second is that "anchored" is
 * a real property and not a filing convenience.
 *
 * Every collection over there is reached through something `Rest\AccessPolicy` has
 * already ruled on: a forum's threads, a thread's replies. By the time the rows are
 * fetched, the one container they can have come from is known to be readable. These
 * three have no such container. A row can arrive from anywhere on the site, so the
 * question "may this reader have the thing this row sits inside" has to be asked of
 * every row, inside the query, before it is counted.
 *
 * That question has two halves and two different owners:
 *
 * | Collection | Forum scope and passwords | The parent thread's status |
 * |---|---|---|
 * | `/search` | `Rest\CollectionVisibility::scope()` | Query\SearchVisibility, from bbPress's own captured list |
 * | `/users/{id}/topics` | `Rest\CollectionVisibility::scope()` | n/a — a topic is its own parent |
 * | `/users/{id}/replies` | `Rest\CollectionVisibility::scope_replies()` | the same call, with this reader's readable statuses |
 *
 * ⚠ **The scope marker is as easy to leave off here as it is there**, and with the
 * same consequence: not a refusal and not a warning, but the site's content in place
 * of this reader's under a matching `X-WP-Total`. Tests\Rest\UnanchoredRepositoryTest
 * asserts it on the arguments of every method in this class, including the recount —
 * which is the half a test written against a route would not see.
 *
 * ## Search runs through bbPress, and that is not a preference either
 *
 * `bbp_has_search_results()` is the exact path Query\SearchVisibility captures
 * bbPress's capability-derived status list on (`bbp_after_has_search_results_parse_args`).
 * A `WP_Query` built here instead would be a search with no `closed` topics in it and
 * with somebody's `private` ones — issue #68, reintroduced through the API.
 *
 * @since 0.6.0
 */
class UnanchoredRepository {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * The forum scope every collection is narrowed to.
	 *
	 * @var CollectionVisibility
	 * @since 0.6.0
	 */
	private CollectionVisibility $visibility;

	/**
	 * Shared search-query builder.
	 *
	 * @var SearchQuery
	 * @since 0.6.0
	 */
	private SearchQuery $search;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp         WordPress/bbPress seam.
	 * @param RestContextInterface $rest       REST seam.
	 * @param CollectionVisibility $visibility Collection scope.
	 * @param SearchQuery          $search     Shared search-query builder.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		CollectionVisibility $visibility,
		SearchQuery $search
	) {
		$this->wp         = $wp;
		$this->rest       = $rest;
		$this->visibility = $visibility;
		$this->search     = $search;
	}

	/**
	 * One page of search results, in the order bbPress returned them.
	 *
	 * Three post types interleaved by `(date, ID)` descending, each row reported with
	 * the post type it came from — the caller turns that into the app's vocabulary,
	 * because `bbp_get_*_post_type()` is filterable and a site's own slug is not part
	 * of any contract.
	 *
	 * ⚠ **The terms are not checked here.** A blank `s` makes
	 * `bbp_has_search_results()` read `->posts` off a property it never assigned; the
	 * route refuses before it gets this far, and Query\SearchQuery::is_runnable()
	 * carries the reasoning.
	 *
	 * @since 0.6.0
	 *
	 * @param string $terms    What to search for; already known to be non-blank.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page, already bounded.
	 * @return array{results:array<int,array{id:int,type:string}>,total:int,total_pages:int}
	 */
	public function search( string $terms, int $page, int $per_page ): array {
		$results = array();

		if ( $this->wp->has_search_results( $this->search_args( $terms, $page, $per_page ) ) ) {
			while ( $this->wp->the_search_results_loop() ) {
				$this->wp->the_search_result();

				$results[] = array(
					'id'   => $this->wp->get_search_result_id(),
					'type' => $this->wp->get_search_result_post_type(),
				);
			}
		}

		$total = $this->search_total( $terms, $page, array() === $results );

		return array(
			'results'     => $results,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		);
	}

	/**
	 * One page of the threads a member started, newest first.
	 *
	 * @since 0.6.0
	 *
	 * @param int $author_id Member whose topics are wanted.
	 * @param int $page      1-based page number.
	 * @param int $per_page  Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function user_topics( int $author_id, int $page, int $per_page ): array {
		return $this->rest->query(
			$this->visibility->scope(
				$this->authored( $author_id, $page, $per_page ) + array(
					'post_type'   => $this->wp->get_topic_post_type(),
					// Named, and narrower than what WordPress would build unasked:
					// this is the same capability-derived list Rest\AccessPolicy
					// answers the singular topic route from, so a thread absent here
					// is a thread `/topics/{id}` would refuse. It is also what leaves
					// `pending` out — a profile is a public page, and a held thread on
					// its author's own view of it is one screenshot from being public.
					'post_status' => $this->wp->get_readable_topic_statuses(),
				)
			)
		);
	}

	/**
	 * One page of the replies a member wrote, newest first.
	 *
	 * ⚠ **`scope_replies()`, never plain `scope()`.** A reply's own `post_status` is
	 * honest and incomplete: bbPress has no write path that rewrites a reply when the
	 * thread above it is hidden, closed to this reader, or given a password, so every
	 * one of those rows is an ordinary `publish` in a forum anybody may open. The
	 * marker this adds is what asks about the parent.
	 *
	 * ⚠ **The imported orphan is refused here, unlike on the website.** The website's
	 * clause asks about the parent's *status* and lets a reply whose parent no longer
	 * exists through, having no status to judge. The forum scope beneath it asks a
	 * different question — is this row inside a forum you may open — and answers it with
	 * an `EXISTS` on the parent, so a reply attached to nothing is inside no forum and
	 * drops out of every REST collection. That is the coherent answer rather than an
	 * oversight: `Rest\AccessPolicy::reply()` has refused an orphan since it was
	 * written, so listing one would advertise a row the app cannot open.
	 *
	 * @since 0.6.0
	 *
	 * @param int $author_id Member whose replies are wanted.
	 * @param int $page      1-based page number.
	 * @param int $per_page  Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function user_replies( int $author_id, int $page, int $per_page ): array {
		return $this->rest->query(
			$this->visibility->scope_replies(
				$this->authored( $author_id, $page, $per_page ) + array(
					'post_type'   => $this->wp->get_reply_post_type(),
					'post_status' => $this->wp->get_public_reply_statuses(),
				),
				$this->wp->get_readable_topic_statuses()
			)
		);
	}

	/**
	 * The search arguments, scoped — built in one place so the page and any recount
	 * of it are the same query asked about different rows.
	 *
	 * @since 0.6.0
	 *
	 * @param string $terms    What to search for.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page.
	 * @return array<string,mixed>
	 */
	private function search_args( string $terms, int $page, int $per_page ): array {
		return $this->visibility->scope( $this->search->args( $terms, $page, $per_page ) );
	}

	/**
	 * How many results the whole search matched, rather than how many this page got.
	 *
	 * ⚠ **WordPress does not count a page past the end.**
	 * `WP_Query::set_found_posts()` returns early when the page came back as an empty
	 * array, so `found_posts` is 0 — and page 9 of a three-result search would tell the
	 * app the search it has just finished reading matched nothing, while page 1 of the
	 * same search says three. WordPress\RestContext::query() recounts for the
	 * collections it builds; this one goes through bbPress instead, so the recount
	 * happens here.
	 *
	 * One row, not a page of them: `SQL_CALC_FOUND_ROWS` is added whenever there is a
	 * LIMIT at all, so the count is the same either way and the second query fetches
	 * nothing it will not use. It runs only when a page came back empty and was not
	 * the first.
	 *
	 * @since 0.6.0
	 *
	 * @param string $terms      What was searched for.
	 * @param int    $page       The page that was asked for.
	 * @param bool   $empty_page Whether it came back with nothing.
	 * @return int
	 */
	private function search_total( string $terms, int $page, bool $empty_page ): int {
		if ( $empty_page && $page > 1 ) {
			$this->wp->has_search_results( $this->search_args( $terms, 1, 1 ) );
		}

		return $this->wp->get_search_result_count();
	}

	/**
	 * The parts both authored collections share.
	 *
	 * A `(created, ID)` descending order, which is neither of the website's: a forum's
	 * thread list sorts by `_bbp_last_active_time`, because a reader of a forum wants
	 * whatever moved most recently. A profile is a different question — what this
	 * person wrote, and when — and a thread somebody else replied to today does not
	 * belong at the top of it. The ID tiebreak is there for the reason every order in
	 * this plugin has one: `post_date` is a DATETIME, an import ties whole runs of rows
	 * on it, and paging an unstable sort serves one row twice and drops another
	 * (CLAUDE.md trap #4).
	 *
	 * ⚠ **`author__in`, not `author`.** `WP_Query` treats `author => 0` as *unset* and
	 * drops the constraint, so a profile query built that way for a member who does not
	 * exist would answer with every post on the site. `author__in => array( 0 )`
	 * compiles to a bound `post_author IN (0)` that cannot. The routes gate on
	 * `Rest\AccessPolicy::user()` first and never call this with 0; the argument is
	 * chosen so that being wrong about that is a narrow answer rather than the site's.
	 *
	 * ⚠ **And no `meta_key`.** Query\TopicQuery carries one for its own order, and
	 * borrowing that base here would keep it: `meta_key` alone makes `WP_Query` join
	 * `postmeta` and require the row, so every topic that never had a last-active stamp
	 * would silently vanish from its author's profile.
	 *
	 * @since 0.6.0
	 *
	 * @param int $author_id Member whose posts are wanted.
	 * @param int $page      1-based page number.
	 * @param int $per_page  Rows per page.
	 * @return array<string,mixed>
	 */
	private function authored( int $author_id, int $page, int $per_page ): array {
		return array(
			'author__in'     => array( max( 0, $author_id ) ),
			'posts_per_page' => max( 1, $per_page ),
			'paged'          => max( 1, $page ),
			'orderby'        => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
		);
	}
}
