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
	 * Declines the takeover when threaded replies are active: bbp_has_replies()
	 * forces posts_per_page to -1 for a hierarchical query, so our template would
	 * load the whole thread unpaginated and "load more replies" would never appear
	 * (issue #12). Such a topic is no longer left to the theme, though — it falls
	 * through to Reskin, where bbPress renders it threaded inside our chrome with
	 * its own pagination. Proper threaded depth in the takeover view supersedes
	 * this (issue #37).
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
		return $this->wp->is_bbpress() && $this->wp->is_single_topic() && ! $this->wp->is_thread_replies_active();
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
		return $this->wp->is_topic_edit()
			|| $this->wp->is_reply_edit()
			|| $this->wp->is_forum_edit();
	}
}
