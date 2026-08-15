<?php
/**
 * The fixed bottom bar with forum-scoped thread Prev/Next.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\Support\Icons;

/**
 * Renders the bottom nav bar from a ThreadNavigator locate() model. The bar is
 * purely about whole threads; moving between posts is the reader's own scroll,
 * and moving between reply pages is the inline "load more" control — so the bar
 * stays free of the clutter Bulletin exists to remove.
 *
 * ## The count is withheld on a pinned thread
 *
 * Settled by Yoren 2026-08-14, carried open since P1. The forum screen groups
 * pinned threads first and then orders the rest by freshness; this bar orders by
 * freshness alone, because that is what the brief asks a reader to move through.
 * The two agree everywhere except on a pinned thread, where the reader has just
 * tapped the first row in a list and the bar answers "Thread 3 of 4" — a sentence
 * that is true about the bar's own order and false about the one they can see.
 *
 * ⚠ **The stepping is deliberately left alone**, on both kinds of thread. Prev and
 * Next are the controls JT proposed and the ones a reader uses; only the count is
 * dropped, and only where it would contradict the screen behind it. Reordering the
 * bar to follow the list was the more honest option and was declined on cost and on
 * super stickies — one appears atop *every* forum's list while belonging to a single
 * forum, so there is no single list for the bar to follow. Dropping the count on
 * every thread was declined as paying for the many to fix the few.
 *
 * So the pinned bar renders Prev, an **empty** slot, and Next. The element is still
 * emitted rather than skipped: the bar is a three-column grid whose outer tracks are
 * equal `1fr`, so dropping the span would move Next into the middle column instead of
 * simply emptying it. Emptied, the track collapses and the two buttons stay edge-
 * aligned exactly where they sit on every other thread — nothing shifts under a
 * reader's thumb between one thread and the next.
 *
 * @since 0.1.0
 */
class ThreadNavBar {

	/**
	 * Echo the nav bar.
	 *
	 * @since 0.1.0
	 *
	 * @param array{prev_url:string,next_url:string,position:int,total:int,pinned?:bool} $model Navigator model.
	 */
	public function render( array $model ): void {
		$prev_url = (string) $model['prev_url'];
		$next_url = (string) $model['next_url'];
		$position = (int) $model['position'];
		$total    = (int) $model['total'];
		$pinned   = ! empty( $model['pinned'] );

		$count = $position > 0 && ! $pinned
			/* translators: 1: current thread number, 2: total threads in the forum. */
			? sprintf( __( 'Thread %1$d of %2$d', 'jtzl-bulletin' ), $position, $total )
			: '';

		printf(
			'<nav class="bltn-navbar" aria-label="%s">',
			esc_attr__( 'Threads in this forum', 'jtzl-bulletin' )
		);

		if ( '' !== $prev_url ) {
			printf(
				'<a class="bltn-navbtn bltn-navbtn--prev" href="%s" rel="prev">%s%s</a>',
				esc_url( $prev_url ),
				Icons::nav_prev(), // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
				esc_html__( 'Prev', 'jtzl-bulletin' )
			);
		} else {
			printf(
				'<span class="bltn-navbtn bltn-navbtn--prev" aria-disabled="true">%s%s</span>',
				Icons::nav_prev(), // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
				esc_html__( 'Prev', 'jtzl-bulletin' )
			);
		}

		printf( '<span class="bltn-navbar__count">%s</span>', esc_html( $count ) );

		if ( '' !== $next_url ) {
			printf(
				'<a class="bltn-navbtn bltn-navbtn--next" href="%s" rel="next">%s%s</a>',
				esc_url( $next_url ),
				esc_html__( 'Next', 'jtzl-bulletin' ),
				Icons::nav_next() // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
			);
		} else {
			printf(
				'<span class="bltn-navbtn bltn-navbtn--next" aria-disabled="true">%s%s</span>',
				esc_html__( 'Next', 'jtzl-bulletin' ),
				Icons::nav_next() // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
			);
		}

		echo '</nav>';
	}
}
