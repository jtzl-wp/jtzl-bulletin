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
 * Shared by continuation endpoints to keep their paging contract consistent.
 *
 * @since 0.3.0
 */
class RequestedPage {

	/**
	 * The highest page any continuation will answer for, before filtering.
	 *
	 * This permits 1.5 million replies at bbPress's default page size.
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
	 * Refuse rather than clamp so the response never describes a different page.
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
	 * Clamp filtered values to the valid continuation range. PHP_INT_MAX is excluded
	 * because endpoints add one when returning `nextPage`; non-numeric values fall
	 * back to the default.
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
