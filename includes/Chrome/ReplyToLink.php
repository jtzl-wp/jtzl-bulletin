<?php
/**
 * The per-reply "Reply To" link, on the takeover tier.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Chrome;

use JTZL\Bulletin\Screen\ScreenClassifier;
use JTZL\Bulletin\Screen\ScreenTier;

/**
 * Takes bbPress's inline `onclick` off the link and leaves the `href` to do the work.
 *
 * @since 0.3.0
 */
class ReplyToLink {

	private const HANDLER = '/\s*onclick="return addReply\.moveForm\([^"]*\);?"/';

	private ScreenClassifier $screen;

	public function __construct( ScreenClassifier $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Strip the handler on takeover screens; leave every other tier untouched.
	 *
	 * @since 0.3.0
	 * @since 0.5.0 Strips the inline handler instead of withholding the link.
	 *
	 * @param mixed $link The link markup bbPress assembled.
	 * @return mixed
	 */
	public function filter_reply_to_link( $link ) {
		if ( ScreenTier::Takeover !== $this->screen->tier() || ! is_string( $link ) ) {
			return $link;
		}

		return (string) preg_replace( self::HANDLER, '', $link );
	}
}
