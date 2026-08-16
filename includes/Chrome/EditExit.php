<?php
/**
 * The way out of an edit or moderation form.
 *
 * @package JTZL\Bulletin
 * @since 0.5.3
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Where a reader goes when they open a form and change their mind.
 *
 * ## The two problems this closes, which are one problem
 *
 * Raised by Yoren, 2026-08-16: the composer carries a Cancel beside its Submit and
 * the edit forms carry nothing, and the forms look near-identical in our chrome —
 * because the reskin tier's whole job is to put bbPress's markup inside it. Two
 * behaviours, no visible cause.
 *
 * The first answer was that the difference is structural: the composer collapses **in
 * place** on a screen the reader stays on, so Cancel returns it to a resting state
 * that nothing else can; an edit form is a whole screen, and leaving a screen is the
 * app bar's job. That is a real distinction, and it does not survive measurement.
 * Standing at Submit on a reply edit at 390x844, the only exit was **712px away** at
 * the far end of the screen, and it did not say *cancel* — it was an unlabelled arrow
 * reading "Back to forums", pointing at the forums index. The convention being
 * appealed to (iOS puts Cancel in the nav bar) needs the nav bar to carry an explicit
 * Cancel aimed at where you came from. We had the arrow without either.
 *
 * So the rule is now the one the composer already followed: **wherever there is a
 * Submit, there is a way not to submit, beside it** — and the app bar's exit points at
 * the post being edited rather than at the index.
 *
 * ## ⚠ Three moderation forms cannot have the inline control, and it is not a choice
 *
 * bbPress gives `form-reply.php` and `form-topic.php` an
 * `bbp_theme_after_{reply,topic}_form_submit_button` action. **`form-topic-split.php`,
 * `form-topic-merge.php` and `form-reply-move.php` contain no `do_action` at all** —
 * verified across all nineteen `form-*` templates in 2.6.14. There is no seam.
 *
 * Script is not the way round it either: `Asset\AssetManager` enqueues the bundle for
 * `ScreenTier::Takeover` only, so no JavaScript runs on a reskin screen. Reaching
 * those three would mean shipping the reading bundle — AJAX paging, deep links, the
 * compose slot — to keymaster-only moderation screens to append one link, or
 * registering a template stack, which this plugin has never done and which would mean
 * owning three of bbPress's forms and every future change to them.
 *
 * They get the corrected app-bar exit instead, which is most of the value: they are
 * reached deliberately, by moderators, and their destination was the wrong one too.
 *
 * ## Where "out" is
 *
 * | Screen | Leaves to |
 * |---|---|
 * | Reply edit, reply move | the reply |
 * | Topic edit, split, merge | the topic |
 * | Every other reskin screen | the forums index, unchanged |
 *
 * ⚠ **The reply is asked about first**, and the order is load-bearing rather than
 * stylistic: on a reply edit request bbPress answers `bbp_is_topic_edit()` false and
 * `bbp_is_reply_edit()` true, but the reply's topic is still resolvable, so an
 * implementation that asked about the topic first would work by accident on one and
 * send the reader to the thread rather than to their own post on the other.
 *
 * @since 0.5.3
 */
class EditExit {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.5.3
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Whether this request is a form the reader may want to abandon.
	 *
	 * @since 0.5.3
	 *
	 * @return bool
	 */
	public function is_edit_form(): bool {
		return $this->wp->is_reply_edit() || $this->wp->is_topic_edit();
	}

	/**
	 * Where leaving this form lands, and what the control says.
	 *
	 * Returns the forums index for every other reskin screen, which is what
	 * `reskin.php` hardcoded before this class existed — a profile or an archive has
	 * no post to return to, and the index genuinely is its parent.
	 *
	 * ⚠ **The two getters fail differently, and testing for an empty string catches
	 * only one of them.** Measured on 2.6.14 rather than assumed:
	 *
	 * | Call | Returns |
	 * |---|---|
	 * | `bbp_get_topic_permalink( 0 )` | `false` — cast to `''` by the seam, caught |
	 * | `bbp_get_reply_url( 0 )` | **`'#post-0'`** — a bare fragment, NOT caught |
	 *
	 * So an unresolvable reply hands back a same-page anchor, which as a Cancel is a
	 * control that appears to work and does nothing: the reader taps it and stays on
	 * the form. `usable()` rejects a fragment-only URL for that reason. A post can
	 * stop resolving mid-edit — trashed under the member, or a filtered permalink —
	 * and the index is a worse destination but a real one.
	 *
	 * @since 0.5.3
	 *
	 * @return array{url: string, label: string}
	 */
	public function destination(): array {
		if ( $this->wp->is_reply_edit() ) {
			$url = $this->wp->get_reply_url( $this->wp->get_reply_id() );
			if ( $this->usable( $url ) ) {
				return array(
					'url'   => $url,
					'label' => __( 'Back to the reply', 'jtzl-bulletin' ),
				);
			}
		}

		if ( $this->wp->is_topic_edit() ) {
			$url = $this->wp->get_topic_permalink( $this->wp->get_topic_id() );
			if ( $this->usable( $url ) ) {
				return array(
					'url'   => $url,
					'label' => __( 'Back to the thread', 'jtzl-bulletin' ),
				);
			}
		}

		return array(
			'url'   => $this->wp->get_forums_url(),
			'label' => __( 'Back to forums', 'jtzl-bulletin' ),
		);
	}

	/**
	 * Whether a URL will actually take the reader somewhere else.
	 *
	 * A fragment-only URL is the failure mode `bbp_get_reply_url()` has for an
	 * unresolvable reply — see the table on `destination()`. It is not empty, so an
	 * emptiness test lets it through, and as a destination it means "stay here".
	 *
	 * @since 0.5.3
	 *
	 * @param string $url Candidate destination.
	 * @return bool
	 */
	private function usable( string $url ): bool {
		return '' !== $url && ! str_starts_with( $url, '#' );
	}

	/**
	 * Echo the Cancel beside Submit. Hooked on bbPress's two after-submit actions.
	 *
	 * ⚠ **Gated on the edit conditionals, and it has to be.** `form-reply.php` and
	 * `form-topic.php` are the same templates the *composer* renders on the takeover
	 * tier — so an ungated hook would put a second Cancel beside the one `reading.ts`
	 * already adds there, on the screen this class is not about.
	 *
	 * A link rather than a button, because it navigates. That also means it works with
	 * no JavaScript, which the composer's scripted Cancel cannot claim and does not
	 * need to: there, script failing leaves bbPress's own uncollapsed form, and the
	 * control has nothing to do. Here, script failing leaves a form with one action.
	 *
	 * @since 0.5.3
	 */
	public function render_cancel(): void {
		if ( ! $this->is_edit_form() ) {
			return;
		}

		$exit = $this->destination();
		if ( ! $this->usable( $exit['url'] ) ) {
			return;
		}

		printf(
			'<a class="bltn-compose__cancel" href="%s">%s</a>',
			esc_url( $exit['url'] ),
			esc_html__( 'Cancel', 'jtzl-bulletin' )
		);
	}
}
