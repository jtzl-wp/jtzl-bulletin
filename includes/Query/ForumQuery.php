<?php
/**
 * Canonical forum-query arguments.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Builds the bbp_has_forums() args used by BOTH takeover forum screens and the
 * load-forums AJAX handler — the role Query\TopicQuery plays for the thread list.
 *
 * @since 0.3.0
 */
class ForumQuery {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Args for one page of a forum list.
	 *
	 * @since 0.3.0
	 *
	 * @param int $parent_id Parent forum, or 0 for the root list the index shows.
	 * @param int $page      1-based page number.
	 * @return array<string,mixed>
	 */
	public function args( int $parent_id, int $page ): array {
		return array(
			'post_type'      => $this->wp->get_forum_post_type(),
			'post_parent'    => max( 0, $parent_id ),
			'posts_per_page' => $this->wp->get_forums_per_page(),
			'paged'          => max( 1, $page ),
			'no_found_rows'  => false,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
				'ID'         => 'ASC',
			),
		);
	}
}
