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
	 * Fields: action (bbPress AJAX action), param (POST key for the subject),
	 * id (the subject — a topic, a forum, or a set of search terms), target (id of
	 * the element rows are appended to), next (the page the control will request),
	 * and label.
	 *
	 * The subject is a string, not an id. It was written for topic and forum IDs and
	 * they still arrive as integers, but the search list's subject is the terms
	 * themselves — and the script has always sent this value through verbatim, so
	 * widening it here is the whole change (issue #35).
	 *
	 * A control without a subject omits both of its attributes rather than naming an
	 * empty one: the subscribed-forums list is a list of one user's own subscriptions,
	 * and which user that is rides on the URL the control posts back to (see
	 * Ajax\LoadSubscribedForumsController). `data-id="0"` would have read as a subject
	 * — on the forums index it is one, the root list.
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
	}
}
