<?php
/**
 * The bounds a request may ask for.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Page, page size and avatar size — read from a request, brought inside their limits,
 * and declared as route arguments.
 *
 * ## Bounds clamp; they do not reject
 *
 * `per_page` is 1–100 and `avatar_size` is 1–512, and a request outside those bounds
 * is served rather than refused. That is a deliberate contract choice — a mobile
 * client guessing at a page size should receive a page, not an error it has to learn
 * to parse — and it dictates something non-obvious about the schemas below: they carry
 * no `minimum`/`maximum` for those two values. WordPress validates schema bounds
 * *before* the callback runs, so declaring them would turn the documented clamp into a
 * 400, and nothing in a controller could undo it.
 *
 * `page` is different. An unusable page number is a mistake worth reporting, so it
 * keeps `minimum` and is validated; the accessor still clamps, as the floor beneath a
 * schema some future route forgets to declare.
 *
 * `caller()` and `id()` sit beside the paging accessors because they answer the same
 * kind of question — what values does this callback act on. For
 * `/topics/{id}/favorite` the topic comes out of the path and the member out of the
 * session, and both are things the request carries.
 *
 * ⚠ **And a declared bound only exists if a `validate_callback` does** — see
 * `integer_arg()`. `register_rest_route()` supplies none, and
 * `WP_REST_Request::has_valid_params()` skips any argument without one, so a schema
 * that names `minimum` and stops there is decoration. That is why every argument here
 * is built through one method rather than written out where it is used.
 *
 * @since 0.6.0
 */
class RequestBounds {

	/**
	 * Default rows per page.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const PER_PAGE_DEFAULT = 10;

	/**
	 * Largest page a client may ask for.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const PER_PAGE_MAX = 100;

	/**
	 * Default avatar edge, in pixels.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const AVATAR_DEFAULT = 96;

	/**
	 * Largest avatar edge, in pixels.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const AVATAR_MAX = 512;

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface $wp Seam, for the one question a request does not carry.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * The resource a route was addressed to.
	 *
	 * ⚠ **`get_url_params()`, never `get_param()`**, which resolves body, then **query
	 * string**, then path (`WP_REST_Request::get_parameter_order()`): `get_param( 'id' )`
	 * on `/users/9/topics?id=7` answers **7**. Untidy on a public read route; on
	 * `/me/favorites?id=7` it would be somebody else's private list.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function id( \WP_REST_Request $request ): int {
		return (int) ( $request->get_url_params()['id'] ?? 0 );
	}

	/**
	 * Whoever is signed in, or 0. Read at the point of use, never held.
	 *
	 * @since 0.6.0
	 *
	 * @return int
	 */
	public function caller(): int {
		return $this->wp->get_current_user_id();
	}

