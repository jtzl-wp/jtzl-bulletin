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
 */
class CollectionVisibility {

	private const MARKER = 'bltn_rest_collection';

	private const REPLY_MARKER = 'bltn_rest_reply_parents';

	private ContextInterface $wp;

	private RestContextInterface $rest;

	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Visibility constraints run before the query and found_rows calculation; post-filtering would disclose hidden rows through totals.
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
	 * Data contract.
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

		return $where . $this->wp->reply_parent_where_clause(
			$this->wp->get_reply_post_type(),
			$this->wp->get_topic_post_type(),
			array_map( 'strval', $statuses )
		);
	}
}
