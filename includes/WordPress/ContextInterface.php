<?php
/**
 * The WordPress / bbPress seam.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
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
 *
 * @since 0.1.0
 */
interface ContextInterface {

	// --- Hooks --------------------------------------------------------------

	/**
	 * Register an action callback.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param string $hook     Hook name.
	 * @param string $callback Callback function name.
	 * @param int    $priority Priority it was added with.
	 */
	public function remove_filter( string $hook, string $callback, int $priority = 10 ): void;

	/**
	 * Apply filters to a value.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_bbpress(): bool;

	/**
	 * Whether this is the forums index (archive).
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_forum_archive(): bool;

	/**
	 * Whether this is a single forum.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_single_forum(): bool;

	/**
	 * Whether this is a single topic (reading view).
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_single_topic(): bool;

	/**
	 * Whether this is a single reply permalink.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_single_reply(): bool;

	/**
	 * Whether threaded (hierarchical) replies are enabled site-wide.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_thread_replies_active(): bool;

	/**
	 * Whether this is the edit-topic form.
	 *
	 * One of the posting/edit forms the reskin tier excludes (owned by the posting
	 * phase, P4), so a request for it is left to the active theme.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_topic_edit(): bool;

	/**
	 * Whether this is the edit-reply form.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_reply_edit(): bool;

	/**
	 * Whether this is the create/edit-forum form (keymaster administration).
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_forum_edit(): bool;

	// --- Auth & site state --------------------------------------------------

	/**
	 * Whether a user is logged in.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_user_logged_in(): bool;

	/**
	 * Current user ID (0 if logged out).
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_current_user_id(): int;

	/**
	 * Whether the current user holds a capability.
	 *
	 * Capabilities, not role names: a site can rename or recompose roles, but the
	 * capability a decision actually rests on stays stable.
	 *
	 * @since 0.3.0
	 *
	 * @param string $capability Capability to test.
	 * @return bool
	 */
	public function current_user_can( string $capability ): bool;

	/**
	 * Login URL, optionally with a redirect target.
	 *
	 * @since 0.1.0
	 *
	 * @param string $redirect URL to return to after login.
	 * @return string
	 */
	public function get_login_url( string $redirect = '' ): string;

	/**
	 * A piece of site information (e.g. "name", "charset").
	 *
	 * @since 0.1.0
	 *
	 * @param string $key Info key.
	 * @return string
	 */
	public function get_bloginfo( string $key ): string;

	// --- bbPress URLs & identity -------------------------------------------

	/**
	 * A user's bbPress profile URL.
	 *
	 * @since 0.1.0
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function get_user_profile_url( int $user_id ): string;

	/**
	 * Display name of the user whose profile is being viewed.
	 *
	 * Drives the reskinned profile header's identity block (name + @handle +
	 * role beside the avatar), which bbPress otherwise renders name-less.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_displayed_user_name(): string;

	/**
	 * Nicename (the URL slug, shown as the @handle) of the displayed user.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_displayed_user_nicename(): string;

	/**
	 * Forum-role label of the displayed user (e.g. "Participant", "Keymaster").
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_displayed_user_role(): string;

	/**
	 * The forums index URL.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_forums_url(): string;

	/**
	 * A topic's permalink.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_permalink( int $topic_id ): string;

	/**
	 * The reading-view URL for a reply (parent topic + page + anchor).
	 *
	 * @since 0.1.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function get_reply_url( int $reply_id ): string;

	/**
	 * Plain-text display name of a topic or reply author.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Topic or reply ID.
	 * @return string
	 */
	public function get_author_name( int $post_id ): string;

	// --- bbPress reply / topic getters -------------------------------------

	/**
	 * ID of the reply currently in the loop.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_reply_id(): int;

	/**
	 * Display name of a reply's author.
	 *
	 * @since 0.1.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function get_reply_author_display_name( int $reply_id ): string;

	/**
	 * A reply's post date.
	 *
	 * @since 0.1.0
	 *
	 * @param int  $reply_id Reply ID.
	 * @param bool $humanize Whether to return a human-readable diff.
	 * @return string
	 */
	public function get_reply_post_date( int $reply_id, bool $humanize = true ): string;

	/**
	 * Echo a reply's filtered content (real post HTML).
	 *
	 * @since 0.1.0
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function the_reply_content( int $reply_id ): void;

	/**
	 * ID of the topic currently in the loop.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_topic_id(): int;

	/**
	 * A topic's title.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_title( int $topic_id ): string;

	/**
	 * Display name of a topic's author.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_author_name( int $topic_id ): string;

	/**
	 * Human-readable time of a topic's last activity.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_last_active_time( int $topic_id ): string;

	/**
	 * A topic's reply count.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return int
	 */
	public function get_topic_reply_count( int $topic_id ): int;

