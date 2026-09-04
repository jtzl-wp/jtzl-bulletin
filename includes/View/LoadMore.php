<?php
/**
 * The inline "load more" control.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

/**
 * One control, two lists: more replies inside a thread, more threads inside a
 * forum. It stays inline in the content flow on purpose — the fixed bottom bar
 * is thread-to-thread Prev/Next, and a second "next" in the chrome meaning
 * something else is the clutter this plugin exists to remove.
 *
 * Everything the script needs rides on the element: which endpoint to call, what
 * to call it about, and where to put the result. That keeps the script free of
 * per-screen knowledge, and lets a screen render the control without the script
 * having to be taught about it.
 *
 * @since 0.1.0
 */
class LoadMore {

	/**
	 * Echo the load-more control.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $control Control data.
	 */
	public function render( array $control ): void {
		$action = (string) ( $control['action'] ?? '' );
		$param  = (string) ( $control['param'] ?? '' );
		$id     = (string) ( $control['id'] ?? '' );
		$target = (string) ( $control['target'] ?? '' );
		$next   = (int) ( $control['next'] ?? 2 );
		$label  = (string) ( $control['label'] ?? '' );

		$subject = '';
		if ( '' !== $param ) {
			$subject = sprintf(
				' data-param="%s" data-id="%s"',
				esc_attr( $param ),
				esc_attr( $id )
			);
		}

		printf(
			'<div class="bltn-loadmore" data-action="%s"%s data-target="%s" data-next="%s">',
			esc_attr( $action ),
			$subject, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled above from escaped parts.
			esc_attr( $target ),
			esc_attr( (string) $next )
		);
		printf(
			'<button type="button" class="bltn-loadmore__btn">%s</button>',
			esc_html( $label )
		);
		echo '</div>';

		/*
		 * The arrival of new rows, for a reader who cannot see them arrive. Empty
		 * until the script writes a count into it; `role="status"` is polite, so it
		 * never interrupts what is being read.
		 *
		 * A SIBLING of the control, not a child, and that is load-bearing: on its
		 * last page the control removes itself from the document (reading.ts), which
		 * would take a nested live region with it — and the last page is exactly the
		 * one whose arrival still needs announcing. Left behind it costs nothing: it
		 * is visually hidden and empty.
		 */
		echo '<p class="bltn-sr-only" role="status" data-bltn-loadmore-status></p>';
	}
}
