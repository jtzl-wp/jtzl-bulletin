<?php
/**
 * A single forum row.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

/**
 * Renders one forum row, used by both the forums index and the sub-forum
 * section of a single forum. The row data is gathered by the screen templates
 * during their forums loop.
 *
 * @since 0.1.0
 */
class ForumRow {

	/**
	 * Echo one forum row.
	 *
	 * Fields: permalink (forum URL), title, description (already stripped and
	 * trimmed), topics (count, including sub-forum topics), author (plain name
	 * of the last active post's author), active (human "last active" time) and
	 * closed (whether the forum takes no new content).
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	public function render( array $row ): void {
		$title       = (string) ( $row['title'] ?? '' );
		$description = (string) ( $row['description'] ?? '' );

		printf( '<a class="bltn-row" href="%s">', esc_url( (string) ( $row['permalink'] ?? '' ) ) );

		echo '<div class="bltn-row__top">';
		printf( '<h2 class="bltn-forum__name">%s</h2>', esc_html( $title ) );
		// Unread dot is wired in P2; placeholder markup lives in the CSS namespace.
		echo '</div>';

		if ( '' !== $description ) {
			printf( '<p class="bltn-row__desc">%s</p>', esc_html( $description ) );
		}

		$this->render_meta( $row );
		echo '</a>';
	}

	/**
	 * Echo the row's meta line: closed, thread count, author, freshness.
	 *
	 * Closed leads it, for the reasons View\ThreadRow gives.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	private function render_meta( array $row ): void {
		$author = (string) ( $row['author'] ?? '' );
		$active = (string) ( $row['active'] ?? '' );
		$topics = (int) ( $row['topics'] ?? 0 );

		echo '<p class="bltn-row__meta">';
		if ( (bool) ( $row['closed'] ?? false ) ) {
			printf(
				'<span class="bltn-closed">%s</span> &middot; ',
				esc_html__( 'Closed', 'jtzl-bulletin' )
			);
		}
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
		echo '</p>';
	}
}
