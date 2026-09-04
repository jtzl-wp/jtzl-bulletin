<?php
/**
 * The arguments every REST collection is queried with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\ForumQuery;
use JTZL\Bulletin\Query\ReplyQuery;
use JTZL\Bulletin\Query\TopicQuery;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Turns a page request into a query, once, for every collection the API serves.
 */
class CollectionRepository {

	private RestContextInterface $rest;

	private CollectionVisibility $visibility;

	private ContextInterface $wp;

	private ForumQuery $forums;

	private TopicQuery $topics;

	private ReplyQuery $replies;

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		CollectionVisibility $visibility,
		ForumQuery $forums,
		TopicQuery $topics,
		ReplyQuery $replies
	) {
		$this->wp         = $wp;
		$this->rest       = $rest;
		$this->visibility = $visibility;
		$this->forums     = $forums;
		$this->topics     = $topics;
		$this->replies    = $replies;
	}

	/**
	 * Data contract.
	 *
	 * @param int $parent_id Parent forum, or 0 for the top-level index.
	 * @param int $page      1-based page number.
	 * @param int $per_page  Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function forums( int $parent_id, int $page, int $per_page ): array {
		$args                   = $this->forums->args( $parent_id, $page );
		$args['posts_per_page'] = $per_page;

		return $this->rest->query( $this->visibility->scope( $args ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int $forum_id Forum whose topics are wanted.
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function forum_topics( int $forum_id, int $page, int $per_page ): array {
		$pinned   = $this->pinned_topic_ids( $forum_id );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;
		$page_ids = array_slice( $pinned, $offset, $per_page );
		$needed   = $per_page - count( $page_ids );

		$ordinary                   = $this->topics->args( $forum_id, 1 );
		$ordinary['offset']         = max( 0, $offset - count( $pinned ) );
		$ordinary['posts_per_page'] = max( 1, $needed );

		$rest_of_page = $this->rest->query( $this->visibility->scope( $ordinary ) );
		$total        = count( $pinned ) + $rest_of_page['total'];

		return array(
			'ids'         => $needed > 0 ? array_merge( $page_ids, $rest_of_page['ids'] ) : $page_ids,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		);
	}

	/**
	 * Data contract.
	 *
	 * @param int $topic_id Thread whose replies are wanted.
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function topic_replies( int $topic_id, int $page, int $per_page ): array {
		$result = $this->rest->query(
			$this->visibility->scope( $this->replies->args( $topic_id, $page, $per_page ) )
		);

		if ( ! $this->wp->is_thread_replies_active() ) {
			return $result;
		}

		$total = count( $this->replies->ordered_ids( $topic_id ) );

		return array(
			'ids'         => $result['ids'],
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		);
	}

	/**
	 * Data contract.
	 *
	 * @param int $term_id  Tag to filter by.
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function tag_topics( int $term_id, int $page, int $per_page ): array {
		$args                   = $this->topics->tagged_args( $this->rest->topic_tag_taxonomy(), $term_id, $page );
		$args['posts_per_page'] = $per_page;

		return $this->rest->query( $this->visibility->scope( $args ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int $page     1-based page number.
	 * @param int $per_page Terms per page, already bounded.
	 * @return array{terms:\WP_Term[],counts:array<int,int>,total:int,total_pages:int}
	 */
	public function tags( int $page, int $per_page ): array {
		$vocabulary = $this->rest->visible_tags(
			array( 'post_status' => $this->wp->get_readable_topic_statuses() ),
			$page,
			$per_page
		);

		return $vocabulary + array( 'total_pages' => (int) ceil( $vocabulary['total'] / max( 1, $per_page ) ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int $forum_id Forum whose pinned topics are wanted.
	 * @return int[]
	 */
	private function pinned_topic_ids( int $forum_id ): array {
		$pinned = array_merge(
			$this->pinned_page( $this->topics->super_pinned_args() ),
			$this->pinned_page( $this->topics->forum_pinned_args( $forum_id ) )
		);

		return array_values( array_unique( $pinned ) );
	}

	/**
	 * Data contract.
	 *
	 * @param array<string,mixed> $args Pinned query arguments.
	 * @return int[]
	 */
	private function pinned_page( array $args ): array {
		if ( array() === (array) ( $args['post__in'] ?? array() ) ) {
			return array();
		}

		return $this->rest->query( $this->visibility->scope( $args ) )['ids'];
	}
}
