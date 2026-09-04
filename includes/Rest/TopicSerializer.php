<?php
/**
 * Topics, as the app sees them.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\Unread\ReadCursor;
use JTZL\Bulletin\Unread\ReadState;
use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * A topic row, with everything a reading screen needs and nothing a list does not.
 */
class TopicSerializer {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private ReadState $reads;

	private ReadCursor $cursor;

	private AuthorSerializer $authors;

	private TagSerializer $tags;

	private AccessPolicy $access;

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		ReadState $reads,
		ReadCursor $cursor,
		AuthorSerializer $authors,
		TagSerializer $tags,
		AccessPolicy $access
	) {
		$this->wp      = $wp;
		$this->rest    = $rest;
		$this->reads   = $reads;
		$this->cursor  = $cursor;
		$this->authors = $authors;
		$this->tags    = $tags;
		$this->access  = $access;
	}

	/**
	 * Data contract.
	 *
	 * @param int[] $topic_ids       Topics, in the order they should appear.
	 * @param int   $avatar_size     Pixels.
	 * @param bool  $include_content Whether to render the opening post.
	 * @return array<int,array<string,mixed>>
	 */
	public function topics( array $topic_ids, int $avatar_size, bool $include_content = false ): array {
		$topic_ids = array_values( array_map( 'intval', $topic_ids ) );
		$state     = $this->state( $topic_ids, $avatar_size );

		$rows = array();
		foreach ( $topic_ids as $topic_id ) {
			$rows[] = $this->topic_data( $topic_id, $state, $include_content );
		}

		return $rows;
	}

	/**
	 * Data contract.
	 *
	 * @param int  $topic_id        Topic ID.
	 * @param int  $avatar_size     Pixels.
	 * @param bool $include_content Whether to render the opening post.
	 * @return array<string,mixed>
	 */
	public function topic( int $topic_id, int $avatar_size, bool $include_content = true ): array {
		$rows = $this->topics( array( $topic_id ), $avatar_size, $include_content );

		return $rows[0];
	}

	/**
	 * Data contract.
	 *
	 * @param int[] $topic_ids   Topics.
	 * @param int   $avatar_size Pixels.
	 * @return array<string,mixed>
	 */
	private function state( array $topic_ids, int $avatar_size ): array {
		$user_id  = $this->wp->get_current_user_id();
		$term_ids = $this->tags->term_ids( $topic_ids );

		return array(
			'authors'    => $this->authors->authors( $topic_ids, $avatar_size ),
			'tag_counts' => $this->rest->visible_tag_counts( $term_ids, array( 'post_status' => $this->wp->get_readable_topic_statuses() ) ),
			'unread'     => $user_id > 0
				? $this->reads->unread_topics( $topic_ids, $user_id )
				: array_fill_keys( $topic_ids, null ),
			'favorite'   => $this->flags( $topic_ids, $user_id > 0 ? $this->rest->favorite_topic_ids( $user_id ) : null ),
			'subscribed' => $this->flags( $topic_ids, $user_id > 0 ? $this->rest->subscribed_topic_ids( $user_id ) : null ),
		);
	}

	/**
	 * Data contract.
	 *
	 * @param int                 $topic_id        Topic ID.
	 * @param array<string,mixed> $state           Primed page state.
	 * @param bool                $include_content Whether to render the opening post.
	 * @return array<string,mixed>
	 */
	private function topic_data( int $topic_id, array $state, bool $include_content ): array {
		$data = array(
			'id'            => $topic_id,
			'forum_id'      => $this->wp->get_topic_forum_id( $topic_id ),
			'title'         => $this->wp->get_topic_title( $topic_id ),
			'author'        => $state['authors'][ $topic_id ] ?? null,
			'created'       => $this->rest->post_date_rfc3339( $topic_id ),
			'last_active'   => $this->rest->last_active_rfc3339( $topic_id ),

			'reply_count'   => $this->wp->get_topic_reply_count( $topic_id ),
			'voice_count'   => $this->rest->topic_voice_count( $topic_id ),
			'is_sticky'     => $this->rest->is_topic_sticky( $topic_id ),
			'is_closed'     => $this->wp->is_topic_closed( $topic_id ),
			'status'        => $this->rest->normalize_status( $this->wp->get_post_status( $topic_id ) ),
			'tags'          => $this->tags->topic_tags( $topic_id, $state['tag_counts'] ),
			'link'          => $this->wp->get_topic_permalink( $topic_id ),
			'can_edit'      => $this->access->can_edit_topic( $topic_id ),
			'read_cursor'   => $this->cursor->issue(
				$topic_id,
				$this->wp->get_topic_last_active_datetime( $topic_id ),
				$this->wp->get_topic_last_active_id( $topic_id )
			),
			'is_unread'     => $state['unread'][ $topic_id ] ?? null,
			'is_subscribed' => $state['subscribed'][ $topic_id ] ?? null,
			'is_favorite'   => $state['favorite'][ $topic_id ] ?? null,
		);

		if ( $include_content ) {
			$data['content'] = $this->rest->rendered_topic_content( $topic_id );
		}

		return $data;
	}

	/**
	 * Data contract.
	 *
	 * @param int[]      $topic_ids Topics.
	 * @param int[]|null $members   The reader's list, or null when logged out.
	 * @return array<int,bool|null>
	 */
	private function flags( array $topic_ids, ?array $members ): array {
		if ( null === $members ) {
			return array_fill_keys( $topic_ids, null );
		}

		$lookup = array_flip( $members );
		$flags  = array();

		foreach ( $topic_ids as $topic_id ) {
			$flags[ $topic_id ] = isset( $lookup[ $topic_id ] );
		}

		return $flags;
	}
}
