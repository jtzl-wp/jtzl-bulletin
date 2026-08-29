<?php
/**
 * The caller's own profile.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Rest;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * `GET /me` and `PATCH /me` — reading and editing your own profile.
 *
 * ## Whose profile is not a parameter
 *
 * Neither route carries an ID. The subject is `Rest\RequestBounds::caller()`, the
 * signed-in user, and there is nothing in the request for another member's ID to
 * arrive in. `Rest\ReplyController::may_edit()` has to establish authorship because a
 * reply is addressed by ID and might be anyone's; here there is nothing to establish.
 *
 * ⚠ **That is also why there is no `PATCH /users/{id}`, and why there should not be
 * one.** A route taking an ID would need a capability check, and the capability that
 * would satisfy it — `edit_users` — belongs to administrators, who already have
 * wp-admin. v1's editing is authorship, on a profile exactly as on a post: a moderator
 * has no route here either.
 *
 * ## Both routes need a real permission callback
 *
 * `Rest\ResponseFactory::authenticated()` is what turns a logged-out `/me` into
 * WordPress's own `rest_not_logged_in` 401. Without it the caller would resolve to 0,
 * `Rest\AccessPolicy::user()` would answer 404, and the app would be told the route does
 * not exist rather than that it needs to sign in — which is the difference between
 * "show the sign-in screen" and "this build is talking to the wrong server".
 *
 * ## What a write answers with
 *
 * The stored profile, from `Rest\ProfilePresenter` — the same collaborator `GET /me` and
 * `GET /users/{id}` answer through, so a PATCH response and the next read cannot come to
 * describe the same member differently. This follows the contract's rule for every other
 * edit: a `PATCH` returns 200 with the current entity, so the app never has to guess what
 * the server now holds.
 *
 * @since 0.6.1
 */
class ProfileController implements ControllerInterface {

	/**
	 * What a profile response is.
	 *
	 * @var ProfilePresenter
	 * @since 0.6.1
	 */
	private ProfilePresenter $profiles;

	/**
	 * Profile writes.
	 *
	 * @var ProfileEditService
	 * @since 0.6.1
	 */
	private ProfileEditService $edits;

	/**
	 * What a request may ask for.
	 *
	 * @var RequestBounds
	 * @since 0.6.1
	 */
	private RequestBounds $bounds;

	/**
	 * What comes back.
	 *
	 * @var ResponseFactory
	 * @since 0.6.1
	 */
	private ResponseFactory $responses;

	/**
	 * Constructor.
	 *
	 * @since 0.6.1
	 *
	 * @param ProfilePresenter   $profiles  What a profile response is.
	 * @param ProfileEditService $edits     Profile writes.
	 * @param RequestBounds      $bounds    What a request may ask for.
	 * @param ResponseFactory    $responses What comes back.
	 */
	public function __construct(
		ProfilePresenter $profiles,
		ProfileEditService $edits,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->profiles  = $profiles;
		$this->edits     = $edits;
		$this->bounds    = $bounds;
		$this->responses = $responses;
	}

	/**
	 * The routes this controller answers.
	 *
	 * @since 0.6.1
	 *
	 * @return array<string,array<mixed>>
	 */
	public function routes(): array {
		return array(
			'/me' => array(
				$this->bounds->authenticated_route(
					array( $this, 'get_item' ),
					array( $this->responses, 'authenticated' ),
					$this->bounds->avatar_args()
				),
				$this->bounds->authenticated_route(
					array( $this, 'update_item' ),
					array( $this->responses, 'authenticated' ),
					array(
						// ⚠ Not `required`. A PATCH tells omitted from sent, and the
						// "you named nothing editable" answer belongs to
						// Rest\ProfileEditService with the rest of the write policy —
						// not to a schema that would refuse it as a missing parameter
						// and hand the app a different error for the same mistake it
						// makes on a topic or a reply.
						'name' => $this->bounds->string_arg(),
					) + $this->bounds->avatar_args(),
					'PATCH'
				),
			),
		);
	}

	/**
	 * Your own profile.
	 *
	 * @since 0.6.1
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		return $this->profiles->present(
			$this->bounds->caller(),
			$this->bounds->avatar_size( $request )
		);
	}

	/**
	 * Edit your own profile.
	 *
	 * @since 0.6.1
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( \WP_REST_Request $request ) {
		$changes = array();

		// Presence, not truthiness: `has_param()` is what tells a field that was sent
		// empty from one that was never mentioned, and the two are different requests.
		if ( $request->has_param( 'name' ) ) {
			$sent = $request->get_param( 'name' );

			// ⚠ Never trust a type the schema was not given. A query string can carry
			// an array where a string is declared (`?name[]=x`), and casting one
			// produces the word "Array" — which would then be somebody's display name.
			$changes['name'] = is_scalar( $sent ) ? (string) $sent : '';
		}

		$caller  = $this->bounds->caller();
		$applied = $this->edits->update( $caller, $changes );

		if ( true !== $applied ) {
			return $applied;
		}

		return $this->profiles->present( $caller, $this->bounds->avatar_size( $request ) );
	}
}
