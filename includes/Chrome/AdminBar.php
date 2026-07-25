<?php
/**
 * Admin-bar visibility inside Bulletin's shell.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Keeps WordPress's admin bar off the screens Bulletin owns, for everyone who
 * cannot administrate the site.
 *
 * The bar paints a fixed strip above our app bar, so it is the first thing on the
 * screen — and for the role an ordinary member actually holds (a bbPress
 * participant, i.e. a WordPress subscriber) every link in it leads somewhere they
 * have no capability to use. That is the navigational clutter the brief exists to
 * remove, in the most expensive position on a phone.
 *
 * Two things this deliberately does NOT do:
 *
 * - It never turns the bar *on*. For a user who can administrate, the incoming
 *   value passes through untouched, so their own "Show Toolbar when viewing site"
 *   preference still decides — we suppress a default, we don't override a choice.
 * - It leaves screens we don't own alone (ScreenTier::None): every non-bbPress page,
 *   and the edit forms excluded for the posting phase, which is precisely where a
 *   keymaster is administrating rather than reading.
 *
 * @since 0.3.0
 */
class AdminBar {

	/**
	 * The capability that earns the bar.
	 *
	 * A capability rather than a role name, because roles can be renamed and
	 * recomposed per site while the capability a decision rests on does not move.
	 * `manage_options` is the administrator test: it is what the bar's own
	 * destinations (the dashboard, the customiser, site settings) require.
	 *
	 * Note a bbPress keymaster does not qualify on forum powers alone — forum
	 * moderation belongs in the shell, in Bulletin's own affordances (issue #36),
	 * not in WordPress's chrome.
	 *
	 * @since 0.3.0
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Screen-tier classifier.
	 *
	 * @var ScreenClassifier
	 */
	private ScreenClassifier $screen;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ContextInterface $wp     WordPress/bbPress seam.
	 * @param ScreenClassifier $screen Screen-tier classifier.
	 */
	public function __construct( ContextInterface $wp, ScreenClassifier $screen ) {
		$this->wp     = $wp;
		$this->screen = $screen;
	}

	/**
	 * Decide whether the admin bar shows for this request.
	 *
	 * WordPress resolves this once, on `template_redirect` at priority 0 — after the
	 * main query is parsed, so the tier is knowable — and the answer then governs
	 * both the bar's markup in `wp_footer()` and the `admin-bar` body class that our
	 * stylesheet reserves height against. Suppressing it here is therefore enough on
	 * its own: no bar, no class, no reserved gap.
	 *
	 * The incoming value is deliberately untyped. A WordPress filter chain enforces
	 * nothing: one careless callback with a missing `return` hands the next one
	 * `null`, and a `bool` parameter would make that a TypeError — a white screen on
	 * every forum page, caused by somebody else's plugin but blamed on ours. Casting
	 * is also what WordPress does with the result (`is_admin_bar_showing()`'s callers
	 * read it as a boolean), so normalising here changes no behaviour.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $show Whether WordPress (and any earlier filter) would show it.
	 * @return bool
	 */
	public function filter_show_admin_bar( $show ): bool {
		$showing = (bool) $show;
		if ( ScreenTier::None === $this->screen->tier() ) {
			return $showing;
		}
		return $showing && $this->wp->current_user_can( self::CAPABILITY );
	}
}
