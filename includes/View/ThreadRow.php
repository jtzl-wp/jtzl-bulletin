<?php
/**
 * A single thread row in a forum's list.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

/**
 * Renders one thread row, used for both the pinned and the all-threads sections.
 * The row data is gathered by the forum screen template during the topics loop.
 *
 * @since 0.1.0
 */
class ThreadRow {

	/**
	 * Echo one thread row.
	 *
	 * Fields: permalink (topic URL), title, author (plain name), active (human
	 * "last active" time), and replies (count).
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	public function render( array $row ): void {
		$permalink = (string) ( $row['permalink'] ?? '' );
		$title     = (string) ( $row['title'] ?? '' );
		$author    = (string) ( $row['author'] ?? '' );
		$active    = (string) ( $row['active'] ?? '' );
		$replies   = (int) ( $row['replies'] ?? 0 );

		printf( '<a class="bltn-row" href="%s">', esc_url( $permalink ) );
		printf( '<h2 class="bltn-row__title">%s</h2>', esc_html( $title ) );
		echo '<p class="bltn-row__meta">';
		if ( '' !== $author ) {
			printf( '<b>%s</b> &middot; ', esc_html( $author ) );
		}
		if ( '' !== $active ) {
			printf( '%s &middot; ', esc_html( $active ) );
		}
		echo esc_html(
			sprintf(
				/* translators: %s: formatted reply count. */
				_n( '%s reply', '%s replies', $replies, 'jtzl-bulletin' ),
				number_format_i18n( $replies )
			)
		);
		echo '</p></a>';
	}
}
