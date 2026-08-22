<?php
/**
 * The fields a write carries.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * How a create or an edit declares its body, and how it reads it back.
 *
 * ## The sibling of Rest\RequestBounds, and not part of it
 *
 * That class is about the shape of a *request* — which page, how large an avatar, whose
 * ID is in the path — and every route in the API asks it something. This one is about
 * the shape of a *post*: a title, a body, tags, the reply being answered. Only the four
 * write routes ask it anything.
 *
 * These methods were written into `Rest\RequestBounds` first, and PHPMD is what said no:
 * seven more took it to 452 lines and 22 methods, over both the size and the method-count
 * gates. The split it forced turned out to be the right one, which is why it is here
 * rather than suppressed there.
 *
 * Both halves follow the same rule, and it is the load-bearing one: **a schema without a
 * `validate_callback` is inert**. WordPress fills one in only through
 * `rest_get_endpoint_args_for_schema()`, which never runs for hand-written route
 * arguments, and `WP_REST_Request::has_valid_params()` skips any argument that has not
 * got one. So `required` would not be required, and `items` would not be checked.
 *
 * ⚠ **A reader here never trusts a type it was not given.** Every one checks
 * `is_scalar()` or `is_array()` before casting, because a query string can carry an
 * array where a string is declared (`?content[]=x`) and casting one produces the word
 * "Array" — which would then be stored as somebody's reply.
 *
 * @since 0.6.0
 */
class WriteFields {

	/**
	 * The post body a write carries, exactly as it was sent.
	 *
	 * ⚠ **Not `string_arg()`.** That one sanitizes with `sanitize_text_field()`, which
	 * is right for a title and destructive for a body: it strips every tag and collapses
	 * newlines, so a reply with a paragraph break or a `<code>` block would reach the
	 * database as one flat line. Post content is cleaned by bbPress's own
	 * `bbp_new_*_pre_content` filter chain — `bbp_encode_bad`, kses, `balanceTags` — and
	 * that chain has to see what the member actually wrote. Leaving the callback as
	 * WordPress's own schema sanitizer casts to string and does nothing else.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,mixed>
	 */
	public function content_arg(): array {
		return array(
			'type'              => 'string',
			'required'          => true,
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'rest_sanitize_request_arg',
		);
	}

	/**
	 * The tag names a topic write carries.
	 *
	 * Names, not IDs — bbPress's own form takes names and creates the ones that do not
	 * exist, and an ID-only API would let a member tag from the browser and never from
	 * the app. What the names then have to satisfy is Rest\ContentGuard's.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,mixed>
	 */
	public function tags_arg(): array {
		return array(
			'type'              => 'array',
			'default'           => array(),
			'items'             => array( 'type' => 'string' ),
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'rest_sanitize_request_arg',
		);
	}

	/**
	 * The reply a new reply answers, declared nullable so it reads back as it writes.
	 *
	 * A Reply entity carries `reply_to: null` when it answers the thread itself, and an
	 * app echoing that value straight back into a write should not be refused for it.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,mixed>
	 */
	public function reply_to_arg(): array {
		return array(
			'type'              => array( 'integer', 'null' ),
			'default'           => null,
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'rest_sanitize_request_arg',
		);
	}

	/**
	 * The body an *edit* may carry: the same field, neither required nor defaulted.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,mixed>
	 */
	public function patch_content_arg(): array {
		$arg = $this->content_arg();

		unset( $arg['required'] );

		return $arg;
	}

	/**
	 * The tag names an *edit* may carry: the same field, without the default.
	 *
	 * ⚠ **Dropping the default is the whole point, and it is not cosmetic.**
	 * `WP_REST_Request::get_parameter_order()` puts `defaults` last in the list
	 * `has_param()` walks, so an argument declared with `'default' => array()` reports as
	 * *present* on a request that never mentioned it. A PATCH tells omitted apart from
	 * sent by exactly that call — with the default in place, every edit would look like a
	 * request to clear the thread's tags. Measured against WordPress's own
	 * `class-wp-rest-request.php`, not assumed.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,mixed>
	 */
	public function patch_tags_arg(): array {
		$arg = $this->tags_arg();

		unset( $arg['default'] );

		return $arg;
	}

	/**
	 * The fields an edit actually asked to change, and only those.
	 *
	 * An absent key and a key sent empty are different requests — `tags: []` clears a
	 * thread's tags, omitting `tags` leaves them alone — so presence is read from
	 * `has_param()` and the value from the same type-checked readers a create uses.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string[]         $names   Field names this route accepts.
	 * @return array<string,mixed> Present fields, keyed by name.
	 */
	public function changes( \WP_REST_Request $request, array $names ): array {
		$changes = array();

		foreach ( $names as $name ) {
			if ( ! $request->has_param( $name ) ) {
				continue;
			}

			$changes[ $name ] = match ( $name ) {
				'tags'  => $this->tags( $request ),
				'title' => $this->title( $request ),
				default => $this->content( $request ),
			};
		}

		return $changes;
	}

	/**
	 * The tag names a request carried, as a list of strings.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string[]
	 */
	public function tags( \WP_REST_Request $request ): array {
		$raw = $request->get_param( 'tags' );

		return is_array( $raw ) ? array_values( array_map( 'strval', array_filter( $raw, 'is_scalar' ) ) ) : array();
	}

	/**
	 * The reply a request asked to answer, or 0 for the thread itself.
	 *
	 * ⚠ A negative becomes 0 rather than a refusal, which is `bbp_validate_reply_to()`'s
	 * own coercion: a pointer that cannot name a reply means the reply answers its
	 * thread. A *positive* pointer is a different matter and is checked against the
	 * thread by Rest\AccessPolicy::reply_to().
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public function reply_to( \WP_REST_Request $request ): int {
		$raw = $request->get_param( 'reply_to' );

		return is_numeric( $raw ) ? max( 0, (int) $raw ) : 0;
	}

	/**
	 * The body a write carries, exactly as it was sent.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	public function content( \WP_REST_Request $request ): string {
		$raw = $request->get_param( 'content' );

		return is_scalar( $raw ) ? (string) $raw : '';
	}

	/**
	 * The title a write carries, already sanitized by its own schema.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	public function title( \WP_REST_Request $request ): string {
		$raw = $request->get_param( 'title' );

		return is_scalar( $raw ) ? trim( (string) $raw ) : '';
	}
}
