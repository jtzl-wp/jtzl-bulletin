<?php
/**
 * A single search result row.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

/**
 * Renders one result, whatever kind of thing it is. bbPress uses three templates
 * here — loop-search-forum, loop-search-topic, loop-search-reply — and they differ
 * enough that a forum result, a thread and a reply read as three unrelated screens
 *
 * @since 0.3.0
 */
class SearchRow {

	private TermHighlighter $marks;

	public function __construct( TermHighlighter $marks ) {
		$this->marks = $marks;
	}

	/**
	 * Echo one result row.
	 *
	 * Fields: permalink (where the result opens), title (the headline — a forum or
	 * thread's name, or a reply's own words), kind (the translated noun for what
	 * this is), author (plain name, absent on a forum), date (the post's own date,
	 * which is the one the list is ordered by), sub (the supporting line, plain
	 * text, already decoded and password-checked by the seam), and terms (what the
	 * reader searched for, to mark).
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	public function render( array $row ): void {
		$title = (string) ( $row['title'] ?? '' );
		$sub   = (string) ( $row['sub'] ?? '' );
		$terms = (string) ( $row['terms'] ?? '' );

		printf( '<a class="bltn-row bltn-result" href="%s">', esc_url( (string) ( $row['permalink'] ?? '' ) ) );
		// Escaped by the highlighter, which has to do it itself — see its docblock.
		printf( '<h2 class="bltn-row__title">%s</h2>', $this->marks->highlight( $title, $terms ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->render_meta( $row );
		if ( '' !== $sub ) {
			printf( '<p class="bltn-result__sub">%s</p>', $this->marks->highlight( $sub, $terms ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</a>';
	}

	/**
	 * Echo the row's meta line: kind, then closed, then author, then date.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	private function render_meta( array $row ): void {
		$kind   = (string) ( $row['kind'] ?? '' );
		$author = (string) ( $row['author'] ?? '' );
		$date   = (string) ( $row['date'] ?? '' );

		echo '<p class="bltn-row__meta">';
		printf( '<span class="bltn-result__kind">%s</span>', esc_html( $kind ) );
		if ( (bool) ( $row['closed'] ?? false ) ) {
			printf(
				' &middot; <span class="bltn-closed">%s</span>',
				esc_html__( 'Closed', 'jtzl-bulletin' )
			);
		}
		if ( '' !== $author ) {
			printf( ' &middot; <b>%s</b>', esc_html( $author ) );
		}
		if ( '' !== $date ) {
			printf( ' &middot; %s', esc_html( $date ) );
		}
		echo '</p>';
	}
}
