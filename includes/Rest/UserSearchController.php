<?php
/**
 * Finding a member by name.
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
 * `GET /users?q=` — resolving a display name or slug to a member.
 *
 * ## The first read here that is gated for a reason other than "it is yours"
 *
 * `/me` and Rest\PersonalController's three collections already require a token, but
 * they have no answer without one — the subject *is* the caller. Every read about
 * somebody else answers anonymously, because the forum is public-read and the app shows
 * threads to a visitor who has not signed in. This one is about other people and asks
 * for a bearer token anyway, and the asymmetry is deliberate: its value is convenience
 * for a member composing a mention, and its cost is a fast way to sweep the member list.
 * Requiring an account does not stop a determined sweep — anyone can register where
 * registration is open — but it puts a name against one, which is the difference between
 * a log that can be read and a log that says nothing.
 *
 * ⚠ **It is not, however, the thing standing between a stranger and the member list.**
 * `GET /users/{id}` is public and `Rest\AccessPolicy::user()` refuses only IDs nobody
 * holds, so walking the ID space already yields every account's name, avatar,
 * registration date and profile link — and the link contains the slug. Measured for
 * #143 before this route was written, and it is why the reach question turned out to be
 * a smaller decision than it looked: search makes the list *queryable*, not visible.
 * Closing the walk means gating a shipped route, which is a breaking change owed to the
 * app team rather than something to slip in beside a new one.
 *
 * ## Two characters, and why the bound is here rather than in the schema
 *
 * A one-character term matches a large fraction of any member list, so it is a listing
 * call wearing a search's clothes — and with a leading wildcard it is a full table scan
 * to build one. Refused.
 *
 * ⚠ **The minimum is checked in the callback and named nowhere in the argument
 * schema**, which is the same choice `Rest\TopicRepliesController` makes about `around`
 * and for the same reason: a schema bound is enforced before any callback runs, so
 * `q=a` would come back as WordPress's own `rest_invalid_param` while a term that is
 * only long enough before trimming came back as this one. One mistake, two refusals,
 * and the pair tells the caller something about the shape of the rule. Checked here,
 * there is a single answer — and it is spelled in WordPress's own vocabulary rather
 * than a Bulletin code, so an app that already handles an invalid parameter handles
 * this without learning anything new.
 *
 * ⚠ **Characters, not bytes.** `mb_strlen()` is what makes a two-character CJK name
 * searchable; `strlen()` would count it as six and let a *one*-character term through.
 * A single CJK character is still refused, which is a real limit on a forum written in
 * one — recorded in the contract rather than worked around here, because the fix is a
 * per-script minimum and nobody has that problem yet.
 *
 * @since 0.6.1
 */
class UserSearchController implements ControllerInterface {

	/**
	 * The shortest term that is a search rather than a listing.
	 *
	 * @var int
	 * @since 0.6.1
	 */
	public const MIN_TERM_LENGTH = 2;

	/**
	 * Matching members.
	 *
	 * @var UserSearchRepository
	 * @since 0.6.1
	 */
	private UserSearchRepository $members;

	/**
	 * Result rows.
	 *
	 * @var UserSerializer
	 * @since 0.6.1
	 */
	private UserSerializer $users;

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
	 * @param UserSearchRepository $members   Matching members.
	 * @param UserSerializer       $users     Result rows.
	 * @param RequestBounds        $bounds    What a request may ask for.
	 * @param ResponseFactory      $responses What comes back.
	 */
	public function __construct(
		UserSearchRepository $members,
		UserSerializer $users,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->members   = $members;
		$this->users     = $users;
		$this->bounds    = $bounds;
		$this->responses = $responses;
	}

	/**
	 * The routes this controller answers.
	 *
	 * ⚠ **`/users` and not `/users/search`.** The collection and the record it holds
	 * are the same resource at two depths, exactly as `/forums` sits above
	 * `/forums/{id}` — and a sibling path would have made the member list look like a
	 * different thing from a member. `q` rather than the `search` the issue sketched,
	 * because `GET /search?q=` already exists and one search parameter across the API
	 * is worth more than agreement with a filed issue.
	 *
	 * @since 0.6.1
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
		return array(
			'/users' => $this->bounds->authenticated_route(
				array( $this, 'get_collection' ),
				array( $this->responses, 'authenticated' ),
				$this->bounds->collection_args() + array(
					'q' => $this->bounds->string_arg( array( 'required' => true ) ),
				)
			),
		);
	}

	/**
	 * One page of matching members.
	 *
	 * @since 0.6.1
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_collection( \WP_REST_Request $request ) {
		$terms = $this->bounds->terms( $request );

		if ( mb_strlen( $terms ) < self::MIN_TERM_LENGTH ) {
			return $this->short_term();
		}

		$page = $this->members->search(
			$terms,
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->users->summaries( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}

	/**
	 * The refusal for a term too short to be a search.
	 *
	 * Built in WordPress's own shape — code, status and a `params` map of name to
	 * message — so it arrives as the failure an app's parameter handling already knows
	 * how to read.
	 *
	 * @since 0.6.1
	 *
	 * @return \WP_Error
	 */
	private function short_term(): \WP_Error {
		return new \WP_Error(
			'rest_invalid_param',
			/* translators: %s: name of the invalid request parameter. */
			sprintf( __( 'Invalid parameter(s): %s', 'jtzl-bulletin' ), 'q' ),
			array(
				'status' => 400,
				'params' => array(
					'q' => sprintf(
						/* translators: %d: minimum number of characters a search term must have. */
						__( 'A search term must be at least %d characters.', 'jtzl-bulletin' ),
						self::MIN_TERM_LENGTH
					),
				),
			)
		);
	}
}
