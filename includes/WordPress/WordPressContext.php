<?php
/**
 * Live WordPress / bbPress implementation of the context seam.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\WordPress;

use WP_Query;

/**
 * Thin one-line delegations to WordPress and bbPress globals. This is the only
 * class in the plugin that calls those globals directly; everything else depends
 * on ContextInterface so it can be faked in tests.
 */
class WordPressContext implements ContextInterface {

	/**
	 * Register an action callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Register a filter callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Remove a filter callback registered under a string function name.
	 *
	 * @param string $hook     Hook name.
	 * @param string $callback Callback function name.
	 * @param int    $priority Priority it was added with.
	 */
	public function remove_filter( string $hook, string $callback, int $priority = 10 ): void {
		remove_filter( $hook, $callback, $priority );
	}

	/**
	 * Apply filters to a value.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value to filter.
	 * @return mixed Filtered value.
	 */
	public function apply_filters( string $hook, $value ) {
		return apply_filters( $hook, $value );
	}

	/**
	 * Whether the main query is a bbPress page.
	 *
	 * @return bool
	 */
	public function is_bbpress(): bool {
		return (bool) is_bbpress();
	}

	/**
	 * Whether this is the forums index (archive).
	 *
	 * @return bool
	 */
	public function is_forum_archive(): bool {
		return (bool) bbp_is_forum_archive();
	}

	/**
	 * Whether this is a single forum.
	 *
	 * @return bool
	 */
	public function is_single_forum(): bool {
		return (bool) bbp_is_single_forum();
	}

	/**
	 * Whether this is a single topic (reading view).
	 *
	 * @return bool
	 */
	public function is_single_topic(): bool {
		return (bool) bbp_is_single_topic();
	}

	/**
	 * Whether this is a single reply permalink.
	 *
	 * @return bool
	 */
	public function is_single_reply(): bool {
		return (bool) bbp_is_single_reply();
	}

	/**
	 * Whether threaded (hierarchical) replies are enabled site-wide.
	 *
	 * @return bool
	 */
	public function is_thread_replies_active(): bool {
		return (bool) bbp_thread_replies();
	}

	/**
	 * Whether a user is logged in.
	 *
	 * @return bool
	 */
	public function is_user_logged_in(): bool {
		return (bool) is_user_logged_in();
	}

	/**
	 * Current user ID (0 if logged out).
	 *
	 * @return int
	 */
	public function get_current_user_id(): int {
		return (int) get_current_user_id();
	}

	/**
	 * Login URL, optionally with a redirect target.
	 *
	 * @param string $redirect URL to return to after login.
	 * @return string
	 */
	public function get_login_url( string $redirect = '' ): string {
		return (string) wp_login_url( $redirect );
	}

	/**
	 * A piece of site information (e.g. "name", "charset").
	 *
	 * @param string $key Info key.
	 * @return string
	 */
	public function get_bloginfo( string $key ): string {
		return (string) get_bloginfo( $key );
	}

	/**
	 * A user's bbPress profile URL.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function get_user_profile_url( int $user_id ): string {
		return (string) bbp_get_user_profile_url( $user_id );
	}

	/**
	 * The forums index URL.
	 *
	 * @return string
	 */
	public function get_forums_url(): string {
		return (string) bbp_get_forums_url();
	}

	/**
	 * A topic's permalink.
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_permalink( int $topic_id ): string {
		return (string) bbp_get_topic_permalink( $topic_id );
	}

	/**
	 * The reading-view URL for a reply (parent topic + page + anchor).
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function get_reply_url( int $reply_id ): string {
		return (string) bbp_get_reply_url( $reply_id );
	}

	/**
	 * Plain-text display name of a topic or reply author.
	 *
	 * Resolves through bbp_get_author_link() — which handles both a topic and a
	 * reply, since bbPress's freshness "last active id" can point at either — then
	 * strips the profile link, as the reading UI never sends readers into themed
	 * profile pages.
	 *
	 * @param int $post_id Topic or reply ID.
	 * @return string
	 */
	public function get_author_name( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$link = bbp_get_author_link(
			array(
				'post_id'   => $post_id,
				'type'      => 'name',
				'show_role' => false,
			)
		);
		return trim( wp_strip_all_tags( (string) $link ) );
	}

