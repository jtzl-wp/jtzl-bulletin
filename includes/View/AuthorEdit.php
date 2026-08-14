<?php
/**
 * The author's own Edit control, in the byline of their own post.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * One quiet control in the byline of a post the reader wrote, while they may still
 * edit it.
 *
 * ## Why it is not in the moderation tray
 *
 * `View\ModerationActions` gates its whole mode on `moderate` *specifically* to
 * suppress this — its docblock said so, on the grounds that "this release has no
 * composer to edit in". P4 supplies one, and an author's edit window is a SoW bullet,
 * so the suppression has to end. It cannot end by moving Edit into the tray, because
 * the tray is a mode: a participant would either be handed a "Moderate" toggle for an
 * ordinary act, or be given a tray with no toggle, which breaks the mode's own rule
 * that nothing appears until it is turned on.
 *
 * **So the control needs no mode, and that is the whole argument.** A mode exists to
 * stop chrome accumulating. `_bbp_edit_lock` is five minutes by default, so this
 * control is transient by nature — it appears when you post and removes itself — and
 * a control that removes itself does not accumulate. The moderation mode is untouched
 * by this class: a moderator still gets one word, and a participant still gets no
 * toggle. What changes is that a participant now gets one control on one post, which
 * is not a mode. (§3 decision 10; debt #3.)
 *
 * ## The three gates, and why the first one is not merely de-duplication
 *
 * | Gate | Declines when |
 * |---|---|
 * | Not moderating this thread | the reader holds `moderate` — Edit is already in their tray |
 * | Publicly visible | the post is `pending`, `spam` or `trash` |
 * | bbPress says so | capability, edit window, or no edit URL |
 *
 * ⚠ **The moderator gate is load-bearing, not tidiness.** `bbp_get_topic_edit_link()`
 * and `bbp_get_reply_edit_link()` both bypass their *entire* check block — the edit
 * lock included — for anyone holding `edit_others_topics` / `edit_others_replies`. A
 * moderator asked directly therefore gets a byline control that never expires, which
 * is precisely the accumulating chrome the "it needs no mode" argument rests on not
 * happening. Declining to ask on their behalf is what keeps the argument true.
 *
 * ⚠ **The status gate has nothing to render against yet, and is still this PR's.**
 * bbPress's link functions test capability and lock and *not* status, and
 * `bbp_map_reply_meta_caps()` lets an author through `edit_reply` on their own
 * `pending` post. Nothing shows it today because a pending reply is invisible to
 * everyone — but PR 5 is the one that makes an author's own pending reply visible,
 * and it would arrive wearing an Edit link. Decision 10 states the exclusion as a
 * property of *this* control ("a post held for review offers no Edit, because there
 * is nothing to edit until it is public"), so it is enforced here rather than left
 * for a query change to remember. It is unit-tested now and visible later.
 *
 * The list is asked for rather than written out, because `closed` is a public topic
 * status: a thread a moderator closed inside the author's five minutes is still a
 * thread its author may fix a typo in.
 *
 * ## What it renders
 *
 * bbPress's own anchor — its URL, its nonce-free edit route, its translated "Edit" —
 * wrapped in a span of ours. The wrapper is what carries the styling, so a site that
 * filters `bbp_get_*_edit_link` into different markup gets a control that still looks
 * like ours rather than an unstyled blue link mid-byline; the same bargain
 * `ModerationActions` strikes with `.bltn-mod`.
 *
 * **No screen-reader suffix on the label, and that was considered.** The byline's
 * permalink one element to the left carries one, because "3 days ago" as a link name
 * says nothing at all. "Edit" is not in that position — it is meaningful on its own,
 * and WCAG 2.4.4 is satisfied by the byline it sits in. A suffix would only be worth
 * its weight if it *disambiguated*, and the honest version ("Edit your reply") still
 * collides with itself the moment a reader has two fresh replies in one thread. It
 * would add words without answering the question it was invoked for.
 *
 * ⚠ **Deliberately left silent:** the control gives no notice before it vanishes.
 * bbPress does not either. A countdown ("Edit · 4 min") is clutter and needs a timer;
 * silence is a small mystery. Revisit only if it is ever reported.
 *
 * @since 0.5.0
 */
class AuthorEdit {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * The moderation mode, asked only whether it applies.
	 *
	 * @var ModerationActions
	 */
	private ModerationActions $moderation;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface  $wp         WordPress/bbPress seam.
	 * @param ModerationActions $moderation Moderation actions renderer.
	 */
	public function __construct( ContextInterface $wp, ModerationActions $moderation ) {
		$this->wp         = $wp;
		$this->moderation = $moderation;
	}

	/**
	 * The opening post's control — the topic itself.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Topic being read.
	 */
	public function render_for_topic( int $topic_id ): void {
		if ( ! $this->offered( $topic_id, $topic_id, $this->wp->get_public_topic_statuses() ) ) {
			return;
		}

		$this->render( $this->wp->get_topic_edit_link( $topic_id ) );
	}

	/**
	 * One reply's control.
	 *
	 * The topic is passed as well as the reply because the moderation test is asked of
	 * the *thread*, exactly as `ModerationActions::available()` asks it — moderation is
	 * a property of the thread being read, and a per-reply answer would let the control
	 * appear under some of a moderator's own posts and not others.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Thread the reply belongs to.
	 * @param int $reply_id Reply being rendered.
	 */
	public function render_for_reply( int $topic_id, int $reply_id ): void {
		if ( ! $this->offered( $topic_id, $reply_id, $this->wp->get_public_reply_statuses() ) ) {
			return;
		}

		$this->render( $this->wp->get_reply_edit_link( $reply_id ) );
	}

	/**
	 * The two gates that are ours, both asked before bbPress is asked for a link.
	 *
	 * Ordered so the control is never even *requested* on a moderator's behalf: asking
	 * would return a link that outlives the edit window, and discarding it afterwards
	 * would leave the right behaviour resting on a discard rather than on a decision.
	 *
	 * @since 0.5.0
	 *
	 * @param int               $topic_id Thread being read, for the moderation test.
	 * @param int               $post_id  Topic or reply whose status is tested.
	 * @param array<int,string> $statuses Public statuses for that kind.
	 * @return bool
	 */
	private function offered( int $topic_id, int $post_id, array $statuses ): bool {
		if ( $this->moderation->available( $topic_id ) ) {
			return false;
		}

		return in_array( $this->wp->get_post_status( $post_id ), $statuses, true );
	}

	/**
	 * Wrap bbPress's answer, or drop it.
	 *
	 * @since 0.5.0
	 *
	 * @param string $markup bbPress's anchor, or '' when it declined.
	 */
	private function render( string $markup ): void {
		if ( '' === $markup ) {
			return;
		}

		printf(
			'<span class="bltn-byline__edit">%s</span>',
			$markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bbPress-generated anchor; esc_url on the href and esc_html__ on the label, both at source.
		);
	}
}
