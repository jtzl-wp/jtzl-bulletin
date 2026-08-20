<?php
/**
 * Whoever wrote a post, as the app sees them.
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
 * The three fields every post carries about its author: `id`, `name`, `avatar`.
 *
 * ⚠ **An anonymous post has no `id`, and never carries its email.** bbPress keeps a
 * guest's address in post meta, and WordPress resolves an avatar from it — so the
 * address goes into the avatar resolver and stops there. `id` is null rather than 0,
 * because 0 is a user ID a client might go and look up.
 *
 * @since 0.6.0
 */
class AuthorSerializer {

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
	 * One post's author.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id     Post ID.
	 * @param int $avatar_size Pixels.
	 * @return array{id:int|null,name:string,avatar:string}
	 */
	public function author( int $post_id, int $avatar_size ): array {
		$author_id = $this->wp->get_post_author( $post_id );

		return array(
			'id'     => $author_id > 0 ? $author_id : null,
			'name'   => $this->wp->get_author_name( $post_id ),
			'avatar' => $this->rest->avatar_url( $post_id, $avatar_size ),
		);
	}

	/**
	 * Authors for a whole page of posts, keyed by post ID.
	 *
	 * @since 0.6.0
	 *
	 * @param int[] $post_ids    Posts.
	 * @param int   $avatar_size Pixels.
	 * @return array<int,array{id:int|null,name:string,avatar:string}>
	 */
	public function authors( array $post_ids, int $avatar_size ): array {
		$authors = array();

		foreach ( $post_ids as $post_id ) {
			$authors[ (int) $post_id ] = $this->author( (int) $post_id, $avatar_size );
		}

		return $authors;
	}
}
