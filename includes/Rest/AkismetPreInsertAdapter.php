<?php
/**
 * The pre-insert filter chain, run without letting Akismet end the request.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Runs `bbp_new_topic_pre_insert` / `bbp_new_reply_pre_insert` and reports the one
 * outcome that has no return value: the write Akismet threw away.
 *
 * ## The branch this class exists for
 *
 * With `akismet_strictness` on, and Akismet certain enough to send back
 * `x-akismet-pro-tip: discard`, `BBP_Akismet::parse_response()` calls `bbp_redirect()` —
 * which calls `wp_safe_redirect()` and then `exit()`. On the website that is exactly
 * right: the spammer is bounced, nothing is stored, and no page is drawn. Over REST it
 * would send a 302 with no body and kill PHP mid-request, so the app would see a
 * redirect to an HTML page where it asked for JSON. **Measured, not reasoned about:**
 * driving that path in the test suite reaches `akismet.php:243` and dies on
 * `wp_safe_redirect()`.
 *
 * ## Where the interception goes, and why not one hook earlier
 *
 * At `bbp_bypass_spam_enforcement`, priority `PHP_INT_MAX` — bbPress's own decision
 * point, the last thing `parse_response()` asks before the strictness block. Two things
 * follow from choosing that hook rather than `bbp_akismet_check_post`:
 *
 * ⚠ **A moderator is still exempt.** `parse_response()` computes
 * `current_user_can( 'moderate', $parent_id )`, offers it to this filter, and returns
 * untouched when the answer is true — so a trusted member's post is never discarded,
 * however certain Akismet is. Intercepting earlier would mean recomputing that
 * capability here, and a second implementation of a rule bbPress already owns is a
 * second implementation to keep in step. This one reads the answer instead: when the
 * bypass is already true, it returns it unchanged and does nothing.
 *
 * ⚠ **It runs after every other extension's bypass filter**, so a forum that trusts a
 * class of member through this hook keeps trusting them from the app.
 *
 * ## Two repairs, both because the escape is an exception
 *
 * The sentinel is thrown from inside a running `apply_filters()`, several hooks deep.
 * That is the only way out that reaches the caller before `bbp_redirect()` reaches
 * `exit()`, and it costs two things that are put back here:
 *
 * 1. **`$wp_current_filter` is left holding every enclosing hook name**, because
 *    `apply_filters()` pops it only on a normal return. Left alone, `current_filter()`
 *    lies for the rest of the process and the stack grows on every occurrence — which
 *    in a test run is one class corrupting another. `unwind_filters()` truncates it back
 *    to the depth recorded before the call, on every path.
 * 2. **`bbpress()->errors` is polluted whether or not anything was discarded.** Akismet's
 *    `check_post()` calls `bbp_filter_anonymous_post_data()`, which adds two errors when
 *    the anonymous author fields are absent — and it *depends* on doing so, since it then
 *    reads `bbp_has_errors()` to decide the author is logged in. So the bag is swapped
 *    for an empty one around the call and handed straight back afterwards.
 *
 * ⚠ **The sentinel is compared by identity, not by class.** An extension somewhere in
 * the chain throwing its own `RuntimeException` is not this one, must not be read as a
 * discard, and is re-thrown untouched.
 *
 * ## What is deliberately left alone
 *
 * Every other Akismet outcome — ham, no response, a bypass, and spam under either
 * strictness setting — goes through bbPress's own filter unchanged. In particular
 * `akismet_strictness` is never forced off: turning it off would convert a discard into
 * a persisted spam row, which stores the very content Akismet said to throw away and
 * fires `bbp_akismet_spam_caught` for a post the site was told not to keep.
 *
 * @since 0.6.0
 */
class AkismetPreInsertAdapter {

	/**
	 * The "should spam enforcement be skipped for this reader" question bbPress asks.
	 *
	 * @var string
	 * @since 0.6.0
	 */
	private const BYPASS_HOOK = 'bbp_bypass_spam_enforcement';

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * REST-side WordPress/bbPress seam.
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
	 * @param ContextInterface     $wp   WordPress/bbPress seam.
	 * @param RestContextInterface $rest REST-side WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Run the pre-insert chain over this post data.
	 *
	 * @since 0.6.0
	 *
	 * @param string              $pre_insert_hook Filter name for this lifecycle.
	 * @param array<string,mixed> $post_data       Post data about to be inserted.
	 * @return array<string,mixed>|null The filtered data, or null when it was discarded.
	 *                                 ⚠ A chain that hands back something that is not an
	 *                                 array is read as a discard too. It is a broken
	 *                                 extension either way, and storing whatever it
	 *                                 returned is the worse of the two answers.
	 * @throws \RuntimeException Re-thrown untouched when it is not this class's sentinel;
	 *                          an extension failing inside the chain is not a discard.
	 */
	public function apply( string $pre_insert_hook, array $post_data ): ?array {
		$sentinel = new \RuntimeException( 'akismet_discard' );
		$guard    = function ( $bypass, $filtered ) use ( $sentinel ) {
			if ( true === $bypass || ! $this->discarding( $filtered ) ) {
				return $bypass;
			}

			throw $sentinel;
		};

		$depth    = $this->rest->current_filter_depth();
		$errors   = $this->rest->swap_bbp_errors( new \WP_Error() );
		$filtered = null;

		$this->wp->add_filter( self::BYPASS_HOOK, $guard, PHP_INT_MAX, 2 );

		try {
			$filtered = $this->wp->apply_filters( $pre_insert_hook, $post_data );
		} catch ( \RuntimeException $thrown ) {
			if ( $thrown !== $sentinel ) {
				throw $thrown;
			}

			$filtered = null;
		} finally {
			$this->wp->remove_filter_callback( self::BYPASS_HOOK, $guard, PHP_INT_MAX );
			$this->rest->swap_bbp_errors( $errors );
			$this->rest->unwind_filters( $depth );
		}

		return is_array( $filtered ) ? $filtered : null;
	}

	/**
	 * Whether this is the outcome bbPress would have redirected out of.
	 *
	 * Both halves of bbPress's own test, in its order: the site setting first, then the
	 * header Akismet sent back. Neither is inferred from the other — a spam verdict
	 * without the pro tip is ordinary spam, and the pro tip with strictness off is
	 * ordinary spam too.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $filtered Post data as the chain has it so far.
	 * @return bool
	 */
	private function discarding( $filtered ): bool {
		if ( ! is_array( $filtered ) || ! $this->rest->is_akismet_strict() ) {
			return false;
		}

		$headers = $filtered['bbp_akismet_result_headers'] ?? array();

		return is_array( $headers ) && 'discard' === ( $headers['x-akismet-pro-tip'] ?? '' );
	}
}
