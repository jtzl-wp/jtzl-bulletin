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
 * spliced together. One row, filled differently, is what makes a result list
 * scannable: the reader's eye learns the shape once.
 *
 * ## The kind is part of the meta line, not a label above the title
 *
 * A result list is the one place two rows can carry the *same* title honestly: a
 * thread matches, and so do three replies inside it, and all four are titled with
 * that thread. Unlabelled they read as a duplication bug. So a kind is not
 * decoration here — it is the only thing telling those rows apart.
 *
 * It goes where View\ThreadRow already puts a qualifier, at the head of the meta
 * line, for the reason written there: every row starts its meta at the same x, so
 * a leading word is found by scanning a column without reading any of it. That
 * costs no extra line and no new idiom, where a label above the title would have
 * cost both.
 *
 * ## Three lines, and the third one earns its place differently each time
 *
 * A thread list is browsed, and a title plus freshness is the whole decision — so
 * it has no third line. A result list is triaged against terms the reader just
 * typed, and every kind of result has one more thing worth knowing before the tap:
 * a thread and a forum have words the reader has not seen, and a reply has a thread
 * it belongs to (View\SearchList picks which). One class holds it, because it is one
 * position in one shape.
 *
 * It sits below the meta rather than above it, unlike View\ForumRow's description.
 * The kind qualifies the headline and belongs against it; the third line is what
 * remains once both have been read.
 *
 * @since 0.3.0
 */
class SearchRow {

	/**
	 * Echo one result row.
	 *
	 * Fields: permalink (where the result opens), title (the headline — a forum or
	 * thread's name, or a reply's own words), kind (the translated noun for what
	 * this is), author (plain name, absent on a forum), date (the post's own date,
	 * which is the one the list is ordered by), and sub (the supporting line, plain
	 * text, already decoded and password-checked by the seam).
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	public function render( array $row ): void {
		$title = (string) ( $row['title'] ?? '' );
		$sub   = (string) ( $row['sub'] ?? '' );

		printf( '<a class="bltn-row bltn-result" href="%s">', esc_url( (string) ( $row['permalink'] ?? '' ) ) );
		printf( '<h2 class="bltn-row__title">%s</h2>', esc_html( $title ) );
		$this->render_meta( $row );
		if ( '' !== $sub ) {
			printf( '<p class="bltn-result__sub">%s</p>', esc_html( $sub ) );
		}
		echo '</a>';
	}

	/**
	 * Echo the row's meta line: kind, then author, then date.
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
		if ( '' !== $author ) {
			printf( ' &middot; <b>%s</b>', esc_html( $author ) );
		}
		if ( '' !== $date ) {
			printf( ' &middot; %s', esc_html( $date ) );
		}
		echo '</p>';
	}
}
