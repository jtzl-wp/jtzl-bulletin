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
	 * "last active" time), replies (count), closed (whether the thread takes
	 * no more replies), and unread (whether it has posts this member has not read —
	 * always false when logged out).
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	public function render( array $row ): void {
		printf( '<a class="bltn-row" href="%s">', esc_url( (string) ( $row['permalink'] ?? '' ) ) );
		$this->render_dot( (bool) ( $row['unread'] ?? false ) );
		printf( '<h2 class="bltn-row__title">%s</h2>', esc_html( (string) ( $row['title'] ?? '' ) ) );
		$this->render_meta( $row );
		echo '</a>';
	}

	/**
	 * Echo the unread dot, or nothing.
	 *
	 * On its own line above the title, at the row's trailing edge — which is where the
	 * approved prototype puts it, and not the same choice View\ForumRow makes. A forum
	 * row leads with a heading and can hold the dot at the end of that line; a thread
	 * row's title wraps to two or three lines, so a dot riding the end of the first one
	 * would sit at a different height on every row. Given its own line it shares the
	 * trailing edge with the forum rows' dots, so a column of them is scannable without
	 * reading any of it. The empty flex box is what puts it there: `.bltn-row__dot`
	 * carries `margin-inline-start: auto`, so with no title beside it the dot is pushed
	 * the full width of the row.
	 *
	 * @since 0.5.0
	 *
	 * @param bool $unread Whether the thread has posts this member has not read.
	 */
	private function render_dot( bool $unread ): void {
		if ( ! $unread ) {
			return;
		}

		printf(
			'<div class="bltn-row__top"><span class="bltn-row__dot"><span class="bltn-sr-only">%s</span></span></div>',
			esc_html__( 'Unread — new posts', 'jtzl-bulletin' )
		);
	}

	/**
	 * Echo the row's meta line: closed, author, freshness, reply count.
	 *
	 * Closed leads it rather than trailing it. Every row starts its meta at the
	 * same x, so a leading word is found by scanning a column without reading any
	 * of it; trailing, it would sit beside freshness, the one field it
	 * contradicts. The row is not dimmed — bbPress greys a closed row to #ccc,
	 * which fails contrast and reads as disabled, when a closed thread is often
	 * the most worth reading (issue #38).
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $row Row data.
	 */
	private function render_meta( array $row ): void {
		$author  = (string) ( $row['author'] ?? '' );
		$active  = (string) ( $row['active'] ?? '' );
		$replies = (int) ( $row['replies'] ?? 0 );

		echo '<p class="bltn-row__meta">';
		if ( (bool) ( $row['closed'] ?? false ) ) {
			printf(
				'<span class="bltn-closed">%s</span> &middot; ',
				esc_html__( 'Closed', 'jtzl-bulletin' )
			);
		}
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
		echo '</p>';
	}
}
