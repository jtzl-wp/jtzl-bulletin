<?php
/**
 * The minimal top strip that replaces the theme header.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\Support\Icons;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders the app bar: a leading back control (or spacer), the screen title
 * (which doubles as the focus heading), and a trailing account button.
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

		// Title (+ optional subtitle). The title doubles as the screen's focus target.
		$tag        = $heading ? 'h1' : 'span';
		$focus_attr = $heading ? ' data-bltn-heading tabindex="-1"' : '';
		printf( '<%1$s class="bltn-appbar__title"%2$s>%3$s', $tag, $focus_attr, esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- tag + attr are internal literals.
		if ( '' !== $subtitle ) {
			printf( '<small>%s</small>', esc_html( $subtitle ) );
		}
		printf( '</%s>', $tag ); // phpcs:ignore WordPress.Security.EscapeOutput -- internal literal.

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
	 * URL for the account button.
	 *
	 * Public-read v1 forums have no account screen: logged-out users go to
	 * wp-login.php; logged-in users go to their bbPress profile.
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
	 * @return string
	 */
	private function account_label(): string {
		return $this->wp->is_user_logged_in()
			? __( 'Your account', 'jtzl-bulletin' )
			: __( 'Sign in', 'jtzl-bulletin' );
	}
}
