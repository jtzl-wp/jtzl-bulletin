<?php
/**
 * The profile identity block: the displayed user's name, handle and role,
 * rendered beside their avatar in the reskinned member-profile header.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Renders the profile header's identity block — display name, @handle and forum
 * role, beside the avatar.
 *
 * The bbPress profile header renders no name at all — only the avatar, then the
 * tab nav. Its `@nicename` heading lives further down the body, and only on
 * the Profile tab, so the avatar and the name never read as one unit. This hooks
 * `bbp_template_before_user_details_menu_items` — which fires between the avatar
 * and the nav, inside `#bbp-single-user-details` — to render the identity block
 * there, so every user tab leads with a coherent identity. The now-duplicate
 * `@nicename` heading on the Profile tab is hidden in CSS.
 *
 * @since 0.3.0
 */
class ProfileIdentity {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Echo the identity block into the profile header.
	 *
	 * @since 0.3.0
	 */
	public function render(): void {
		$name     = $this->wp->get_displayed_user_name();
		$nicename = $this->wp->get_displayed_user_nicename();

		// Nothing identifiable to show (e.g. a malformed request): render nothing
		// rather than an empty block that would just add chrome to the header.
		if ( '' === $name && '' === $nicename ) {
			return;
		}

		echo '<div class="bltn-identity">';

		if ( '' !== $name ) {
			printf( '<span class="bltn-identity__name">%s</span>', esc_html( $name ) );
		}

		// @handle and role read as one quiet meta line under the name.
		$meta = array();
		if ( '' !== $nicename ) {
			$meta[] = '@' . $nicename;
		}
		$role = $this->wp->get_displayed_user_role();
		if ( '' !== $role ) {
			$meta[] = $role;
		}
		if ( array() !== $meta ) {
			printf(
				'<span class="bltn-identity__meta">%s</span>',
				esc_html( implode( ' · ', $meta ) )
			);
		}

		echo '</div>';
	}
}
