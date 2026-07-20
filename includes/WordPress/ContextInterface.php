<?php
/**
 * The WordPress / bbPress seam.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\WordPress;

/**
 * Wraps every stateful or side-effecting WordPress/bbPress global the plugin
 * touches, so services depend on this interface rather than on globals directly.
 * That is what makes the reading logic unit-testable without a live WordPress:
 * tests inject a fake implementation (see tests/Support/FakeContext.php).
 *
 * Pure, deterministic formatting helpers (esc_*, __, number_format_i18n, …) are
 * intentionally NOT wrapped here — they are called directly and shimmed in the
 * PHPUnit bootstrap, keeping this surface to the parts that actually need faking.
 */
interface ContextInterface {

	// --- Hooks --------------------------------------------------------------

	/**
	 * Register an action callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void;

	/**
	 * Register a filter callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void;

	/**
	 * Remove a filter callback registered under a string function name.
	 *
	 * @param string $hook     Hook name.
	 * @param string $callback Callback function name.
	 * @param int    $priority Priority it was added with.
	 */
	public function remove_filter( string $hook, string $callback, int $priority = 10 ): void;

	/**
	 * Apply filters to a value.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value to filter.
	 * @return mixed Filtered value.
	 */
	public function apply_filters( string $hook, $value );

	// --- bbPress conditional tags ------------------------------------------

	/**
	 * Whether the main query is a bbPress page.
	 *
	 * @return bool
	 */
	public function is_bbpress(): bool;

	/**
	 * Whether this is the forums index (archive).
	 *
	 * @return bool
	 */
	public function is_forum_archive(): bool;

	/**
	 * Whether this is a single forum.
	 *
	 * @return bool
	 */
	public function is_single_forum(): bool;

	/**
	 * Whether this is a single topic (reading view).
	 *
	 * @return bool
	 */
	public function is_single_topic(): bool;

	/**
	 * Whether this is a single reply permalink.
	 *
	 * @return bool
	 */
	public function is_single_reply(): bool;

	/**
	 * Whether threaded (hierarchical) replies are enabled site-wide.
	 *
	 * @return bool
	 */
	public function is_thread_replies_active(): bool;

	// --- Auth & site state --------------------------------------------------

	/**
	 * Whether a user is logged in.
	 *
	 * @return bool
	 */
	public function is_user_logged_in(): bool;

	/**
	 * Current user ID (0 if logged out).
	 *
	 * @return int
	 */
	public function get_current_user_id(): int;

	/**
	 * Login URL, optionally with a redirect target.
	 *
	 * @param string $redirect URL to return to after login.
	 * @return string
	 */
	public function get_login_url( string $redirect = '' ): string;

	/**
	 * A piece of site information (e.g. "name", "charset").
	 *
	 * @param string $key Info key.
	 * @return string
	 */
	public function get_bloginfo( string $key ): string;

	// --- bbPress URLs & identity -------------------------------------------

	/**
	 * A user's bbPress profile URL.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function get_user_profile_url( int $user_id ): string;

	/**
	 * The forums index URL.
	 *
	 * @return string
	 */
	public function get_forums_url(): string;

	/**
	 * A topic's permalink.
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_permalink( int $topic_id ): string;

	/**
	 * The reading-view URL for a reply (parent topic + page + anchor).
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function get_reply_url( int $reply_id ): string;

	/**
	 * Plain-text display name of a topic or reply author.
	 *
	 * @param int $post_id Topic or reply ID.
	 * @return string
	 */
	public function get_author_name( int $post_id ): string;

	// --- bbPress reply / topic getters -------------------------------------

	/**
	 * ID of the reply currently in the loop.
	 *
	 * @return int
	 */
	public function get_reply_id(): int;

	/**
	 * Display name of a reply's author.
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function get_reply_author_display_name( int $reply_id ): string;

	/**
	 * A reply's post date.
	 *
	 * @param int  $reply_id Reply ID.
	 * @param bool $humanize Whether to return a human-readable diff.
	 * @return string
	 */
	public function get_reply_post_date( int $reply_id, bool $humanize = true ): string;

	/**
	 * Echo a reply's filtered content (real post HTML).
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function the_reply_content( int $reply_id ): void;

	/**
	 * The forum ID a topic belongs to.
	 *
	 * @param int $topic_id Topic ID.
	 * @return int
	 */
	public function get_topic_forum_id( int $topic_id ): int;

