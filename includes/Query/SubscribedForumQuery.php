<?php
/**
 * Paging for the Subscribed Forums list on a member's profile.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Owns paging for bbPress's subscribed-forums profile query.
 *
 * Preserve bbPress's authorization and subscription filtering while applying the
 * requested page and stable ordering.
 */
class SubscribedForumQuery {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Args for one page of the subscribed-forums list.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page 1-based page number.
	 * @return array<string,mixed>
	 */
	public function args( int $page ): array {
		return $this->pageable(
			array(
				'post_type'      => $this->wp->get_forum_post_type(),
				'post_parent'    => 'any',
				'posts_per_page' => $this->wp->get_forums_per_page(),
				'paged'          => max( 1, $page ),
			)
		);
	}

	/**
	 * Make bbPress's own subscribed-forums query pageable, on the profile tab only.
	 *
	 * Hooked on `bbp_after_has_forums_parse_args`, which hands over the merged
	 * arguments in the moment before bbPress runs the query. Nothing else is touched:
	 * the page the reader is served is still page 1, which is the contract every
	 * load-more list here keeps — the document ships page 1 and the control grows it.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $args Parsed query arguments.
	 * @return mixed
	 */
	public function filter_forum_args( $args ) {
		if ( ! is_array( $args ) || ! $this->wp->is_subscriptions() ) {
			return $args;
		}

		return $this->pageable( $args );
	}

	/**
	 * Count the rows, and settle the order they come back in.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<string,mixed>
	 */
	private function pageable( array $args ): array {
		$args['no_found_rows'] = false;
		$args['orderby']       = array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
			'ID'         => 'ASC',
		);

		return $args;
	}
}
