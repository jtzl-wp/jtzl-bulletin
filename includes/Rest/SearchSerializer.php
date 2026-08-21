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
 *
 * A search result is the ordinary entity — a forum row, a thread row, a reply row —
 * with `type` added. There is no fourth, flatter shape: the app already knows how to
 * draw all three, and a search-only representation would be a second contract to keep
 * in step with the first.
 *
 * ## Grouped, then put back
 *
 * The page is serialized one *kind* at a time and then reassembled into the order the
 * search returned it in. That is not tidiness. Every plural serializer here primes its
 * page in one pass — authors, unread state, favourites, subscriptions, tag counts —
 * and calling a singular serializer per row instead would turn a page of ten
 * interleaved results into ten of each of those lookups, on the one route where the
 * rows are guaranteed to be scattered across the whole site.
 *
 * ⚠ **`type` is the contract's word, not the site's post type.**
 * `bbp_get_forum_post_type()` and its siblings are filterable, and a site that
 * registers its forums under some other slug would otherwise have that slug published
 * to the app as though it were part of the API. The three literals below are the
 * vocabulary the app team was given.
 *
 * ⚠ **A row of a fourth kind is dropped rather than guessed at.**
 * `bbp_get_post_types()` is a filter too, so a plugin can put a type into the search
 * query that this API has no representation for — and rendering it as one of the three
 * we do know would put a wrong noun and somebody else's data in front of the reader.
 * View\SearchList makes the same call on the website. The total still counts it, which
 * is bbPress's answer about its own query rather than ours to overrule.
 *
 * @since 0.6.0
 */
class SearchSerializer {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * Forum rows.
	 *
	 * @var ForumSerializer
	 * @since 0.6.0
	 */
	private ForumSerializer $forums;

	/**
	 * Topic rows.
	 *
	 * @var TopicSerializer
	 * @since 0.6.0
	 */
	private TopicSerializer $topics;

	/**
	 * Reply rows.
	 *
	 * @var ReplySerializer
	 * @since 0.6.0
	 */
	private ReplySerializer $replies;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface $wp      WordPress/bbPress seam.
	 * @param ForumSerializer  $forums  Forum rows.
	 * @param TopicSerializer  $topics  Topic rows.
	 * @param ReplySerializer  $replies Reply rows.
	 */
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
	 * A page of results, interleaved as the search returned them.
	 *
	 * @since 0.6.0
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
	 * The page's IDs, split by the kind of thing they name.
	 *
	 * @since 0.6.0
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
	 * A serialized page keyed by the ID of the row it describes.
	 *
	 * @since 0.6.0
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

	/**
	 * The app's word for one of bbPress's post types, or '' for anything else.
	 *
	 * @since 0.6.0
	 *
	 * @param string $post_type Post type as the search returned it.
	 * @return string
	 */
	private function kind( string $post_type ): string {
		$vocabulary = array(
			$this->wp->get_forum_post_type() => 'forum',
			$this->wp->get_topic_post_type() => 'topic',
			$this->wp->get_reply_post_type() => 'reply',
		);

		return $vocabulary[ $post_type ] ?? '';
	}
}
