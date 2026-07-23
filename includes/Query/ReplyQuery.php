<?php
/**
 * Canonical reply-query arguments.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Builds the bbp_has_replies() args used by BOTH the initial reading-view render
 * and the load-more AJAX handler, so the two paths paginate identically.
 *
 * Two deliberate choices (see CLAUDE.md trap #4):
 *  - An explicit reply-only post type, rather than leaning on
 *    bbp_show_lead_topic() — that helper only excludes the topic when
 *    bbp_is_single_topic() is also true, which is false in an AJAX request, so
 *    relying on it pulls the topic into the replies loop off-page and shifts
 *    every page boundary by one.
 *  - A (date, ID) order. Ordering by date ALONE is unstable when replies share a
 *    timestamp (coarse-dated imports, two posts in the same second): MySQL
 *    returns tied rows in an undefined order, so LIMIT/OFFSET paging shuffles
 *    rows across page boundaries — duplicating some replies and dropping others.
 *    The ID tiebreak makes paging deterministic.
 *
 * @since 0.1.0
 */
class ReplyQuery {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Reply-query args for a topic and 1-based page.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic to load replies for.
	 * @param int $page     1-based page number.
	 * @return array<string,mixed>
	 */
	public function args( int $topic_id, int $page ): array {
		return array(
			'post_parent'    => $topic_id,
			'post_type'      => $this->wp->get_reply_post_type(),
			'posts_per_page' => $this->wp->get_replies_per_page(),
			'paged'          => max( 1, $page ),
			'orderby'        => array(
				'date' => 'ASC',
				'ID'   => 'ASC',
			),
		);
	}
}
