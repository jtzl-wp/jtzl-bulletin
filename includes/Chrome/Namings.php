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
 * ⚠ **The category was found in the code rather than imposed on it.** Splitting the
 * chrome group was forced — nine constructor arguments trips `ExcessiveParameterList`
 * and a `Chrome` class cannot take the container instead (deptrac grants `Vendor_DI`
 * to `Root` alone) — but the line it was split on was already written down: three of
 * the eight filters described themselves with the verb *name*, in comments nobody
 * wrote with a refactor in mind. A `+`/`×` toggle that announces itself as "times",
 * a bare `(2, 0)` beside a child forum, four reskin routes sharing one `<title>`.
 * They are one idea, and the remaining five — which change or remove something
 * already rendered — are another. That is `Chrome\Furniture`.
 *
 * ⚠ **`SubForumCountLabels` moved here out of `Bootstrap::register_reskin()`**, where
 * it had been grouped by the tier it happens to fire on rather than by what it does.
 * It is a `Chrome` class binding a `Chrome` concern; nothing about it changed.
 *
 * @since 0.5.0
 */
class Namings {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * The glyph-only row toggles.
	 *
	 * @var RowActionLabels
	 */
	private RowActionLabels $row_actions;

	/**
	 * The document title on the reskin routes.
	 *
	 * @var DocumentTitle
	 */
	private DocumentTitle $document_title;

	/**
	 * The unlabelled count pair beside a child forum.
	 *
	 * @var SubForumCountLabels
	 */
	private SubForumCountLabels $counts;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface    $wp             WordPress/bbPress seam.
	 * @param RowActionLabels     $row_actions    Row-toggle labels.
	 * @param DocumentTitle       $document_title Document title parts.
	 * @param SubForumCountLabels $counts         Sub-forum count labels.
	 */
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
		// Give bbPress's glyph-only `+` / `×` row toggles a name. bbPress hardcodes
		// the glyphs in its own loop templates, so the only control on a Subscriptions
		// or Favourites row announced itself as "times" — while being the destructive
		// one. Filtered before the parse, so the name travels through bbPress's own
		// AJAX re-render too (see Chrome\RowActionLabels).
		$this->wp->add_filter( 'bbp_before_get_user_subscribe_link_parse_args', array( $this->row_actions, 'filter_subscribe_args' ) );
		$this->wp->add_filter( 'bbp_before_get_user_favorites_link_parse_args', array( $this->row_actions, 'filter_favorite_args' ) );
		$this->wp->add_filter( 'bbp_before_paginate_links_parse_args', array( $this->row_actions, 'filter_pagination_args' ) );

		// And name the screens WordPress could not: bbPress filters only the legacy
		// wp_title, which wp_get_document_title() never calls, so four reskin routes
		// shared one <title> (see Chrome\DocumentTitle).
		$this->wp->add_filter( 'document_title_parts', array( $this->document_title, 'filter_document_title_parts' ), 100 );

		// And name the two numbers bbPress prints beside each child forum, which it
		// renders as a bare `(2, 0)` — a pair no label explains and which cannot be
		// reconciled with the labelled `Topics`/`Posts` on the same forum's own row
		// (issue #74). After the parse, so the counts bbPress decided to show are the
		// ones named.
		$this->wp->add_filter( 'bbp_after_list_forums_parse_args', array( $this->counts, 'filter_list_args' ) );
	}
}
