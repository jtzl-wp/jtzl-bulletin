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
	 * back_url (leading back control; omit for a spacer), back_label (its label),
	 * and heading (whether the title is the screen's focus h1).
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

		// Resolved before anything is emitted, because the leading side has to know:
		// the title is centered in the space between the two, so a second trailing
		// control has to be answered by a second leading spacer or the title drifts
		// half a button off centre on every screen.
		$search = $this->search_link();

		echo '<header class="bltn-appbar">';

		// Leading: back control, or a spacer to keep the title centered.
		if ( '' !== $back_url ) {
			printf(
				'<a class="bltn-iconbtn bltn-iconbtn--back" href="%s" aria-label="%s">%s</a>',
				esc_url( $back_url ),
				esc_attr( $back_label ),
				Icons::chevron_left() // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
			);
		} else {
			echo '<span class="bltn-appbar__spacer" aria-hidden="true"></span>';
		}
		if ( '' !== $search ) {
			echo '<span class="bltn-appbar__spacer" aria-hidden="true"></span>';
		}

		// Title (+ optional subtitle). The title doubles as the screen's focus target.
		$tag        = $heading ? 'h1' : 'span';
		$focus_attr = $heading ? ' data-bltn-heading tabindex="-1"' : '';
		printf( '<%1$s class="bltn-appbar__title"%2$s>%3$s', $tag, $focus_attr, esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- tag + attr are internal literals.
		if ( '' !== $subtitle ) {
			printf( '<small>%s</small>', esc_html( $subtitle ) );
		}
		printf( '</%s>', $tag ); // phpcs:ignore WordPress.Security.EscapeOutput -- internal literal.

		echo $search; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled by search_link() from escaped parts.

		// Trailing: account.
		printf(
			'<a class="bltn-iconbtn" href="%s" aria-label="%s">%s</a>',
			esc_url( $this->account_url() ),
			esc_attr( $this->account_label() ),
			Icons::account() // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
		);

		echo '</header>';
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
		return $this->wp->get_login_url( $this->wp->get_forums_url() );
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
