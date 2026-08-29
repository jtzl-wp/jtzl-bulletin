<?php
/**
 * The arguments a member search runs with.
 *
 * @package JTZL\Bulletin
 * @since 0.6.1
 */

namespace JTZL\Bulletin\Query;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * What `GET /users?q=` asks the user table — and, the part that matters, what it
 * refuses to ask it.
 *
 * ## The columns are the security boundary, not a tuning choice
 *
 * `WP_User_Query` picks its own search columns when none are named, and it picks them
 * from the *shape of the search string*: a term containing `@` searches `user_email`
 * **and nothing else**, and the general default set is `user_login`, `user_url`,
 * `user_email`, `user_nicename`, `display_name` (`class-wp-user-query.php:713–727`).
 * Left to itself this route would answer "is there an account on this address?" for
 * anybody able to log in — an oracle nobody asked for, reachable by typing an email
 * address into a name box.
 *
 * So the columns are named, and named narrowly: a display name, and the slug that
 * display name is reached by.
 *
 * ⚠ **Naming them is not enough on its own.** `WP_User_Query` runs
 * `apply_filters( 'user_search_columns', … )` *after* it has read the argument
 * (`:741`), so any other plugin on the site can put `user_email` back — with no error
 * and no sign in the response, since a matching row looks exactly like a name match.
 * What actually holds the boundary is WordPress\RestContext::user_query(), which pins
 * the filter's result back to what these arguments declared. That is why the list is a
 * constant here rather than a literal inside the array: declared once, enforced
 * downstream, and asserted from both ends — Tests\Query\UserSearchQueryTest on the
 * argument, Tests\Integration\RestUserSearchTest against a real hostile filter.
 *
 * ## Why `user_nicename` is both searched and returned
 *
 * bbPress resolves an `@mention` with `get_user_by( 'slug', … )` against a
 * `[0-9a-zA-Z\-_]+` capture (`common/formatting.php:479, 502`) — the nicename, never
 * the display name. A member search that could not return a slug would be unable to
 * compose a mention bbPress would linkify, and mention autocomplete is the one
 * consumer this route was filed for (#143). It follows that a display name containing
 * a space cannot be mentioned at all under bbPress's own scheme; that is bbPress's
 * limit, and the app needs the slug to work within it.
 *
 * ⚠ **On a default install the slug *is* the login name.** `wp_insert_user` falls back
 * to `sanitize_title( mb_substr( $user_login, 0, 50 ) )` when none is supplied
 * (`user.php:2349, 2352`). Publishing it is deliberate rather than an oversight — the
 * User entity's `link` has carried it since the API was written, because
 * `bbp_get_user_profile_url()` is `…/forums/users/{user_nicename}/` — but it is why
 * the site owes this API a rate limit on `/wp-json/jwt-auth/v1/token`, which the
 * contract's Auth section names as a launch requirement.
 *
 * ## A contains match, and an order that can be paged
 *
 * `*term*` rather than `term*`: forum display names are commonly "First Last", and a
 * search that could not find somebody by their surname would oblige the app to ask
 * members to type a name from the left. The cost is a leading wildcard, so MySQL
 * cannot use an index on `display_name` — acceptable at forum scale, and part of why
 * the route refuses a one-character term rather than letting it scan the table.
 *
 * ⚠ **`(display_name, ID)`, never `display_name` alone.** Two members can share a
 * display name — WordPress enforces uniqueness on `user_login` and `user_nicename`,
 * and on neither of these — and paging a sort with ties serves one row twice and drops
 * another. That is CLAUDE.md's trap #4, the defect that made reply paging unstable,
 * arriving by a different door.
 *
 * @since 0.6.1
 */
class UserSearchQuery {

	/**
	 * The only columns a member search may ever match on.
	 *
	 * ⚠ Adding to this list publishes a field. `user_email` and `user_login` are
	 * absent deliberately, and the class docblock above is the argument for why.
	 *
	 * @var string[]
	 * @since 0.6.1
	 */
	public const COLUMNS = array( 'display_name', 'user_nicename' );

	/**
	 * One page of the members matching a term.
	 *
	 * ⚠ **The term is not checked here.** How short is too short is a property of the
	 * route rather than of the query — see Rest\UserSearchController::MIN_TERM_LENGTH,
	 * which refuses before this is ever built.
	 *
	 * @since 0.6.1
	 *
	 * @param string $terms    What to search for; already trimmed and long enough.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page, already bounded.
	 * @return array<string,mixed>
	 */
	public function args( string $terms, int $page, int $per_page ): array {
		return array(
			// The asterisks are WP_User_Query's own wildcard syntax. It detects them and
			// then `trim()`s **every** leading and trailing one off before the term
			// reaches `esc_like()`, so an asterisk *inside* the term survives as a
			// literal character while one the member happened to type at either end is
			// quietly eaten. Both are already contains-matches here, so the second
			// costs nothing beyond a character that was never going to match a name.
			'search'         => '*' . $terms . '*',
			'search_columns' => self::COLUMNS,
			'number'         => max( 1, $per_page ),
			'paged'          => max( 1, $page ),
			'orderby'        => array(
				'display_name' => 'ASC',
				'ID'           => 'ASC',
			),
		);
	}
}