	/**
	 * ID of the reply currently in the loop.
	 *
	 * @return int
	 */
	public function get_reply_id(): int {
		return (int) bbp_get_reply_id();
	}

	/**
	 * Display name of a reply's author.
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function get_reply_author_display_name( int $reply_id ): string {
		return (string) bbp_get_reply_author_display_name( $reply_id );
	}

	/**
	 * A reply's post date.
	 *
	 * @param int  $reply_id Reply ID.
	 * @param bool $humanize Whether to return a human-readable diff.
	 * @return string
	 */
	public function get_reply_post_date( int $reply_id, bool $humanize = true ): string {
		return (string) bbp_get_reply_post_date( $reply_id, $humanize );
	}

	/**
	 * Echo a reply's filtered content (real post HTML).
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function the_reply_content( int $reply_id ): void {
		bbp_reply_content( $reply_id );
	}

	/**
	 * The forum ID a topic belongs to.
	 *
	 * @param int $topic_id Topic ID.
	 * @return int
	 */
	public function get_topic_forum_id( int $topic_id ): int {
		return (int) bbp_get_topic_forum_id( $topic_id );
	}

	/**
	 * Whether the current user may view a forum, ancestors taken into account.
	 *
	 * Delegates to bbPress's own capability check, which allows a keymaster or a
	 * user with `read_forum` on a private/hidden forum, denies everyone else, and
	 * — with check_ancestors — refuses a public forum nested under a restricted
	 * parent. Its deny-by-default fallthrough also covers draft/trashed forums.
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	public function user_can_view_forum( int $forum_id ): bool {
		return (bool) bbp_user_can_view_forum(
			array(
				'forum_id'        => $forum_id,
				'check_ancestors' => true,
			)
		);
	}

	/**
	 * The topic post type key.
	 *
	 * @return string
	 */
	public function get_topic_post_type(): string {
		return (string) bbp_get_topic_post_type();
	}

	/**
	 * The reply post type key.
	 *
	 * @return string
	 */
	public function get_reply_post_type(): string {
		return (string) bbp_get_reply_post_type();
	}

	/**
	 * The "public" post status key.
	 *
	 * @return string
	 */
	public function get_public_status_id(): string {
		return (string) bbp_get_public_status_id();
	}

	/**
	 * The "closed" post status key.
	 *
	 * @return string
	 */
	public function get_closed_status_id(): string {
		return (string) bbp_get_closed_status_id();
	}

	/**
	 * Replies shown per page.
	 *
	 * @return int
	 */
	public function get_replies_per_page(): int {
		return (int) bbp_get_replies_per_page();
	}

	/**
	 * A post's type.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_post_type( int $post_id ): string {
		return (string) get_post_type( $post_id );
	}

	/**
	 * A post's status.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_post_status( int $post_id ): string {
		return (string) get_post_status( $post_id );
	}

	/**
	 * A single post-meta value as a string.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	public function get_post_meta_value( int $post_id, string $key ): string {
		return (string) get_post_meta( $post_id, $key, true );
	}

	/**
	 * Read a transient.
	 *
	 * @param string $key Transient key.
	 * @return mixed The value, or false if absent.
	 */
	public function get_transient( string $key ) {
		return get_transient( $key );
	}

	/**
	 * Write a transient.
	 *
	 * @param string $key   Transient key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Lifetime in seconds.
	 */
	public function set_transient( string $key, $value, int $ttl ): void {
		set_transient( $key, $value, $ttl );
	}

