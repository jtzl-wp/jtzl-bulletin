<?php
/**
 * The three places bbPress renders a control, a number or a screen with no name.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Binds the filters that supply a name where bbPress supplies none.
 *
 * @since 0.5.0
 */
class Namings {

	private ContextInterface $wp;

	private RowActionLabels $row_actions;

	private DocumentTitle $document_title;

	private SubForumCountLabels $counts;

	public function __construct(
		ContextInterface $wp,
		RowActionLabels $row_actions,
		DocumentTitle $document_title,
		SubForumCountLabels $counts
	) {
		$this->wp             = $wp;
		$this->row_actions    = $row_actions;
		$this->document_title = $document_title;
		$this->counts         = $counts;
	}

	/**
	 * Bind every naming.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// Name bbPress's glyph-only row toggles before both initial and AJAX rendering.
		$this->wp->add_filter( 'bbp_before_get_user_subscribe_link_parse_args', array( $this->row_actions, 'filter_subscribe_args' ) );
		$this->wp->add_filter( 'bbp_before_get_user_favorites_link_parse_args', array( $this->row_actions, 'filter_favorite_args' ) );
		$this->wp->add_filter( 'bbp_before_paginate_links_parse_args', array( $this->row_actions, 'filter_pagination_args' ) );

		// bbPress filters legacy wp_title, not the document-title API.
		$this->wp->add_filter( 'document_title_parts', array( $this->document_title, 'filter_document_title_parts' ), 100 );

		// Label bbPress's otherwise bare child-forum counts after it resolves them.
		$this->wp->add_filter( 'bbp_after_list_forums_parse_args', array( $this->counts, 'filter_list_args' ) );
	}
}
