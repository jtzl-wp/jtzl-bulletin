<?php
/**
 * The way out of an edit or moderation form.
 *
 * @package JTZL\Bulletin
 * @since 0.5.3
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Returns edit forms to the edited reply or topic, with the reply checked first.
 * Other reskin screens return to the forums index.
 */
class EditExit {

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Whether this request is a form the reader may want to abandon.
	 *
	 * @since 0.5.3
	 *
	 * @return bool
	 */
	public function is_edit_form(): bool {
		return $this->wp->is_reply_edit() || $this->wp->is_topic_edit();
	}

	/**
	 * Where leaving this form lands, and what the control says.
	 *
	 * @since 0.5.3
	 *
	 * @return array{url: string, label: string}
	 */
	public function destination(): array {
		if ( $this->wp->is_reply_edit() ) {
			$url = $this->wp->get_reply_url( $this->wp->get_reply_id() );
			if ( $this->usable( $url ) ) {
				return array(
					'url'   => $url,
					'label' => __( 'Back to the reply', 'jtzl-bulletin' ),
				);
			}
		}

		if ( $this->wp->is_topic_edit() ) {
			$url = $this->wp->get_topic_permalink( $this->wp->get_topic_id() );
			if ( $this->usable( $url ) ) {
				return array(
					'url'   => $url,
					'label' => __( 'Back to the thread', 'jtzl-bulletin' ),
				);
			}
		}

		return array(
			'url'   => $this->wp->get_forums_url(),
			'label' => __( 'Back to forums', 'jtzl-bulletin' ),
		);
	}

	/**
	 * Whether a URL will actually take the reader somewhere else.
	 *
	 * A fragment-only URL is the failure mode `bbp_get_reply_url()` has for an
	 * unresolvable reply — see the table on `destination()`. It is not empty, so an
	 * emptiness test lets it through, and as a destination it means "stay here".
	 *
	 * @since 0.5.3
	 *
	 * @param string $url Candidate destination.
	 * @return bool
	 */
	private function usable( string $url ): bool {
		return '' !== $url && ! str_starts_with( $url, '#' );
	}

	/**
	 * Echo the Cancel beside Submit. Hooked on bbPress's two after-submit actions.
	 *
	 * @since 0.5.3
	 */
	public function render_cancel(): void {
		if ( ! $this->is_edit_form() ) {
			return;
		}

		$exit = $this->destination();
		if ( ! $this->usable( $exit['url'] ) ) {
			return;
		}

		printf(
			'<a class="bltn-compose__cancel" href="%s">%s</a>',
			esc_url( $exit['url'] ),
			esc_html__( 'Cancel', 'jtzl-bulletin' )
		);
	}
}
