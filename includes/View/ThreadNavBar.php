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
