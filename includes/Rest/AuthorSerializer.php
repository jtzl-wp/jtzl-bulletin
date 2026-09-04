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
 */
class AuthorSerializer {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Data contract.
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
	 * Data contract.
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
