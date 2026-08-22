<?php
/**
 * The compatibility window a form-shaped hook runs inside.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Runs one `*_pre_extras` action the way its listeners expect, and leaves nothing
 * behind.
 *
 * ## What this exists to make possible
 *
 * `bbp_new_topic_pre_extras` and `bbp_new_reply_pre_extras` are bbPress's "last chance
 * to refuse this" hooks, and a forum that has an extension refusing writes on the
 * website expects it to refuse them from the app too. But those listeners were written
 * against a browser POST: they read `$_POST['bbp_topic_title']`, they call
 * `bbp_add_error()`, and they assume the request they are inspecting is the one PHP put
 * in the superglobals.
 *
 * A REST request has none of that. So this class builds a *window* in which those
 * assumptions hold — sanitized, form-equivalent values in `$_POST` and `$_REQUEST`, a
 * fresh error bag on the bbPress singleton — runs the action, reads what was added, and
 * puts every one of them back.
 *
 * ## What is deliberately not emulated
 *
 * ⚠ **Only the fields the write itself supplies.** No nonce, no `_wp_http_referer`, no
 * `bbp_topic_status`, no anonymous-author fields, and never the REST request array
 * itself. A listener that reads something outside that set sees nothing, and that is
 * the compatibility boundary: an extension depending on screen state — the queried
 * object, a referer, a form the app never rendered — is not silently given a fake one.
 * It gets an empty value, which is the honest answer, rather than a plausible one that
 * would make it refuse or allow for a reason nobody could reconstruct.
 *
 * ⚠ **The error bag is swapped, not cleared.** Whatever was in `bbpress()->errors`
 * before is taken away for the duration and handed back afterwards. Two consequences,
 * both wanted: a listener cannot see complaints that belong to somebody else's request,
 * and a complaint it *does* add cannot outlive the call. The second matters more than it
 * looks — the bbPress singleton outlives a PHPUnit rollback, so a leaked error bag is a
 * cross-test failure waiting for a reason.
 *
 * ## Restoration is unconditional
 *
 * Every swap is undone in `finally`, so a listener that throws restores the globals on
 * its way past. The exception itself is not caught: a fatal in an extension is not this
 * class's to convert into a 400, and swallowing it would report a write as refused when
 * what actually happened is that somebody's plugin is broken.
 *
 * @since 0.6.0
 */
class BbpHookScope {

	/**
	 * Errors an extension may raise that Bulletin has a specific answer for.
	 *
	 * The keys are the codes bbPress's own handlers use, because an extension writing a
	 * refusal against this lifecycle writes it in bbPress's vocabulary. Anything else is
	 * still honoured — it becomes a generic refusal carrying the extension's own words —
	 * but these get the status the contract already promises for that kind of failure,
	 * so an app's error handling does not have to special-case which plugin refused.
	 *
	 * @var array<string,array{0:string,1:int}>
	 * @since 0.6.0
	 */
	private const KNOWN = array(
		'bbp_topic_title'              => array( 'invalid_title', 400 ),
		'bbp_reply_title'              => array( 'invalid_title', 400 ),
		'bbp_topic_content'            => array( 'invalid_content', 400 ),
		'bbp_reply_content'            => array( 'invalid_content', 400 ),
		'bbp_topic_tags'               => array( 'invalid_tags', 400 ),
		'bbp_reply_tags'               => array( 'invalid_tags', 400 ),
		'bbp_topic_moderation'         => array( 'moderation_rejected', 400 ),
		'bbp_reply_moderation'         => array( 'moderation_rejected', 400 ),
		'bbp_topic_permission'         => array( 'forbidden', 403 ),
		'bbp_reply_permission'         => array( 'forbidden', 403 ),
		'bbp_new_topic_forum_closed'   => array( 'forbidden', 403 ),
		'bbp_new_reply_forum_closed'   => array( 'forbidden', 403 ),
		'bbp_new_topic_forum_category' => array( 'forbidden', 403 ),
		'bbp_new_reply_forum_category' => array( 'forbidden', 403 ),
		'bbp_reply_topic_closed'       => array( 'forbidden', 403 ),
		'bbp_topic_forum_id'           => array( 'not_found', 404 ),
		'bbp_reply_topic_id'           => array( 'not_found', 404 ),
		'bbp_reply_forum_id'           => array( 'not_found', 404 ),
		'bbp_new_topic_forum_private'  => array( 'not_found', 404 ),
		'bbp_new_topic_forum_hidden'   => array( 'not_found', 404 ),
		'bbp_new_reply_forum_private'  => array( 'not_found', 404 ),
		'bbp_new_reply_forum_hidden'   => array( 'not_found', 404 ),
		'bbp_topic_duplicate'          => array( 'duplicate_post', 409 ),
		'bbp_reply_duplicate'          => array( 'duplicate_post', 409 ),
		'bbp_topic_flood'              => array( 'rate_limited', 429 ),
		'bbp_reply_flood'              => array( 'rate_limited', 429 ),
	);

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param RestContextInterface $rest WordPress/bbPress seam.
	 */
	public function __construct( RestContextInterface $rest ) {
		$this->rest = $rest;
	}

	/**
	 * Run one pre-save action inside the window, and report what it said.
	 *
	 * @since 0.6.0
	 *
	 * @param string               $hook        Action name.
	 * @param int[]                $hook_args   Positional arguments bbPress passes it.
	 * @param array<string,scalar> $form_values Sanitized form-equivalent values.
	 * @return true|\WP_Error True when nothing objected.
	 */
	public function run( string $hook, array $hook_args, array $form_values ) {
		$raised   = new \WP_Error();
		$globals  = $this->rest->swap_request_globals( $form_values );
		$previous = $this->rest->swap_bbp_errors( $raised );

		try {
			$this->rest->fire_pre_extras( $hook, $hook_args );
		} finally {
			$this->rest->swap_bbp_errors( $previous );
			$this->rest->restore_request_globals( $globals );
		}

		return $this->translate( $raised );
	}

	/**
	 * What a listener added, as an answer the contract already has a place for.
	 *
	 * Only the first complaint is reported. bbPress collects every one because a form
	 * redraws with all of them listed at once; a JSON error envelope has one `code`, and
	 * inventing an array of them here would be a shape no other route in the API
	 * returns.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_Error $raised What the listener added, if anything.
	 * @return true|\WP_Error
	 */
	private function translate( \WP_Error $raised ) {
		$code = (string) $raised->get_error_code();

		if ( '' === $code ) {
			return true;
		}

		$message = $this->rest->strip_markup( (string) $raised->get_error_message( $code ) );

		if ( isset( self::KNOWN[ $code ] ) ) {
			list( $rest_code, $status ) = self::KNOWN[ $code ];

			return new \WP_Error( $rest_code, $message, array( 'status' => $status ) );
		}

		// An extension nobody here has heard of. Its words are worth carrying — they are
		// what tells the member why — but its code is not, because an app cannot branch
		// on a code it has never been told about, and a plugin-specific one leaking into
		// the contract would make it one.
		return new \WP_Error(
			'write_rejected',
			'' === $message ? __( 'This post was rejected.', 'jtzl-bulletin' ) : $message,
			array( 'status' => 400 )
		);
	}
}
