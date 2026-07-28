<?php
/**
 * Moderation actions on the reading view.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders bbPress's own moderation links for a topic or a reply, inside a wrapper the
 * reading view can hide as one.
 *
 * Moderation on this screen is a **mode, not furniture** (issue #36). bbPress puts a
 * seven-item control row under the opening post and a five-item row under every reply;
 * on a 23-reply thread that is 24 control rows a moderator did not ask for, on the one
 * screen whose entire job is the post. So the default state of the reading view adds
 * exactly one word for a moderator — "Moderate", beside Subscribe in the thread header
 * — and the rows appear only when it is tapped.
 *
 * The asymmetry is the design: one thread, N replies. Thread actions are few and are
 * about the thing being read; reply actions multiply by every "+1" in the thread. This
 * is the only arrangement where the cost of moderation chrome does not scale with
 * thread length.
 *
 * Two properties worth not "fixing" later:
 *
 * - **The rows are rendered server-side and hidden in CSS, not injected by JS.** A
 *   thread past its first page appends replies through the load-more endpoint, and
 *   those replies arrive already carrying their row: the mode is a class on the
 *   enclosing article, so appended content inherits it with no JavaScript
 *   coordination and no re-application step to forget.
 * - **The gate is `moderate` on the whole mode, not bbPress's per-link tests.** Those
 *   run too, inside — which is how a Moderator ends up with a narrower set than a
 *   Keymaster without us enumerating either — but bbPress alone would also give a
 *   *participant* an "Edit" link on their own post inside the edit window. That is a
 *   posting affordance, and this release has no composer to edit in.
 *
 * @since 0.3.0
 */
class ModerationActions {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Whether this reader moderates the thread — the one test the screen branches on.
	 *
	 * Asked of the topic even for the reply rows: moderation is a property of the
	 * thread being read, and a per-reply answer would let the rows appear under some
	 * posts and not others, which reads as a rendering fault rather than a permission.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic being read.
	 * @return bool
	 */
	public function available( int $topic_id ): bool {
		return $this->wp->current_user_can_moderate( $topic_id );
	}

	/**
	 * The thread's own actions — close, pin, merge, trash, spam, approve, edit.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic being read.
	 */
	public function render_for_topic( int $topic_id ): void {
		$this->render_group(
			$this->wp->get_topic_moderation_links( $topic_id ),
			__( 'Thread actions', 'jtzl-bulletin' )
		);
	}

	/**
	 * One reply's actions — edit, move, split, trash, spam, approve.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply being rendered.
	 */
	public function render_for_reply( int $reply_id ): void {
		$this->render_group(
			$this->wp->get_reply_moderation_links( $reply_id ),
			__( 'Reply actions', 'jtzl-bulletin' )
		);
	}

	/**
	 * The toggle that turns the mode on, in the thread header beside Subscribe.
	 *
	 * Rendered only where it can work: the label names the next action ("Moderate",
	 * then "Done" once JavaScript has swapped it), and `aria-expanded` carries the
	 * state the label no longer does. No `aria-controls` — the mode reveals the thread
	 * group *and* a row under every post, so naming one region would describe less
	 * than the button does.
	 *
	 * @since 0.3.0
	 */
	public function render_toggle(): void {
		printf(
			'<button type="button" class="bltn-modtoggle" aria-expanded="false" data-bltn-modtoggle data-bltn-label-on="%1$s">%2$s</button>',
			esc_attr__( 'Done', 'jtzl-bulletin' ),
			esc_html__( 'Moderate', 'jtzl-bulletin' )
		);
	}

	/**
	 * One group of links, or nothing at all.
	 *
	 * The empty case is real rather than defensive: a Moderator on a *trashed* topic
	 * has had close, spam and trash unset by bbPress itself, and a role may hold
	 * `moderate` while every individual link declines. An empty bordered box under a
	 * post would read as a broken control, so there is no box.
	 *
	 * The heading is for screen readers only. Sighted users get the grouping from the
	 * box and its position; a visible "Reply actions" label on every reply would be
	 * the clutter this whole mode exists to avoid.
	 *
	 * @since 0.3.0
	 *
	 * @param string $links bbPress's link markup, or ''.
	 * @param string $label Accessible name for the group.
	 */
	private function render_group( string $links, string $label ): void {
		if ( '' === $links ) {
			return;
		}

		printf(
			'<div class="bltn-mod" role="group" aria-label="%1$s">%2$s</div>',
			esc_attr( $label ),
			$links // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bbPress-generated markup; each link is escaped at source (wp_nonce_url + esc_url/esc_attr in bbp_get_*_link).
		);
	}
}
