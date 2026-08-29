<?php
/**
 * Editing your own profile over the REST API.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Applies this API's profile-write policy, then stores the one field it accepts.
 *
 * ## One field, and the omissions are the design
 *
 * A member may change how their name reads. They may not change their password, their
 * email address, their login or their role here — not because those are hard, but
 * because each of them is an *account* operation and this is a forum API.
 *
 * ⚠ **WordPress core will already do all four**, at `POST /wp/v2/users/{id}` with the
 * caller's own ID, and it applies neither a current-password challenge nor an
 * email-confirmation round trip on the way. Measured on 2026-08-29: a bearer token
 * alone changed its account's password, locked the original password out, and the
 * token that did it kept working afterwards. With a stateless token that cannot be
 * revoked, that turns a seven-day token leak into permanent account loss.
 *
 * **Bulletin does not fix core's route and does not try to** — this plugin has no
 * business changing how WordPress's own API behaves on somebody's site. Closing that
 * door is a deployment decision, recorded with the other JWT launch requirements in
 * `docs/rest-api-plan.md` under Auth. What this class controls is the door *we* own,
 * and it is deliberately too narrow to walk a credential through: the seam method it
 * calls, `WordPress\RestContextInterface::update_display_name()`, cannot express a
 * password even if a future caller wanted it to.
 *
 * ## An edit has to change something
 *
 * A PATCH naming no editable field is `invalid_request` 400 rather than a no-op 200,
 * which is the answer `Rest\TopicEditService` and `Rest\ReplyEditService` already give.
 * An app that sent the wrong field name should hear about it once, not discover much
 * later that nothing has been saved for a week.
 *
 * @since 0.6.1
 */
class ProfileEditService {

	/**
	 * The longest display name this API will store.
	 *
	 * `wp_users.display_name` is `varchar(250)`, so a longer value is silently truncated
	 * by MySQL rather than refused — and the member is then shown a name they did not
	 * choose, with nothing having reported a problem. Refusing at the same number keeps
	 * the stored value and the sent value the same thing.
	 *
	 * ⚠ Counted in **characters, not bytes**, with `mb_strlen()`. The column is measured
	 * in characters too, so a name in Japanese gets the full 250 rather than a third of
	 * them. This is the same choice bbPress makes for a topic title.
	 *
	 * @since 0.6.1
	 */
	private const NAME_MAX_LENGTH = 250;

	/**
	 * REST-side WordPress seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.1
	 */
	private RestContextInterface $rest;

	/**
	 * Constructor.
	 *
	 * @since 0.6.1
	 *
	 * @param RestContextInterface $rest REST-side WordPress seam.
	 */
	public function __construct( RestContextInterface $rest ) {
		$this->rest = $rest;
	}

	/**
	 * Apply an edit to a member's own profile.
	 *
	 * @since 0.6.1
	 *
	 * @param int                  $user_id Member being edited — always the caller.
	 * @param array<string,string> $changes Fields the request actually carried.
	 * @return true|\WP_Error
	 */
	public function update( int $user_id, array $changes ) {
		if ( ! array_key_exists( 'name', $changes ) ) {
			return $this->refuse(
				'invalid_request',
				__( 'An edit has to change something.', 'jtzl-bulletin' ),
				400
			);
		}

		$name = trim( $changes['name'] );

		// ⚠ Emptiness is checked *after* the schema's `sanitize_text_field()` has run,
		// which is the only point where it means anything: a name sent as `<script>`
		// arrives here as the empty string, and a name of one space arrives as one
		// space. Both are refused, and neither reaches storage as a member with no
		// visible name at all.
		if ( '' === $name ) {
			return $this->refuse(
				'invalid_name',
				__( 'A display name cannot be empty.', 'jtzl-bulletin' ),
				400
			);
		}

		if ( mb_strlen( $name, 'utf-8' ) > self::NAME_MAX_LENGTH ) {
			return $this->refuse(
				'invalid_name',
				__( 'That display name is too long.', 'jtzl-bulletin' ),
				400
			);
		}

		if ( ! $this->rest->update_display_name( $user_id, $name ) ) {
			// Named no more precisely than the other write failures in this API: what
			// went wrong inside WordPress is not something the app can act on, and
			// saying so is how a probe learns which values the site treats specially.
			return $this->refuse(
				'write_failed',
				__( 'The profile could not be saved.', 'jtzl-bulletin' ),
				500
			);
		}

		return true;
	}

	/**
	 * One refusal, shaped the way every refusal in this API is shaped.
	 *
	 * @since 0.6.1
	 *
	 * @param string $code    Stable snake-case error code.
	 * @param string $message Plain-text message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private function refuse( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
