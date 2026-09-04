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
 * Classifies the current request as takeover, reskin, or theme-owned.
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

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * The treatment for the current request.
	 *
	 * Reskin is the fallback for bbPress front-end screens. Only explicit takeover
	 * screens and exclusions use another tier.
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
			|| $this->is_single_topic()
			|| $this->is_search();
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
	 * until the password is supplied, so the reader stays in Bulletin's
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
	 * ReplyQuery requests a flat query because bbPress disables pagination for
	 * hierarchical replies. Reply context preserves the relationship without depth.
	 *
	 * A password-protected topic still takes over: the screen template renders
	 * WordPress's own password form in place of the opening post and replies until
	 * the password is supplied, keeping the reader in Bulletin's chrome.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_single_topic(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_single_topic();
	}

	/**
	 * Whether the current request is the search screen.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_search(): bool {
		return $this->wp->is_bbpress() && $this->wp->is_search();
	}

	/**
	 * Whether the active theme owns this bbPress screen.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	private function is_excluded(): bool {
		return $this->wp->is_forum_edit();
	}
}