	/**
	 * The member a request is about: the ID in the path, or — on `/me`, whose path has
	 * none — whoever is asking. That is the whole of what `/me` is.
	 *
	 * ⚠ Presence, not truthiness: `/users/0` matches `[\d]+` and must stay the 404 it
	 * is, where a `?:` would serve the caller their own profile.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function member( \WP_REST_Request $request ): int {
		$path = $request->get_url_params();

		return array_key_exists( 'id', $path ) ? (int) $path['id'] : $this->caller();
	}

	/**
	 * The page a request asks for.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function page( \WP_REST_Request $request ): int {
		return max( 1, (int) $request->get_param( 'page' ) );
	}

	/**
	 * How many rows a request asks for, within bounds.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function per_page( \WP_REST_Request $request ): int {
		return $this->clamp( $request->get_param( 'per_page' ), self::PER_PAGE_DEFAULT, 1, self::PER_PAGE_MAX );
	}

	/**
	 * What size avatar a request asks for, within bounds.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function avatar_size( \WP_REST_Request $request ): int {
		return $this->clamp( $request->get_param( 'avatar_size' ), self::AVATAR_DEFAULT, 1, self::AVATAR_MAX );
	}

	/**
	 * The search terms a request asks for, with the whitespace taken off.
	 *
	 * ⚠ Trimmed here rather than compared here. A whitespace-only `q` is refused by
	 * the route that reads it, because `bbp_has_search_results()` does not survive
	 * being asked to search for nothing — see Query\SearchQuery::is_runnable() — and
	 * this is where the two spellings of "nothing" become one.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	public function terms( \WP_REST_Request $request ): string {
		$raw = $request->get_param( 'q' );

		// Scalar-checked rather than cast: WordPress accepts an array for a query
		// parameter (`?q[]=x`), and casting one would search for the word "Array".
		return is_scalar( $raw ) ? trim( (string) $raw ) : '';
	}

	/**
	 * Route arguments every collection accepts.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function collection_args(): array {
		return array(
			'page'     => $this->integer_arg(
				array(
					'default' => 1,
					'minimum' => 1,
				)
			),
			'per_page' => $this->integer_arg(
				array(
					'default'           => self::PER_PAGE_DEFAULT,
					'sanitize_callback' => fn( $value ): int => $this->clamp( $value, self::PER_PAGE_DEFAULT, 1, self::PER_PAGE_MAX ),
				)
			),
		) + $this->avatar_args();
	}

	/**
	 * An integer route argument, declared so WordPress will actually check it.
	 *
	 * ⚠ **`register_rest_route()` adds no `validate_callback` for you.** WordPress
	 * fills one in only through `rest_get_endpoint_args_for_schema()`, which is a
	 * `WP_REST_Controller` convenience and never runs for hand-written route args —
	 * and `WP_REST_Request::has_valid_params()` skips any argument that has not got
	 * one. So a schema declaring `type` and `minimum` and nothing else is *inert*: a
	 * `page` of 0 or `abc` reaches the callback exactly as 5 does. Every integer
	 * argument in this plugin is built here so that cannot be declared by accident.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $overrides Argument-specific keys; they win.
	 * @return array<string,mixed>
	 */
	public function integer_arg( array $overrides = array() ): array {
		return $overrides + array(
			'type'              => 'integer',
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * A string route argument, declared so WordPress will actually check it.
	 *
	 * The sibling of `integer_arg()` and it exists for the same reason: without a
	 * `validate_callback` the schema is decoration, and `required` in particular does
	 * nothing at all. With one, an absent argument is refused as
	 * `rest_missing_callback_param` before any callback runs — which is what keeps a
	 * terms-less search from ever reaching `bbp_has_search_results()`.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $overrides Argument-specific keys; they win.
	 * @return array<string,mixed>
	 */
	public function string_arg( array $overrides = array() ): array {
		return $overrides + array(
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * A read route, declared the one way every read route in this API is declared.
	 *
	 * Two invariants live here rather than at each of a dozen call sites, because both
	 * of them are decisions that would be invisible if one route quietly differed:
	 *
	 * - **`READABLE`.** Nothing in v1's read surface answers anything else, and a
	 *   controller that declared `ALLMETHODS` by accident would take POST traffic Task
	 *   8's routes are supposed to own.
	 * - **`__return_true`.** Bulletin's forums are public-read, and *what a reader may
	 *   have* is a per-row answer no permission callback can give: Rest\AccessPolicy
	 *   answers it for a singular route, and Rest\CollectionVisibility answers it inside
	 *   the query for a collection, before `found_posts`, so the total describes the
	 *   rows. A route that reached for a capability check here would be adding a second,
	 *   coarser opinion beside the real one.
	 *
	 * @since 0.6.0
	 *
	 * @param array{0:object,1:string}          $callback Controller method to answer with.
	 * @param array<string,array<string,mixed>> $args     Argument schema.
	 * @return array<string,mixed>
	 */
	public function readable_route( array $callback, array $args = array() ): array {
		return array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => $callback,
			'permission_callback' => '__return_true',
			'args'                => $args,
		);
	}

	/**
	 * A route only a signed-in member may reach.
	 *
	 * The counterpart of `readable_route()`, differing in the two places that method's
	 * docblock calls decisions. **`methods` defaults to `READABLE`** — a favourite is
	 * `PUT` to set and `DELETE` to clear on one endpoint rather than two, so the
	 * handler reads the verb (see Rest\TopicController). **The permission callback is
	 * real** — Rest\ResponseFactory::authenticated(), which answers only *whether there
	 * is a member*; whether they may touch this thing is Rest\AccessPolicy's, asked
	 * inside the handler.
	 *
	 * @since 0.6.0
	 *
	 * @param array{0:object,1:string}          $callback   Controller method to answer with.
	 * @param array{0:object,1:string}          $permission Permission callback.
	 * @param array<string,array<string,mixed>> $args       Argument schema.
	 * @param string                            $methods    HTTP verbs, comma separated.
	 * @return array<string,mixed>
	 */
	public function authenticated_route(
		array $callback,
		array $permission,
		array $args = array(),
		string $methods = \WP_REST_Server::READABLE
	): array {
		return array(
			'methods'             => $methods,
			'callback'            => $callback,
			'permission_callback' => $permission,
			'args'                => $args,
		);
	}

	/**
	 * The avatar-size argument, accepted by every route that serializes a person.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function avatar_args(): array {
		return array(
			'avatar_size' => $this->integer_arg(
				array(
					'default'           => self::AVATAR_DEFAULT,
					'sanitize_callback' => fn( $value ): int => $this->clamp( $value, self::AVATAR_DEFAULT, 1, self::AVATAR_MAX ),
				)
			),
		);
	}

	/**
	 * A requested number brought inside its bounds, or the default when absent.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $value    Whatever the request carried.
	 * @param int   $fallback Value for an absent parameter.
	 * @param int   $min      Lowest allowed.
	 * @param int   $max      Highest allowed.
	 * @return int
	 */
	private function clamp( $value, int $fallback, int $min, int $max ): int {
		if ( null === $value || '' === $value ) {
			return $fallback;
		}

		return max( $min, min( $max, (int) $value ) );
	}
}
