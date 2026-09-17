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

	private ContextInterface $wp;

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
				'back_label' => __( 'Back', 'jtzls-bulletin-for-bbpress' ),
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
		 * The title is centered against the bar, not between unequal controls. Pass the
		 * trailing group's width so it cannot overlap the centered title.
		 */
		printf(
			'<header class="bltn-appbar" style="--bltn-appbar-side:%dpx">',
			(int) ( count( $trailing ) * 40 + 8 )
		);

		echo $this->leading( $back_url, $back_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts.

		$tag        = $heading ? 'h1' : 'span';
		$focus_attr = $heading ? ' data-bltn-heading tabindex="-1"' : '';
		printf( '<%1$s class="bltn-appbar__title"%2$s>%3$s', $tag, $focus_attr, esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- tag + attr are internal literals.
		if ( '' !== $subtitle ) {
			printf( '<small>%s</small>', esc_html( $subtitle ) );
		}
		printf( '</%s>', $tag ); // phpcs:ignore WordPress.Security.EscapeOutput -- internal literal.

		printf( '<div class="bltn-appbar__actions">%s</div>', implode( '', $trailing ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- each part assembled from escaped values.

		echo '</header>';
	}

	/**
	 * The trailing group, in reading order: home, search, account.
	 *
	 * Home is omitted on the index; elsewhere it differs from the one-level back control.
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
				esc_attr__( 'Forums home', 'jtzls-bulletin-for-bbpress' ),
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
			esc_attr__( 'Search', 'jtzls-bulletin-for-bbpress' ),
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
		 * Return signed-in readers to the current screen; fall back to the forum index
		 * when the request cannot identify itself.
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
			? __( 'Your account', 'jtzls-bulletin-for-bbpress' )
			: __( 'Sign in', 'jtzls-bulletin-for-bbpress' );
	}
}
