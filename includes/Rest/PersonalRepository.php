<?php
/**
 * The arguments the REST collections a member has built themselves are queried with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Query\SubscribedForumQuery;
use JTZL\Bulletin\Query\TopicQuery;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Favourites, subscribed threads, subscribed forums: the collections whose membership
 * comes from outside the query.
 */
class PersonalRepository {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private CollectionVisibility $visibility;

	private TopicQuery $topics;

	private SubscribedForumQuery $subscribed;

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		CollectionVisibility $visibility,
		TopicQuery $topics,
		SubscribedForumQuery $subscribed
	) {
		$this->wp         = $wp;
		$this->rest       = $rest;
		$this->visibility = $visibility;
		$this->topics     = $topics;
		$this->subscribed = $subscribed;
	}

	/**
	 * Data contract.
	 *
	 * @param int $user_id  Member whose favourites are wanted.
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function favorite_topics( int $user_id, int $page, int $per_page ): array {
		return $this->listed_topics( $this->rest->favorite_topic_ids( $user_id ), $page, $per_page );
	}

	/**
	 * Data contract.
	 *
	 * @param int $user_id  Member whose subscriptions are wanted.
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function subscribed_topics( int $user_id, int $page, int $per_page ): array {
		return $this->listed_topics( $this->rest->subscribed_topic_ids( $user_id ), $page, $per_page );
	}

	/**
	 * Data contract.
	 *
	 * @param int $user_id  Member whose subscriptions are wanted.
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function subscribed_forums( int $user_id, int $page, int $per_page ): array {
		$forum_ids = $this->wp->get_subscribed_forum_ids( $user_id );

		if ( array() === $forum_ids ) {
			return $this->empty_page();
		}

		$args                   = $this->subscribed->args( $page );
		$args['post__in']       = $forum_ids;
		$args['posts_per_page'] = $per_page;

		return $this->rest->query( $this->visibility->scope( $args ) );
	}

	/**
	 * Data contract.
	 *
	 * @param int[] $topic_ids The member's relationship, as IDs.
	 * @param int   $page      1-based page number.
	 * @param int   $per_page  Rows per page, already bounded.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	private function listed_topics( array $topic_ids, int $page, int $per_page ): array {
		if ( array() === $topic_ids ) {
			return $this->empty_page();
		}

		return $this->rest->query(
			$this->visibility->scope(
				array( 'post_status' => $this->wp->get_readable_topic_statuses() )
					+ $this->topics->listed_args( $topic_ids, $page, $per_page )
			)
		);
	}

	/**
	 * Data contract.
	 *
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	private function empty_page(): array {
		return array(
			'ids'         => array(),
			'total'       => 0,
			'total_pages' => 0,
		);
	}
}
