<?php
/**
 * Screen tiering — which treatment a request gets.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Screen;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Decides, for the current request, whether Bulletin takes it over with our own
 * document, reskins bbPress's own markup inside our chrome, or leaves it to the
 * active theme. Replaces the earlier single is_reading_screen() boolean: both the
 * template swap (Takeover\TemplateController) and the style suppression
 * (Asset\AssetManager) now branch on the tier this returns.
 *
 * @since 0.3.0
 */
class ScreenClassifier {

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
	 * The treatment for the current request.
	 *
	 * Reskin is the catch-all for bbPress front-end screens precisely so that
	 * nothing a reader can reach ever falls through to the site theme; only the
	 * enumerated takeover screens and the phase-owned exclusions divert from it.
	 *
	 * @since 0.3.0
	 *
	 * @return ScreenTier
	 */
	public function tier(): ScreenTier {
		if ( ! $this->wp->is_bbpress() ) {
			return ScreenTier::None;
		}
		if ( $this->is_takeover() ) {
			return ScreenTier::Takeover;
		}
		if ( $this->is_excluded() ) {
			return ScreenTier::None;
		}
		return ScreenTier::Reskin;
	}

	/**
	 * Whether the request is one of the takeover reading screens.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_takeover(): bool {
		return $this->is_forums_index()
			|| $this->is_single_forum()
			|| $this->is_single_topic();
	}

	/**
	 * Whether the current request is the forums index (home).
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_forums_index(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_forum_archive();
	}

	/**
	 * Whether the current request is a single forum's thread list.
	 *
	 * The takeover proceeds on a password-protected forum too: the screen template
	 * renders WordPress's own password form in place of the sub-forums and threads
	 * until the password is supplied (issue #18), so the reader stays in Bulletin's
	 * chrome rather than dropping into the theme.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_single_forum(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_single_forum();
	}

	/**
	 * Whether the current request is a single topic's reading view.
	 *
	 * Every single topic, with no exceptions left. P1 declined the takeover when
	 * threaded replies were active, because bbp_has_replies() forces
	 * posts_per_page to -1 for a hierarchical query and the thread would have
	 * loaded unpaginated (issue #12). Query\ReplyQuery now asks for a flat query
	 * outright, so there is nothing left to decline: the thread pages the way
	 * every other thread does and its shape is rendered as reply context rather
	 * than depth (issue #37). Removing the guard also retires the reskinned
	 * threaded-topic screen (issue #64) — nothing routes a reader to bbPress's own
	 * content-single-topic.php any more.
	 *
	 * A password-protected topic still takes over: the screen template renders
	 * WordPress's own password form in place of the opening post and replies until
	 * the password is supplied (issue #18), keeping the reader in Bulletin's chrome.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_single_topic(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_single_topic();
	}

	/**
	 * Whether the request is a bbPress screen owned by another phase, and so left
	 * to the theme rather than reskinned.
	 *
	 * The posting composer and the edit forms (topic/reply/forum) belong to the
	 * posting phase (P4) and to keymaster administration — not a reader's forum
	 * flow — so they are excluded from the reskin catch-all (see
	 * docs/p2-screen-inventory.md, "True leave to the theme is reserved …").
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	private function is_excluded(): bool {
		if ( $this->is_moderation_form() ) {
			return false;
		}

		return $this->wp->is_topic_edit()
			|| $this->wp->is_reply_edit()
			|| $this->wp->is_forum_edit();
	}

	/**
	 * Whether the request is one of bbPress's moderation forms.
	 *
	 * These have to be asked about before the edit exclusion, because bbPress builds
	 * all three on top of it: `bbp_is_topic_merge()` and `bbp_is_topic_split()` are
	 * `bbp_is_topic_edit()` plus an `action` parameter, and `bbp_is_reply_move()` is
	 * `bbp_is_reply_edit()` plus one. Excluding every edit request therefore excluded
	 * these too, and merge, split and move — the destinations of three links the
	 * moderation mode renders — dropped a moderator out of Bulletin into the site
	 * theme mid-task. That contradicts the tiering's own rule that nothing a reader
	 * can reach from inside the shell leaves it, and issue #36's "merge/split/move
	 * reachable and legible in-shell".
	 *
	 * The plain edit forms stay excluded. They are the composer's surface, and P4 owns
	 * it; a moderator following Edit is going somewhere this release does not render,
	 * which is a different problem from the one this fixes.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	private function is_moderation_form(): bool {
		return $this->wp->is_topic_merge()
			|| $this->wp->is_topic_split()
			|| $this->wp->is_reply_move();
	}
}
