<?php
/**
 * The forum screen's "Start a thread" control and form.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Starting a thread, on the screen where threads live.
 *
 * ## Why this is a fixed bar and the reply composer is not
 *
 * The two compose affordances have different shapes and that is deliberate rather
 * than an oversight. On the reading view the bottom of the screen is spoken for by
 * thread Prev/Next — **JT's own proposal** — so the composer sits inline at the foot
 * of the thread. This screen's bottom is free, the prototype puts a fixed
 * `.composebar` there, and JT approved it.
 *
 * The actions differ too, which is what makes the inconsistency bearable: starting a
 * thread is a **screen-level** action, available wherever you are in a long list;
 * replying belongs where the conversation ended.
 *
 * ## Why the bar is not simply "on a forum page"
 *
 * ⚠ The gate is `bbp_current_user_can_access_create_topic_form()`, **not**
 * `is_single_forum()`. Four cases reach this screen where a bar would lead straight
 * to a refusal:
 *
 * - a **category** — this takeover screen renders categories as well as forums;
 * - a **closed forum**;
 * - a member **without `publish_topics`**;
 * - a **logged-out visitor** with anonymous posting off.
 *
 * A control whose destination cannot honour it carries nothing. That is the same rule
 * `Chrome\ReplyToLink` exists for, and DESIGN.md's "a broken control is worse than no
 * control" (#62).
 *
 * ⚠ **Three of those four come from bbPress's own test; the category does not, and
 * the plan said it did.** `bbp_current_user_can_access_create_topic_form()` gates on
 * `bbp_is_forum_open()`, which is only `! bbp_is_forum_closed()` and says nothing
 * about type — so on a category it answers **true**, bbPress renders a topic form, and
 * `bbp_new_topic_handler()` then refuses the post: *"This forum is a category. No
 * topics can be created in this forum."* (`topics/functions.php:222`). Exactly the
 * refusal the gate exists to prevent, arriving one screen later. So the category is
 * asked about separately, here.
 *
 * ## What each of those four gets instead
 *
 * | State | The screen shows |
 * |---|---|
 * | Can start one | the bar, and the form at the foot of the list |
 * | Logged out | the bar, carrying "Sign in to start a thread" |
 * | Closed forum | no bar; one quiet line at the foot |
 * | Category | no bar, no line |
 * | No capability | no bar, no line |
 *
 * **Closed speaks; a category and a missing capability do not**, on the reading
 * view's reasoning. *Closed is a fact about this forum*, and a reader looking at its
 * threads is entitled to know why they cannot add one. *A category is structural* —
 * it is not a place threads live, and the screen already says so by listing forums
 * instead of threads. *No capability is a fact about the reader*, true on every forum
 * forever, and saying it on each one is nagging with no action attached.
 *
 * @since 0.5.0
 */
class StartThread {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Renders bbPress's own form templates, corrected for this tier.
	 *
	 * @var BbPressForm
	 */
	private BbPressForm $form;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface $wp   WordPress/bbPress seam.
	 * @param BbPressForm      $form Renders bbPress's own form templates.
	 */
	public function __construct( ContextInterface $wp, BbPressForm $form ) {
		$this->wp   = $wp;
		$this->form = $form;
	}

	/**
	 * Echo the fixed bar, or nothing.
	 *
	 * ⚠ **The control is an anchor to `#new-post`, not a button**, and that is what
	 * makes the no-JS path work: with no script the form is already open at the foot of
	 * the list and this jumps to it, which is an ordinary in-page link doing an ordinary
	 * thing. The script upgrades it — collapsing the form, then opening and focusing it
	 * on tap — but it enhances a control that already worked.
	 *
	 * @since 0.5.0
	 *
	 * @param int $forum_id The forum being read.
	 */
	public function render_bar( int $forum_id ): void {
		if ( $this->may_start( $forum_id ) ) {
			printf(
				'<div class="bltn-composebar"><a class="bltn-composebar__btn" href="#new-post" aria-controls="new-post" data-bltn-compose-open data-bltn-cancel="%s">%s</a></div>',
				esc_attr__( 'Cancel', 'jtzl-bulletin' ),
				esc_html__( 'Start a thread', 'jtzl-bulletin' )
			);
			return;
		}

		/*
		 * Logged out is the one refusal with something to offer: sign in, and the
		 * question becomes answerable. The others are answered already.
		 *
		 * ⚠ **Both other refusals have to be excluded here, not just closure** — and
		 * missing the category was a bug in the first draft of this class, on the one
		 * route that skips `may_start()`. Signing in cannot make a category accept a
		 * topic any more than it can reopen a closed forum, so offering the round trip
		 * is the same broken control the class note argues against. Raised by Gitar
		 * on #113; the tests missed it because the category case ran as an
		 * administrator and never travelled this branch.
		 */
		if ( ! $this->wp->is_user_logged_in()
			&& ! $this->wp->is_forum_closed( $forum_id )
			&& ! $this->wp->is_forum_category( $forum_id ) ) {
			printf(
				'<div class="bltn-composebar"><a class="bltn-composebar__btn" href="%s">%s</a></div>',
				esc_url( $this->wp->get_login_url( $this->wp->get_current_url() ) ),
				esc_html__( 'Sign in to start a thread', 'jtzl-bulletin' )
			);
		}
	}

	/**
	 * Echo the form at the foot of the list, or the one line that replaces it.
	 *
	 * ⚠ **A form carrying a rejection must not rest collapsed.** bbPress prints
	 * validation failures inside it and puts the reader's typed content back in the
	 * field with them, so folding it away means submitting appears to do nothing —
	 * reported on the reply composer and fixed there; the same rule applies to every
	 * screen that renders one.
	 *
	 * @since 0.5.0
	 *
	 * @param int $forum_id The forum being read.
	 */
	public function render_form( int $forum_id ): void {
		if ( $this->may_start( $forum_id ) ) {
			printf(
				'<div class="bltn-compose" data-bltn-compose="%s"><div class="bltn-compose__form">',
				esc_attr( $this->wp->has_errors() ? 'open' : 'collapsible' )
			);
			$this->form->render( 'topic' );
			echo '</div></div>';
			return;
		}

		if ( $this->wp->is_forum_closed( $forum_id ) ) {
			printf(
				'<div class="bltn-compose"><p class="bltn-compose__note">%s</p></div>',
				esc_html__( 'This forum is closed to new topics.', 'jtzl-bulletin' )
			);
		}
	}

	/**
	 * Whether a thread can actually be started here.
	 *
	 * Two questions, because bbPress's own helper only answers one of them — see the
	 * class note on categories.
	 *
	 * @since 0.5.0
	 *
	 * @param int $forum_id The forum being read.
	 * @return bool
	 */
	private function may_start( int $forum_id ): bool {
		return $this->wp->can_access_create_topic_form()
			&& ! $this->wp->is_forum_category( $forum_id );
	}
}
