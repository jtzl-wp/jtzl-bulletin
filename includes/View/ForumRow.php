<?php
/**
 * A single forum row.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\View;

/**
 * Renders one forum row, used by both the forums index and the sub-forum
 * section of a single forum. The row data is gathered by the screen templates
 * during their forums loop.
 */
class ForumRow {

	/**
	 * Echo one forum row.
	 *
	 * Fields: permalink (forum URL), title, description (already stripped and
	 * trimmed), topics (count, including sub-forum topics), author (plain name
	 * of the last active post's author) and active (human "last active" time).
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	public function render( array $row ): void {
		$permalink   = (string) ( $row['permalink'] ?? '' );
		$title       = (string) ( $row['title'] ?? '' );
		$description = (string) ( $row['description'] ?? '' );
		$author      = (string) ( $row['author'] ?? '' );
		$active      = (string) ( $row['active'] ?? '' );
		$topics      = (int) ( $row['topics'] ?? 0 );

		printf( '<a class="bltn-row" href="%s">', esc_url( $permalink ) );

		echo '<div class="bltn-row__top">';
		printf( '<h2 class="bltn-forum__name">%s</h2>', esc_html( $title ) );
		// Unread dot is wired in P2; placeholder markup lives in the CSS namespace.
		echo '</div>';

		if ( '' !== $description ) {
			printf( '<p class="bltn-row__desc">%s</p>', esc_html( $description ) );
		}

		echo '<p class="bltn-row__meta">';
		echo esc_html(
			sprintf(
				/* translators: %s: formatted thread count. */
				_n( '%s thread', '%s threads', $topics, 'jtzl-bulletin' ),
				number_format_i18n( $topics )
			)
		);
		if ( '' !== $author ) {
			printf( ' &middot; <b>%s</b>', esc_html( $author ) );
		}
		if ( '' !== $active ) {
			printf( ' &middot; %s', esc_html( $active ) );
		}
		echo '</p></a>';
	}
}
