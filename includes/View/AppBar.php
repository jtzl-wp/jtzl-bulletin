<?php
/**
 * The minimal top strip that replaces the theme header.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\Support\AllowedHtml;
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
		 * The title is centered against the bar, not between unequal controls. Pass the
		 * trailing group's width so it cannot overlap the centered title.
		 */
		printf(
			'<header class="bltn-appbar" style="--bltn-appbar-side:%dpx">',
			(int) ( count( $trailing ) * 40 + 8 )
		);

		if ( '' === $back_url ) {
			// A 40px hole the width of an icon button, keeping the title centered.
			echo '<span class="bltn-appbar__spacer" aria-hidden="true"></span>';
		} else {
			$this->icon_link( 'bltn-iconbtn bltn-iconbtn--back', $back_url, $back_label, Icons::chevron_left() );
		}

		if ( $heading ) {
			printf( '<h1 class="bltn-appbar__title" data-bltn-heading tabindex="-1">%s', esc_html( $title ) );
		} else {
			printf( '<span class="bltn-appbar__title">%s', esc_html( $title ) );
		}
		if ( '' !== $subtitle ) {
			printf( '<small>%s</small>', esc_html( $subtitle ) );
		}
		if ( $heading ) {
			echo '</h1>';
		} else {
			echo '</span>';
		}

		echo '<div class="bltn-appbar__actions">';
		foreach ( $trailing as $link ) {
			$this->icon_link( 'bltn-iconbtn', $link['url'], $link['label'], $link['icon'] );
		}
		echo '</div>';

		echo '</header>';
	}

	/**
	 * The trailing group, in reading order: home, search, account.
	 *
	 * Home is omitted on the index; elsewhere it differs from the one-level back control.
	 * Search is omitted where there is nowhere for it to go.
	 *
	 * @since 0.3.0
	 *
	 * @return list<array{url:string,label:string,icon:string}>
	 */
	private function trailing(): array {
		$out = array();

		if ( ! $this->wp->is_forum_archive() ) {
			$out[] = array(
				'url'   => $this->wp->get_forums_url(),
				'label' => __( 'Forums home', 'jtzl-bulletin' ),
				'icon'  => Icons::home(),
			);
		}

		if ( $this->wp->allow_search() && ! $this->wp->is_search() ) {
			$out[] = array(
				'url'   => $this->wp->get_search_url(),
				'label' => __( 'Search', 'jtzl-bulletin' ),
				'icon'  => Icons::search(),
			);
		}

		$out[] = array(
			'url'   => $this->account_url(),
			'label' => $this->account_label(),
			'icon'  => Icons::account(),
		);

		return $out;
	}

	/**
	 * Echo one icon-only link, named by its label.
	 *
	 * @since 0.6.5
	 *
	 * @param string $class CSS classes.
	 * @param string $url   Destination.
	 * @param string $label Accessible name.
	 * @param string $icon  One of the Icons glyphs.
	 */
	private function icon_link( string $class, string $url, string $label, string $icon ): void {
		printf(
			'<a class="%s" href="%s" aria-label="%s">%s</a>',
			esc_attr( $class ),
			esc_url( $url ),
			esc_attr( $label ),
			wp_kses( $icon, AllowedHtml::icon() )
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
			? __( 'Your account', 'jtzl-bulletin' )
			: __( 'Sign in', 'jtzl-bulletin' );
	}
}