	/**
	 * Whether the current user may view a forum, ancestors taken into account.
	 *
	 * Capability-aware, not merely status-based: a keymaster, moderator, or member
	 * of a private forum is allowed, while an unauthorised visitor is refused —
	 * including for a public forum nested beneath a restricted ancestor.
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	public function user_can_view_forum( int $forum_id ): bool;

	// --- bbPress types & statuses ------------------------------------------

	/**
	 * The topic post type key.
	 *
	 * @return string
	 */
	public function get_topic_post_type(): string;

	/**
	 * The reply post type key.
	 *
	 * @return string
	 */
	public function get_reply_post_type(): string;

	/**
	 * The "public" post status key.
	 *
	 * @return string
	 */
	public function get_public_status_id(): string;

	/**
	 * The "closed" post status key.
	 *
	 * @return string
	 */
	public function get_closed_status_id(): string;

	/**
	 * Replies shown per page.
	 *
	 * @return int
	 */
	public function get_replies_per_page(): int;

	// --- WordPress post / meta / transients --------------------------------

	/**
	 * A post's type.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_post_type( int $post_id ): string;

	/**
	 * A post's status.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_post_status( int $post_id ): string;

	/**
	 * A single post-meta value as a string.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	public function get_post_meta_value( int $post_id, string $key ): string;

	/**
	 * Read a transient.
	 *
	 * @param string $key Transient key.
	 * @return mixed The value, or false if absent.
	 */
	public function get_transient( string $key );

	/**
	 * Write a transient.
	 *
	 * @param string $key   Transient key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Lifetime in seconds.
	 */
	public function set_transient( string $key, $value, int $ttl ): void;

	// --- bbPress query & replies loop --------------------------------------

	/**
	 * Topic IDs in a forum, ordered freshest first.
	 *
	 * @param int      $forum_id Forum ID.
	 * @param string[] $statuses Post statuses to include.
	 * @return int[]
	 */
	public function get_forum_topic_ids( int $forum_id, array $statuses ): array;

	/**
	 * Prime the replies loop.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return bool Whether any replies matched.
	 */
	public function has_replies( array $args ): bool;

	/**
	 * Advance the replies loop.
	 *
	 * @return bool Whether a reply remains.
	 */
	public function the_replies_loop(): bool;

	/**
	 * Set up the current reply in the loop.
	 */
	public function the_reply(): void;

	/**
	 * The number of reply pages from the last replies query.
	 *
	 * @return int
	 */
	public function get_max_reply_pages(): int;

	// --- Assets -------------------------------------------------------------

	/**
	 * Enqueue a stylesheet.
	 *
	 * @param string   $handle  Handle.
	 * @param string   $src     URL.
	 * @param string[] $deps    Dependencies.
	 * @param string   $version Version.
	 */
	public function enqueue_style( string $handle, string $src, array $deps, string $version ): void;

	/**
	 * Enqueue a script.
	 *
	 * @param string   $handle    Handle.
	 * @param string   $src       URL.
	 * @param string[] $deps      Dependencies.
	 * @param string   $version   Version.
	 * @param bool     $in_footer Whether to print in the footer.
	 */
	public function enqueue_script( string $handle, string $src, array $deps, string $version, bool $in_footer ): void;

	/**
	 * Attach a localized data object to a script.
	 *
	 * @param string              $handle      Script handle.
	 * @param string              $object_name JS global name.
	 * @param array<string,mixed> $data        Data.
	 */
	public function localize_script( string $handle, string $object_name, array $data ): void;

	/**
	 * The bbPress front-end AJAX endpoint URL.
	 *
	 * @return string
	 */
	public function get_ajax_url(): string;

	/**
	 * Handles of every currently enqueued stylesheet.
	 *
	 * @return string[]
	 */
	public function get_enqueued_style_handles(): array;

	/**
	 * Dequeue a stylesheet by handle.
	 *
	 * @param string $handle Handle.
	 */
	public function dequeue_style( string $handle ): void;

	// --- Output control -----------------------------------------------------

	/**
	 * Issue a safe redirect.
	 *
	 * @param string $url    Target URL.
	 * @param int    $status HTTP status code.
	 */
	public function safe_redirect( string $url, int $status ): void;

	/**
	 * End the request.
	 */
	public function terminate(): void;

	/**
	 * Send a JSON error response and end the request.
	 *
	 * @param array<string,mixed> $data   Payload.
	 * @param int                 $status HTTP status code.
	 */
	public function send_json_error( array $data, int $status ): never;

	/**
	 * Send a JSON success response and end the request.
	 *
	 * @param array<string,mixed> $data Payload.
	 */
	public function send_json_success( array $data ): never;
}
