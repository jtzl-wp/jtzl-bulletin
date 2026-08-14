<?php
/**
 * The minimal top strip that replaces the theme header.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\Support\Icons;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders the app bar: a leading back control (or spacer), the screen title
 * (which doubles as the focus heading), and a trailing account button.
 *
 * @since 0.1.0
 */
class AppBar {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Echo the app bar.
	 *
	 * Recognised keys: title (plain heading text), subtitle (small line under it),
	 * back_url (leading back control; omit for a spacer), back_label (its accessible
	 * name), and heading (whether the title is the screen's focus h1).
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $args Bar arguments.
	 */
	public function render( array $args ): void {
		$args = wp_parse_args(
			$args,
			array(
				'title'      => $this->wp->get_bloginfo( 'name' ),
				'subtitle'   => '',
				'back_url'   => '',
				'back_label' => __( 'Back', 'jtzl-bulletin' ),
				'heading'    => true,
			)
		);

		$title      = (string) $args['title'];
		$subtitle   = (string) $args['subtitle'];
		$back_url   = (string) $args['back_url'];
		$back_label = (string) $args['back_label'];
		$heading    = (bool) $args['heading'];

		$trailing = $this->trailing();

		/*
		 * The title is centred on the SCREEN, not in the space the controls leave over,
		 * so it is taken out of the flex row and positioned against the bar itself. The
		 * row then holds two things — the leading control and the trailing group — and
		 * pushes them apart.
		 *
		 * Which means the title has to be told how much room to leave, or it would run
		 * under the buttons: centred, its half-width is bounded by the WIDER side, and
		 * that is the trailing group. Its width travels as a custom property because the
		 * group is not the same on every screen — search carries no search button. Forty
		 * per button, which they are, plus one gap to keep off the title.
		 */
		printf(
			'<header class="bltn-appbar" style="--bltn-appbar-side:%dpx">',
			(int) ( count( $trailing ) * 40 + 8 )
		);

		// Leading: the way out of this screen — one level up — or a spacer holding its
		// place on the index, which has no level above it.
		echo $this->leading( $back_url, $back_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts.

		// Title (+ optional subtitle). The title doubles as the screen's focus target.
		$tag        = $heading ? 'h1' : 'span';
		$focus_attr = $heading ? ' data-bltn-heading tabindex="-1"' : '';
		printf( '<%1$s class="bltn-appbar__title"%2$s>%3$s', $tag, $focus_attr, esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- tag + attr are internal literals.
		if ( '' !== $subtitle ) {
			printf( '<small>%s</small>', esc_html( $subtitle ) );
		}
		printf( '</%s>', $tag ); // phpcs:ignore WordPress.Security.EscapeOutput -- internal literal.

		// Wrapped, so the flex row has exactly two items to push apart and the group
		// keeps its own spacing whatever the title does.
		printf( '<div class="bltn-appbar__actions">%s</div>', implode( '', $trailing ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- each part assembled from escaped values.

		echo '</header>';
	}

	/**
	 * The trailing group, in reading order: home, search, account.
	 *
	 * Home is a separate journey from the back control beside the title, which goes one
	 * level up — from a thread in a nested forum that was three taps to the index, and
	 * no control named where it went (issue #86). It leads the group because it is the
	 * only one that leaves the reading path; search and account both open something.
	 *
	 * Omitted on the index itself, where it would point at the page it is on.
	 *
	 * @since 0.3.0
	 *
	 * @return list<string>
	 */
	private function trailing(): array {
		$out = array();

		if ( ! $this->wp->is_forum_archive() ) {
			$out[] = sprintf(
				'<a class="bltn-iconbtn" href="%s" aria-label="%s">%s</a>',
				esc_url( $this->wp->get_forums_url() ),
				esc_attr__( 'Forums home', 'jtzl-bulletin' ),
				Icons::home()
			);
		}

		$search = $this->search_link();
		if ( '' !== $search ) {
			$out[] = $search;
		}

		$out[] = sprintf(
			'<a class="bltn-iconbtn" href="%s" aria-label="%s">%s</a>',
			esc_url( $this->account_url() ),
			esc_attr( $this->account_label() ),
			Icons::account()
		);

		return $out;
	}

	/**
	 * The leading control: one level up, or a spacer holding its place.
	 *
	 * Its destination is the screen's own — a thread's forum, a sub-forum's parent —
	 * so it is the way back along the path a reader walked. Leaving the forums
	 * entirely is the home button's job, in the trailing group.
	 *
	 * @since 0.3.0
	 *
	 * @param string $url   Destination, or '' for no control.
	 * @param string $label Accessible name.
	 * @return string
	 */
	private function leading( string $url, string $label ): string {
		if ( '' === $url ) {
			return $this->spacer();
		}

		return sprintf(
			'<a class="bltn-iconbtn bltn-iconbtn--back" href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $label ),
			Icons::chevron_left()
		);
	}

	/**
	 * A 40px hole the width of an icon button, keeping the title centered.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	private function spacer(): string {
		return '<span class="bltn-appbar__spacer" aria-hidden="true"></span>';
	}

	/**
	 * The search entry point, or '' when there is nowhere for it to go.
	 *
	 * Search had no entry point on any screen a reader could reach. bbPress's inline
	 * field appears on the topic archive and the profile tabs, and nothing in bbPress
	 * links the topic archive at all — so search existed on screens nobody navigates
	 * to (issue #35). One control in the bar reaches it from every screen instead,
	 * on both tiers, at no vertical cost.
	 *
	 * A link, not a field. The bar is three items wide on a phone and a text input
	 * cannot live there without taking the title's place; putting the input on the
	 * screen it belongs to means the bar gains an icon rather than a control, which
	 * is the version of this that survives "everything here is subtraction".
	 *
	 * Two conditions hide it, and both are the same thought: never offer a door to a
	 * room the reader is in or that does not exist. It is absent on the search screen
	 * itself — where the same glyph is the submit control, and one glyph must not
	 * mean two things at once — and absent on a site with `_bbp_allow_search` off,
	 * where bbPress answers that URL with nothing.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	private function search_link(): string {
		if ( ! $this->wp->allow_search() || $this->wp->is_search() ) {
			return '';
		}

		return sprintf(
			'<a class="bltn-iconbtn" href="%s" aria-label="%s">%s</a>',
			esc_url( $this->wp->get_search_url() ),
			esc_attr__( 'Search', 'jtzl-bulletin' ),
			Icons::search()
		);
	}

	/**
	 * URL for the account button.
	 *
	 * Public-read v1 forums have no account screen: logged-out users go to
	 * wp-login.php; logged-in users go to their bbPress profile.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private function account_url(): string {
		if ( $this->wp->is_user_logged_in() ) {
			$profile = $this->wp->get_user_profile_url( $this->wp->get_current_user_id() );
			if ( '' !== $profile ) {
				return $profile;
			}
		}

		/*
		 * Back to where they were, not to the forums index. Until 0.5.0 this sent a
		 * logged-out reader to `get_forums_url()`, which was harmless while it was the
		 * only sign-in route on the screen. P4 put a second one at the foot of every
		 * thread — "Sign in to reply", carrying this URL — and two controls one above
		 * the other going to different places is the kind of thing a reader notices
		 * exactly once, when the first one loses their place.
		 *
		 * The index is still the fallback: a request that cannot name itself has to
		 * land somewhere, and the forums are where the app begins.
		 */
		$here = $this->wp->get_current_url();

		return $this->wp->get_login_url( '' !== $here ? $here : $this->wp->get_forums_url() );
	}

	/**
	 * Accessible label for the account button, reflecting auth state.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private function account_label(): string {
		return $this->wp->is_user_logged_in()
			? __( 'Your account', 'jtzl-bulletin' )
			: __( 'Sign in', 'jtzl-bulletin' );
	}
}
