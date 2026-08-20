<?php
/**
 * The forum scope every REST collection is narrowed to.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Restricts a marked query to forums this reader may open, before it is counted.
 *
 * ## Why in the query and not after it
 *
 * A collection sends `X-WP-Total`, and the contract says that total counts what this
 * reader may actually receive. Filtering a returned page would leave the total
 * describing a different set from the rows — page 1 short, page 2 repeating, and a
 * count that tells a visitor how much they are not being shown. So the restriction
 * goes in before `WP_Query` computes `found_posts`, which means `posts_where`.
 *
 * ## Scoped by a marker, like everything else on this filter
 *
 * The marker rides the query's own variables, exactly as Query\SearchVisibility and
 * Query\PendingVisibility do: an unrecognised argument is carried into
 * `WP_Query::$query_vars` untouched, so the filter can ask the query it was handed
 * whether it is one of ours. Nothing else on the site arms it — not bbPress's own
 * loops on the reskin tier, not the website's screens, not another plugin's query.
 * An unmarked query returns byte-identical.
 *
 * ## Where it sits in the order, and why that is load-bearing
 *
 * `posts_where` carries four of Bulletin's filters and their order is a decision:
 *
 * | Priority | Filter | Direction |
 * |---|---|---|
 * | 10 | Query\ProtectedStatusGuard, Query\SearchVisibility | narrow |
 * | 20 | this | narrow |
 * | `PHP_INT_MAX` | Query\PendingVisibility | widen |
 *
 * Narrowing first and widening last is what keeps the widening meaningful: the pending
 * clause wraps whatever it is given and OR-s one fully-bound predicate — one author,
 * one thread, one status — beside it. Running this after that widening would subtract
 * from outside the wrap and remove the row again.
 *
 * @since 0.6.0
 */
class CollectionVisibility {

	/**
	 * The query variable the collection marker rides on.
	 *
	 * @var string
	 * @since 0.6.0
	 */
	private const MARKER = 'bltn_rest_collection';

	/**
	 * The query variable the reply-parent marker rides on.
	 *
	 * @var string
	 * @since 0.6.0
	 */
	private const REPLY_MARKER = 'bltn_rest_reply_parents';

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
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp   WordPress/bbPress seam.
	 * @param RestContextInterface $rest REST seam.
	 */
	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Arm a set of query arguments with this reader's forum scope.
	 *
	 * The scope is resolved here rather than inside the filter so the whole page —
	 * rows, total and page count — is answered against one snapshot of what this
	 * reader may open.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $query_args Arguments to arm.
	 * @return array<string,mixed>
	 */
	public function scope( array $query_args ): array {
		return array_merge(
			$query_args,
			array( self::MARKER => $this->rest->readable_forum_ids() )
		);
	}

	/**
	 * Arm a reply collection that has no single known parent topic.
	 *
	 * An unanchored reply list — a member's replies, a search result — can contain a
	 * reply whose own status is public while the thread it belongs to is private,
	 * hidden or protected. The forum scope above does not answer that: the thread's
	 * *status* is a separate question, and this is the marker that asks it.
	 *
	 * ⚠ Not for `/topics/{id}/replies`. That route has one known parent and
	 * Rest\AccessPolicy has already ruled on it — including the case where the reader
	 * is the author of a held topic, which this status list deliberately excludes.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $query_args             Arguments to arm.
	 * @param string[]            $readable_topic_statuses Statuses a parent may have.
	 * @return array<string,mixed>
	 */
	public function scope_replies( array $query_args, array $readable_topic_statuses ): array {
		return array_merge(
			$this->scope( $query_args ),
			array( self::REPLY_MARKER => array_values( $readable_topic_statuses ) )
		);
	}

	/**
	 * Narrow a marked query. Hooked on `posts_where` at priority 20.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $where The WHERE clause built so far.
	 * @param mixed $query The query it belongs to.
	 * @return mixed
	 */
	public function restrict( $where, $query = null ) {
		$scope = $this->wp->get_query_arg( $query, self::MARKER );

		if ( ! is_string( $where ) || ! is_array( $scope ) ) {
			return $where;
		}

		$where = $this->rest->collection_where_clause(
			$where,
			array_map( 'intval', $scope ),
			$this->wp->get_forum_post_type(),
			$this->wp->get_topic_post_type(),
			$this->wp->get_reply_post_type()
		);

		$statuses = $this->wp->get_query_arg( $query, self::REPLY_MARKER );

		if ( ! is_array( $statuses ) ) {
			return $where;
		}

		// The website's own clause, reused rather than reimplemented: it already
		// covers a reply whose real parent is unreadable, and preserves the imported
		// orphan rule — a reply with no parent at all keeps its own status's answer.
		return $where . $this->wp->reply_parent_where_clause(
			$this->wp->get_reply_post_type(),
			$this->wp->get_topic_post_type(),
			array_map( 'strval', $statuses )
		);
	}
}
