<?php
/**
 * The inline "load more" control.
 *
 * @package JTZL\Bulletin
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
 */
class LoadMore {

	/**
	 * Echo the load-more control.
	 *
	 * Fields: action (bbPress AJAX action), param (POST key for the subject id),
	 * id (the subject — a topic or a forum), target (id of the element rows are
	 * appended to), next (the page the control will request), and label.
	 *
	 * @param array<string,mixed> $control Control data.
	 */
	public function render( array $control ): void {
		$action = (string) ( $control['action'] ?? '' );
		$param  = (string) ( $control['param'] ?? '' );
		$id     = (int) ( $control['id'] ?? 0 );
		$target = (string) ( $control['target'] ?? '' );
		$next   = (int) ( $control['next'] ?? 2 );
		$label  = (string) ( $control['label'] ?? '' );

		printf(
			'<div class="bltn-loadmore" data-action="%s" data-param="%s" data-id="%s" data-target="%s" data-next="%s">',
			esc_attr( $action ),
			esc_attr( $param ),
			esc_attr( (string) $id ),
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
