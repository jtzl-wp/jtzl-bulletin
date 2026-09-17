<?php
/**
 * The acknowledgement a reply held for moderation gets on the redirect.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Owns the one-time acknowledgement for held replies that are not visible.
 *
 * Pending replies already visible to their author need no duplicate notice. Spam
 * and anonymous replies share neutral wording to avoid exposing spam decisions.
 */
class HeldNotice {

	public const FLAG = 'bltn_held';

	public const ANCHOR = 'bltn-held';

	private ContextInterface $wp;

	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Mark redirects only when the submitted reply will not be visible.
	 *
	 * The redirect hook is the only point with the reply status, author, and current
	 * user. Public statuses come from bbPress so custom moderation states still work.
	 */
	public function filter_redirect( $url, $redirect_to = null, $reply_id = null ) {
		unset( $redirect_to );

		if ( ! is_string( $url ) || ! is_numeric( $reply_id ) ) {
			return $url;
		}

		$reply = (int) $reply_id;

		if ( in_array( $this->wp->get_post_status( $reply ), $this->wp->get_public_reply_statuses(), true ) ) {
			return $url;
		}

		// The visible held row already explains its state to its author.
		if ( $this->shown_back( $reply ) ) {
			return $url;
		}

		/*
		 * The bbPress post fragment points to a reply excluded from the query. Target
		 * the acknowledgement instead so normal browser fragment navigation reveals it.
		 */
		$flagged = $this->wp->add_query_arg( self::FLAG, '1', $this->without_fragment( $url ) );

		return $flagged . '#' . self::ANCHOR;
	}

	/**
	 * The URL up to its fragment.
	 *
	 * Remove the fragment before add_query_arg(), which otherwise preserves it.
	 *
	 * @since 0.5.2
	 *
	 * @param string $url URL that may carry a fragment.
	 * @return string
	 */
	private function without_fragment( string $url ): string {
		$hash = strpos( $url, '#' );

		return false === $hash ? $url : substr( $url, 0, $hash );
	}

	/**
	 * Whether pending visibility will show this reply to its author.
	 *
	 * Keep this duplicate check local: drift can only affect the notice, not disclose
	 * a reply.
	 */
	private function shown_back( int $reply_id ): bool {
		$author = $this->wp->get_post_author( $reply_id );

		return $this->wp->get_post_status( $reply_id ) === $this->wp->get_pending_status_id()
			&& $author > 0
			&& $author === $this->wp->get_current_user_id();
	}

	/**
	 * Echo the acknowledgement, if this request is carrying the flag.
	 *
	 * @since 0.5.0
	 */
	public function render(): void {
		if ( ! $this->wp->has_query_flag( self::FLAG ) ) {
			return;
		}

		/*
		 * The redirect targets this ID. A focusable fragment moves assistive technology
		 * to a status region that was already present when the page loaded.
		 */
		printf(
			'<p id="%1$s" class="bltn-compose__note bltn-compose__note--held" role="status" tabindex="-1">%2$s</p>',
			esc_attr( self::ANCHOR ),
			esc_html__( 'Your reply is awaiting review.', 'jtzls-bulletin-for-bbpress' )
		);
	}
}