	/**
	 * The forum ID a topic belongs to.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	public function user_can_view_forum( int $forum_id ): bool;

	/**
	 * Whether a post is password-protected and its password has not been supplied.
	 *
	 * Drives the password gate in two places (issue #18): the single-forum and
	 * single-topic screen templates render WordPress's own password form in place
	 * of the content when this is true, and the load-more AJAX endpoints refuse
	 * under the same condition — mirroring the gate bbPress applies in its own
	 * templates.
	 *
	 * @since 0.3.0
	 *
	 * @param int $post_id Forum or topic ID.
	 * @return bool
	 */
	public function is_password_required( int $post_id ): bool;

	// --- bbPress types & statuses ------------------------------------------

	/**
	 * The forum post type key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_forum_post_type(): string;

	/**
	 * The topic post type key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_topic_post_type(): string;

	/**
	 * The reply post type key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_reply_post_type(): string;

	/**
	 * The "public" post status key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_public_status_id(): string;

	/**
	 * The "closed" post status key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_closed_status_id(): string;

	/**
	 * Replies shown per page.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_replies_per_page(): int;

	/**
	 * Topics shown per page.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_topics_per_page(): int;

	/**
	 * The page number the current request asks for (1 when unpaged).
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_paged(): int;

	// --- WordPress post / meta / transients --------------------------------

	/**
	 * A post's type.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_post_type( int $post_id ): string;

	/**
	 * A post's status.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_post_status( int $post_id ): string;

	/**
	 * A single post-meta value as a string.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	public function get_post_meta_value( int $post_id, string $key ): string;

	/**
	 * Read a transient.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key Transient key.
	 * @return mixed The value, or false if absent.
	 */
	public function get_transient( string $key );

	/**
	 * Write a transient.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param int      $forum_id Forum ID.
	 * @param string[] $statuses Post statuses to include.
	 * @return int[]
	 */
	public function get_forum_topic_ids( int $forum_id, array $statuses ): array;

	/**
	 * IDs of a forum's sticky topics, super stickies included.
	 *
	 * @since 0.1.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return int[]
	 */
	public function get_sticky_topic_ids( int $forum_id ): array;

	/**
	 * Prime the topics loop.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return bool Whether any topics matched.
	 */
	public function has_topics( array $args ): bool;

	/**
	 * Advance the topics loop.
	 *
	 * @since 0.1.0
	 *
	 * @return bool Whether a topic remains.
	 */
	public function the_topics_loop(): bool;

	/**
	 * Set up the current topic in the loop.
	 *
	 * @since 0.1.0
	 */
	public function the_topic(): void;

	/**
	 * The number of topic pages from the last topics query.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_max_topic_pages(): int;

	/**
	 * Prime the replies loop.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return bool Whether any replies matched.
	 */
	public function has_replies( array $args ): bool;

	/**
	 * Advance the replies loop.
	 *
	 * @since 0.1.0
	 *
	 * @return bool Whether a reply remains.
	 */
	public function the_replies_loop(): bool;

	/**
	 * Set up the current reply in the loop.
	 *
	 * @since 0.1.0
	 */
	public function the_reply(): void;

	/**
	 * The number of reply pages from the last replies query.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_max_reply_pages(): int;

	// --- Assets -------------------------------------------------------------

	/**
	 * Enqueue a stylesheet.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param string              $handle      Script handle.
	 * @param string              $object_name JS global name.
	 * @param array<string,mixed> $data        Data.
	 */
	public function localize_script( string $handle, string $object_name, array $data ): void;

	/**
	 * The bbPress front-end AJAX endpoint URL.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_ajax_url(): string;

	/**
	 * Handles of every currently enqueued stylesheet.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public function get_enqueued_style_handles(): array;

	/**
	 * Dequeue a stylesheet by handle.
	 *
	 * @since 0.1.0
	 *
	 * @param string $handle Handle.
	 */
	public function dequeue_style( string $handle ): void;

	/**
	 * The registered source URL of an enqueued stylesheet, or '' if unknown.
	 *
	 * Used by the reskin-tier suppression to tell theme stylesheets apart from
	 * bbPress's and ours by where they load from.
	 *
	 * @since 0.3.0
	 *
	 * @param string $handle Handle.
	 * @return string
	 */
	public function get_style_src( string $handle ): string;

	/**
	 * The active (parent) theme's directory URL, no trailing slash.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_template_directory_uri(): string;

	/**
	 * The active theme's directory URL — the child theme's when one is active,
	 * else the same as the template directory — no trailing slash.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_stylesheet_directory_uri(): string;

	// --- Output control -----------------------------------------------------

	/**
	 * Issue a safe redirect.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url    Target URL.
	 * @param int    $status HTTP status code.
	 */
	public function safe_redirect( string $url, int $status ): void;

	/**
	 * End the request.
	 *
	 * @since 0.1.0
	 */
	public function terminate(): void;

	/**
	 * Send a JSON error response and end the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $data   Payload.
	 * @param int                 $status HTTP status code.
	 */
	public function send_json_error( array $data, int $status ): never;

	/**
	 * Send a JSON success response and end the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $data Payload.
	 */
	public function send_json_success( array $data ): never;
}