	/**
	 * Topic IDs in a forum, ordered freshest first.
	 *
	 * Immediate forum only — topics in sub-forums are not folded in.
	 *
	 * @param int      $forum_id Forum ID.
	 * @param string[] $statuses Post statuses to include.
	 * @return int[]
	 */
	public function get_forum_topic_ids( int $forum_id, array $statuses ): array {
		$query = new WP_Query(
			array(
				'post_type'        => bbp_get_topic_post_type(),
				'post_parent'      => $forum_id,
				'post_status'      => $statuses,
				'posts_per_page'   => -1,
				'meta_key'         => '_bbp_last_active_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'          => 'meta_value',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Prime the replies loop.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return bool Whether any replies matched.
	 */
	public function has_replies( array $args ): bool {
		return (bool) bbp_has_replies( $args );
	}

	/**
	 * Advance the replies loop.
	 *
	 * @return bool Whether a reply remains.
	 */
	public function the_replies_loop(): bool {
		return (bool) bbp_replies();
	}

	/**
	 * Set up the current reply in the loop.
	 */
	public function the_reply(): void {
		bbp_the_reply();
	}

	/**
	 * The number of reply pages from the last replies query.
	 *
	 * @return int
	 */
	public function get_max_reply_pages(): int {
		// reply_query is always primed by a has_replies() call before this runs
		// (the reading view and the AJAX handler both do so).
		return (int) bbpress()->reply_query->max_num_pages;
	}

	/**
	 * Enqueue a stylesheet.
	 *
	 * @param string   $handle  Handle.
	 * @param string   $src     URL.
	 * @param string[] $deps    Dependencies.
	 * @param string   $version Version.
	 */
	public function enqueue_style( string $handle, string $src, array $deps, string $version ): void {
		wp_enqueue_style( $handle, $src, $deps, $version );
	}

	/**
	 * Enqueue a script.
	 *
	 * @param string   $handle    Handle.
	 * @param string   $src       URL.
	 * @param string[] $deps      Dependencies.
	 * @param string   $version   Version.
	 * @param bool     $in_footer Whether to print in the footer.
	 */
	public function enqueue_script( string $handle, string $src, array $deps, string $version, bool $in_footer ): void {
		wp_enqueue_script( $handle, $src, $deps, $version, $in_footer );
	}

	/**
	 * Attach a localized data object to a script.
	 *
	 * @param string              $handle      Script handle.
	 * @param string              $object_name JS global name.
	 * @param array<string,mixed> $data        Data.
	 */
	public function localize_script( string $handle, string $object_name, array $data ): void {
		wp_localize_script( $handle, $object_name, $data );
	}

	/**
	 * The bbPress front-end AJAX endpoint URL.
	 *
	 * @return string
	 */
	public function get_ajax_url(): string {
		return (string) bbp_get_ajax_url();
	}

	/**
	 * Handles of every currently enqueued stylesheet.
	 *
	 * @return string[]
	 */
	public function get_enqueued_style_handles(): array {
		$styles = wp_styles();
		return array_values( array_map( 'strval', (array) $styles->queue ) );
	}

	/**
	 * Dequeue a stylesheet by handle.
	 *
	 * @param string $handle Handle.
	 */
	public function dequeue_style( string $handle ): void {
		wp_dequeue_style( $handle );
	}

	/**
	 * Issue a safe redirect.
	 *
	 * @param string $url    Target URL.
	 * @param int    $status HTTP status code.
	 */
	public function safe_redirect( string $url, int $status ): void {
		wp_safe_redirect( $url, $status );
	}

	/**
	 * End the request.
	 *
	 * A bare exit cannot execute inside a test process, so this method is excluded
	 * from coverage — the same unexecutable-exit category as the ABSPATH
	 * direct-access guards, the one place the house standard permits it. The
	 * redirect-then-stop behaviour is proven through the mockable seam (see
	 * TemplateControllerTest).
	 *
	 * @codeCoverageIgnore
	 */
	public function terminate(): void {
		exit;
	}

	/**
	 * Send a JSON error response and end the request.
	 *
	 * @param array<string,mixed> $data   Payload.
	 * @param int                 $status HTTP status code.
	 */
	public function send_json_error( array $data, int $status ): never {
		wp_send_json_error( $data, $status );
	}

	/**
	 * Send a JSON success response and end the request.
	 *
	 * @param array<string,mixed> $data Payload.
	 */
	public function send_json_success( array $data ): never {
		wp_send_json_success( $data );
	}
}
