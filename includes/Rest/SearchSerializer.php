<?php
/**
 * Search results, as the app sees them.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * One list, three kinds of row, each carrying the word for what it is.
 */
class SearchSerializer {

	private ContextInterface $wp;

	private ForumSerializer $forums;

	private TopicSerializer $topics;

	private ReplySerializer $replies;

	public function __construct(
		ContextInterface $wp,
		ForumSerializer $forums,
		TopicSerializer $topics,
		ReplySerializer $replies
	) {
		$this->wp      = $wp;
		$this->forums  = $forums;
		$this->topics  = $topics;
		$this->replies = $replies;
	}

	/**
	 * Data contract.
	 *
	 * @param array<int,array{id:int,type:string}> $results     Results, in order, by post type.
	 * @param int                                  $avatar_size Pixels.
	 * @return array<int,array<string,mixed>>
	 */
	public function results( array $results, int $avatar_size ): array {
		$grouped = $this->group( $results );
		$rows    = array(
			'forum' => $this->index( $this->forums->forums( $grouped['forum'] ) ),
			'topic' => $this->index( $this->topics->topics( $grouped['topic'], $avatar_size ) ),
			'reply' => $this->index( $this->replies->replies( $grouped['reply'], $avatar_size ) ),
		);

		$ordered = array();

		foreach ( $results as $result ) {
			$kind = $this->kind( (string) ( $result['type'] ?? '' ) );
			$row  = $rows[ $kind ][ (int) ( $result['id'] ?? 0 ) ] ?? null;

			if ( null !== $row ) {
				$ordered[] = array( 'type' => $kind ) + $row;
			}
		}

		return $ordered;
	}

	/**
	 * Data contract.
	 *
	 * @param array<int,array{id:int,type:string}> $results Results, in order.
	 * @return array{forum:int[],topic:int[],reply:int[]}
	 */
	private function group( array $results ): array {
		$grouped = array(
			'forum' => array(),
			'topic' => array(),
			'reply' => array(),
		);

		foreach ( $results as $result ) {
			$kind = $this->kind( (string) ( $result['type'] ?? '' ) );

			if ( '' !== $kind ) {
				$grouped[ $kind ][] = (int) ( $result['id'] ?? 0 );
			}
		}

		return $grouped;
	}

	/**
	 * Data contract.
	 *
	 * @param array<int,array<string,mixed>> $rows Serialized rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function index( array $rows ): array {
		$indexed = array();

		foreach ( $rows as $row ) {
			$indexed[ (int) ( $row['id'] ?? 0 ) ] = $row;
		}

		return $indexed;
	}

	private function kind( string $post_type ): string {
		$vocabulary = array(
			$this->wp->get_forum_post_type() => 'forum',
			$this->wp->get_topic_post_type() => 'topic',
			$this->wp->get_reply_post_type() => 'reply',
		);

		return $vocabulary[ $post_type ] ?? '';
	}
}
