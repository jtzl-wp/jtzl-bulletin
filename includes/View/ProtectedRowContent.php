<?php
/**
 * What a password-protected post contributes to a loop row.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

/**
 * Withholds WordPress's password form from the description slot of a bbPress loop
 * row (issue #54).
 *
 * Row templates print a post's content through `bbp_forum_content()` and
 * `bbp_reply_content()`, and WordPress substitutes its password *form* for the
 * content of a protected post. On the reskin tier — where bbPress's own templates
 * render and nothing of ours intercepts them — a protected forum's row therefore
 * reads "This content is password-protected. To view it, please enter the password
 * below. Password: [____] [Enter]" where the one line the row has to say what the
 * forum is about should be. A live input and submit button, in a list.
 *
 * This is the takeover tier's decision (View\ForumList::fields(), issue #49) carried
 * to the tier that renders bbPress's markup instead of ours: the description is
 * withheld outright. Counts and freshness are untouched, matching bbPress's own
 * loop — a password gates what a forum *holds*, not that it exists and is active.
 *
 * ## Why this hook
 *
 * Not `bbp_get_forum_content`, which the issue originally proposed: bbPress returns
 * the form **before** it reaches its own filter, so on the only case that matters the
 * filter never fires.
 *
 * ```php
 * function bbp_get_forum_content( $forum_id = 0 ) {
 *     $forum_id = bbp_get_forum_id( $forum_id );
 *     if ( post_password_required( $forum_id ) ) {
 *         return get_the_password_form();               // ← early return
 *     }
 *     $content = get_post_field( 'post_content', $forum_id );
 *     return apply_filters( 'bbp_get_forum_content', $content, $forum_id );
 * }
 * ```
 *
 * `bbp_get_topic_content()` and `bbp_get_reply_content()` have the identical shape.
 * So the interception has to happen where WordPress produces the form instead.
 *
 * Not a stylesheet rule either, though `.bbp-author-avatar { display: none }` is real
 * precedent on this tier: `display: none` still ships a live `<input type="password">`
 * and a submit button in every protected row, and a suppression that only exists in
 * CSS cannot be asserted against real bbPress in the integration suite. Here that
 * decides it.
 *
 * ## Why a bracketed slot, and not "a loop is running"
 *
 * `the_password_form` is a global filter, and a password form is exactly the right
 * thing in two places Bulletin renders on purpose:
 *
 *  - the in-shell password screen for a protected forum or topic (issue #18);
 *  - a protected **reply** inside the reading view, whose form is the reader's only
 *    way to unlock it — single-reply permalinks redirect into that same view, so
 *    there is no other route to the prompt.
 *
 * The second one rules out asking whether a bbPress loop is in progress: the reading
 * view runs `bbp_has_replies()` itself, so `reply_query->in_the_loop` is true while
 * View\ReplyView prints a reply's whole body. Suppressing there would leave a
 * protected reply blank and unlockable by nothing.
 *
 * So the guard is narrower than a loop and narrower than a tier: the four actions
 * bbPress fires immediately around the two slots in question — `loop-single-forum.php`
 * lines 38/42 and `loop-single-reply.php` lines 62/66 — arm this and disarm it again.
 * Nothing else in bbPress fires them, and none of our own views fire them at all
 * (View\ReplyView calls `the_reply_content()` directly), so the suppression cannot
 * reach content we render ourselves. That is a property of the wiring, not of a
 * condition that has to stay true as the screens change.
 *
 * It follows the rows wherever bbPress renders them, which is what the AJAX
 * continuation on the Subscriptions tab needs: appended rows go through the same
 * template, fire the same actions, and are withheld the same way as page 1.
 *
 * @since 0.3.0
 */
class ProtectedRowContent {

	/**
	 * Whether bbPress is between the actions that bracket a row's content slot.
	 *
	 * @var bool
	 */
	private bool $in_row_slot = false;

	/**
	 * Arm the suppression: a row's content slot is about to render.
	 *
	 * @since 0.3.0
	 */
	public function open_row_slot(): void {
		$this->in_row_slot = true;
	}

	/**
	 * Disarm it again the moment the slot closes.
	 *
	 * @since 0.3.0
	 */
	public function close_row_slot(): void {
		$this->in_row_slot = false;
	}

	/**
	 * Answer WordPress's password form with nothing when it is standing in for a
	 * loop row's description.
	 *
	 * @since 0.3.0
	 *
	 * @param string $form The password form markup WordPress generated.
	 * @return string
	 */
	public function filter_password_form( string $form ): string {
		return $this->in_row_slot ? '' : $form;
	}
}
