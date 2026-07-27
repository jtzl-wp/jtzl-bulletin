<?php
/**
 * The page number a continuation request asks for.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Ajax;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Reads `paged` off a load-more request, and refuses a page no list could have.
 * Shared by every continuation endpoint so the four of them cannot drift on what
 * a page number means (issue #51, item 2).
 *
 * Two methods rather than one, because their order matters at the call site.
 * `requested()` only parses — it touches nothing and ends nothing, so a controller
 * can read the page before it knows whether the reader may have it. `guard()` is a
 * refusal, and belongs *after* each controller's access checks: a logged-out
 * request for page 10^19 should be told it is forbidden, not that its page number
 * is out of range, or the endpoint answers a question it was never asked.
 *
 * ## What the ceiling is, and what it is not
 *
 * It is a bound on the integer, not on the query. A large `OFFSET` costs
 * `min( offset, matching rows )` — MySQL stops when the result set runs out — so
 * page 10^9 of a forum holding 200 topics costs what page 1 costs. The real
 * exposure was arithmetic: `(int) '99999999999999999999'` saturates to
 * `PHP_INT_MAX`, and the `$page + 1` every endpoint returns as `nextPage` then
 * overflows to a float, so the JSON contract broke before the database was ever
 * troubled. Refusing above a ceiling closes that, and states the endpoint's
 * contract: a page that could exist.
 *
 * ## Why a declared ceiling and not a derived one
 *
 * Ajax\LoadSubscribedForumsController derives its bound exactly, because the
 * subscription relationship itself is cheap to count. Nothing equivalent holds
 * here. bbPress does keep per-forum and per-topic counts in postmeta, and an
 * earlier sketch of this fix proposed using them — but those counts are
 * maintained by bbPress's own write paths, and an import that inserts posts
 * directly leaves them stale. bbPress ships repair tools precisely because they
 * drift. A bound derived from a stale count refuses pages a reader can legitimately
 * reach, which is the truncation bug of issues #38 and #50 reintroduced as a
 * security fix. Counting for real, per request, would put a `COUNT(*)` on the hot
 * path to save a scan that is already bounded — worse than the thing it buys.
 *
 * So: a declared number, high enough that no real list reaches it, and a filter
 * for the site that proves otherwise.
 *
 * ## Why these endpoints do not answer `out_of_range` for a merely empty page
 *
 * A page past the end of a real list is served as success with no rows and
 * `hasMore: false`, and the control removes itself — which is also the right
 * answer to a race, where a reader loads page 1 and a moderator trashes a thread
 * before they ask for page 2. Turning that into an error would break a legitimate
 * read. The ceiling is about pages no list could have, not pages this list does
 * not happen to have.
 *
 * @since 0.3.0
 */
class RequestedPage {

	/**
	 * The highest page any continuation will answer for, before filtering.
	 *
	 * At bbPress's default 15 replies per page that is 1.5 million replies in one
	 * thread; at 50 forums per page, 5 million forums. Both are orders of magnitude
	 * past any list a reader could page through, so nothing real is refused.
	 *
	 * @var int
	 */
	private const MAX_PAGE = 100000;

	/**
	 * Filter for the ceiling, for a site whose lists really are that long.
	 *
	 * @var string
	 */
	private const MAX_PAGE_FILTER = 'bltn_max_continuation_page';

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
	 * The page this request asks for, floored at the first page a continuation can
	 * be. No side effects: this is a read, and refusing is guard()'s job.
	 *
	 * Page 1 ships with the document on every one of these lists, so a control only
	 * ever asks for 2 or higher, and anything lower is a request that did not come
	 * from one.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function requested(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public read-only endpoints; see Asset\AssetManager for why there is no nonce, and each controller's own guards for what does gate the request.
		$page = isset( $_POST['paged'] ) ? (int) $_POST['paged'] : 2;

		return max( 2, $page );
	}

	/**
	 * Refuse a page above the ceiling, before the offset query is issued for it.
	 *
	 * Refused rather than clamped: clamping would serve page 100,000 to a request
	 * for 10^19 and then compute `hasMore` against a number the caller never asked
	 * for. send_json_error() ends the request, so returning normally is the single
	 * "allowed" outcome.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page Requested page.
	 */
	public function guard( int $page ): void {
		if ( $page > $this->ceiling() ) {
			$this->wp->send_json_error( array( 'message' => 'out_of_range' ), 400 );
		}
	}

	/**
	 * The ceiling in force, after filtering.
	 *
	 * What a filter returns is re-validated rather than trusted, in both directions,
	 * because both ends break something. Below 2 there is no page a continuation
	 * could ever answer, so every load-more on the site would stop working. At
	 * `PHP_INT_MAX` the `$page + 1` an endpoint returns as `nextPage` overflows to a
	 * float — the exact defect this class exists to close, handed back through the
	 * escape hatch. Anything non-numeric is not an answer to the question, so the
	 * default stands.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	private function ceiling(): int {
		$filtered = $this->wp->apply_filters( self::MAX_PAGE_FILTER, self::MAX_PAGE );
		$ceiling  = is_numeric( $filtered ) ? (int) $filtered : self::MAX_PAGE;

		return min( PHP_INT_MAX - 1, max( 2, $ceiling ) );
	}
}
