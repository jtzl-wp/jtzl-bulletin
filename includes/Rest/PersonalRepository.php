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
 *
 * ## What makes these a third repository rather than more methods on the other two
 *
 * Every other collection the API serves is *described* by its query — the topics in
 * this forum, the replies in this thread, the posts matching these words. These three
 * are not. Their membership is a list of IDs bbPress hands over from an engagement
 * store, and the query's only job is to order that list, page it, and take out
 * whatever this reader may no longer see. Rest\CollectionRepository asks a place a
 * question; Rest\UnanchoredRepository asks the whole site one; this class is handed
 * the answer and asks only whether it still holds.
 *
 * That difference is not filing. It is where the sharp edge is:
 *
 * ⚠ **An empty relationship must never reach `WP_Query`.** `post__in => array()` is
 * *ignored* rather than matched against, so a member with no favourites would be
 * served every topic on the site as their favourites — under an `X-WP-Total` that
 * agreed with the rows, which is exactly what would make it look deliberate. Every
 * method here short-circuits before querying, and they are together so that the guard
 * cannot be present on two of the three.
 *
 * ## The relationship is read through bbPress, never off the meta
 *
 * `_bbp_favorite` and `_bbp_subscription` are one of three storage shapes bbPress may
 * be using — it dispatches to a meta, taxonomy or user-option engagement strategy —
 * so the IDs come from the seams that ask bbPress, and both of those already existed:
 * favourites and subscribed topics from WordPress\RestContextInterface, subscribed
 * forums from WordPress\ContextInterface, where the website's own subscribed-forums
 * screen has read them since 0.3.0.
 *
 * ## Scoped like everything else
 *
 * Rest\CollectionVisibility::scope() is armed on all three, before `found_posts`. A
 * favourite is a bookmark, not a key: a thread favourited last year in a forum that
 * has since been made private drops out of the list and out of the total, rather than
 * being listed with a title the reader may no longer have.
 *
 * @since 0.6.0
 */
class PersonalRepository {

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
	 * Shared topic-query builder.
	 *
	 * @var TopicQuery
	 * @since 0.6.0
	 */
	private TopicQuery $topics;

	/**
	 * Shared subscribed-forum query builder.
	 *
	 * @var SubscribedForumQuery
	 * @since 0.6.0
	 */
	private SubscribedForumQuery $subscribed;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp         WordPress/bbPress seam.
	 * @param RestContextInterface $rest       REST seam.
	 * @param CollectionVisibility $visibility Collection scope.
	 * @param TopicQuery           $topics     Shared topic-query builder.
	 * @param SubscribedForumQuery $subscribed Shared subscribed-forum query builder.
	 */
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
	 * One page of the threads a member has favourited.
	 *
	 * @since 0.6.0
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
	 * One page of the threads a member subscribes to.
	 *
	 * @since 0.6.0
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
	 * One page of the forums a member subscribes to.
	 *
	 * ⚠ **Query\SubscribedForumQuery, not Query\ForumQuery**, and the difference is
	 * `post_parent`. A subscribed forum sits at whatever depth its owner put it, so
	 * scoping to a parent would hide every sub-forum a member had subscribed to; that
	 * class already spells the same thing bbPress does, and already carries the
	 * found-row counting and the total `(menu_order, title, ID)` order that issue #50
	 * needed — an order this list has to share with the website's own subscribed-forums
	 * screen or the two disagree about which forum sits on which page.
	 *
	 * ⚠ **The relationship comes from WordPress\ContextInterface**, which has answered
	 * it since 0.3.0 for that same screen. bbPress dispatches subscription storage to a
	 * meta, taxonomy or user-option strategy, so this is a question with one right
	 * place to ask it and no reason for the REST seam to ask it a second way.
	 *
	 * @since 0.6.0
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
	 * One page of a named set of topics, scoped to what this reader may open.
	 *
	 * ⚠ **The empty short-circuit is the whole safety of this method.** WP_Query
	 * ignores an empty `post__in` rather than matching nothing, so a member with no
	 * favourites would otherwise be served every topic on the site as their favourites
	 * — under an `X-WP-Total` that agreed with the rows, which is what would make it
	 * look deliberate. Both callers come through here so the guard cannot be present
	 * on one list and absent from the other.
	 *
	 * @since 0.6.0
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
	 * A collection with nothing in it, in the shape every collection comes back in.
	 *
	 * @since 0.6.0
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
