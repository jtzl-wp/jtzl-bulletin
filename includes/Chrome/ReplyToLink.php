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
 * ## What this class used to do, and why it stopped
 *
 * Until 0.5.0 it **withheld the link entirely**. The reasoning was sound at the time:
 * the destination is `{topic}/?bbp_reply_to={id}#new-post`, a single-topic URL, and
 * since issue #37 every single-topic URL is a takeover screen — which rendered no
 * reply form until the posting phase. Followed on the fixture, the destination
 * reported zero forms and zero textareas. A door to a room that was not built.
 *
 * P4 built the room. The link works, so withholding it would now be the defect.
 *
 * ## What it does instead, and why that is not nothing
 *
 * `bbp_get_reply_to_link()` emits `onclick="return addReply.moveForm( … )"` whenever
 * `bbp_thread_replies()` is on (`replies/template.php:1609`). `addReply` lives in
 * bbPress's `reply.js`, which `Asset\TakeoverScriptSuppressor` dequeues on every
 * takeover screen — deliberately, and it stays dequeued: `moveForm` exists to lift
 * the reply form out of the foot of the thread and up under the post being answered,
 * and the composer's resting place at the foot is a design JT approved.
 *
 * ⚠ **Left alone, the link would still reach the right page — by way of a thrown
 * ReferenceError.** `addReply` is undefined, the handler throws, the return value is
 * therefore not `false`, and the browser follows the `href`. Right destination, wrong
 * mechanism, and a console error on every tap. Stripping the attribute makes the
 * `href` the whole mechanism, which is what it already was in practice and what the
 * no-JS path has always done.
 *
 * `ScreenTier::None` and the reskin tier are both left exactly as they are: reskin
 * keeps bbPress's scripts, so `addReply` is defined there and `moveForm` is a working
 * control on a screen we only restyle.
 *
 * @since 0.3.0
 */
class ReplyToLink {

	/**
	 * The generated handler, exactly as `bbp_get_reply_to_link()` builds it.
	 *
	 * Anchored on the function name rather than on `onclick` generally: this class is
	 * removing one known handler whose script we suppressed, not sanitising markup.
	 * An `onclick` another plugin adds through the same filter is not ours to drop.
	 *
	 * @since 0.5.0
	 *
	 * @var string
	 */
	private const HANDLER = '/\s*onclick="return addReply\.moveForm\([^"]*\);?"/';

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
	 * @param ScreenClassifier $screen Screen-tier classifier.
	 */
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
