<?php
/**
 * Live WordPress / bbPress implementation of the context seam.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\WordPress;

use WP_Query;

/**
 * Thin one-line delegations to WordPress and bbPress globals. This is the only
 * class in the plugin that calls those globals directly; everything else depends
 * on ContextInterface so it can be faked in tests.
 *
 * @since 0.1.0
 */
class WordPressContext implements ContextInterface {

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
	public function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

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
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Remove a filter callback registered under a string function name.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_bbpress(): bool {
		return (bool) is_bbpress();
	}

	/**
	 * Whether this is the forums index (archive).
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_forum_archive(): bool {
		return (bool) bbp_is_forum_archive();
	}

	/**
	 * Whether this is a single forum.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_single_forum(): bool {
		return (bool) bbp_is_single_forum();
	}

	/**
	 * Whether this is a single topic (reading view).
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_single_topic(): bool {
		return (bool) bbp_is_single_topic();
	}

	/**
	 * Whether this is a single reply permalink.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_single_reply(): bool {
		return (bool) bbp_is_single_reply();
	}

	/**
	 * Whether threaded (hierarchical) replies are enabled site-wide.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_thread_replies_active(): bool {
		return (bool) bbp_thread_replies();
	}

	/**
	 * Whether this is the edit-topic form.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_topic_edit(): bool {
		return (bool) bbp_is_topic_edit();
	}

	/**
	 * Whether this is the edit-reply form.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_reply_edit(): bool {
		return (bool) bbp_is_reply_edit();
	}

	/**
	 * Whether this is the create/edit-forum form (keymaster administration).
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_forum_edit(): bool {
		return (bool) bbp_is_forum_edit();
	}

	/**
	 * Whether a user is logged in.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_user_logged_in(): bool {
		return (bool) is_user_logged_in();
	}

	/**
	 * Current user ID (0 if logged out).
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_current_user_id(): int {
		return (int) get_current_user_id();
	}

	/**
	 * Whether the current user holds a capability.
	 *
	 * @since 0.3.0
	 *
	 * @param string $capability Capability to test.
	 * @return bool
	 */
	public function current_user_can( string $capability ): bool {
		return (bool) current_user_can( $capability );
	}

	/**
	 * Login URL, optionally with a redirect target.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function get_user_profile_url( int $user_id ): string {
		return (string) bbp_get_user_profile_url( $user_id );
	}

	/**
	 * Display name of the user whose profile is being viewed, as plain text.
	 *
	 * WordPress stores display names HTML-encoded (e.g. "Mara &amp; Co"), and
	 * bbPress returns them so under every filter. We decode to a plain string here
	 * so the presenter escapes it exactly once (matching the reading view, where
	 * the view owns escaping) — without this it would render double-encoded as
	 * "Mara &amp;amp; Co". Decoding is correct whichever way storage encodes it.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_displayed_user_name(): string {
		return html_entity_decode(
			(string) bbp_get_displayed_user_field( 'display_name', 'raw' ),
			ENT_QUOTES,
			'UTF-8'
		);
	}

	/**
	 * Nicename (the URL slug, shown as the @handle) of the displayed user.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_displayed_user_nicename(): string {
		return (string) bbp_get_displayed_user_field( 'user_nicename', 'raw' );
	}

	/**
	 * Forum-role label of the displayed user (e.g. "Participant").
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_displayed_user_role(): string {
		return (string) bbp_get_user_display_role( bbp_get_displayed_user_id() );
	}

	/**
	 * The forums index URL.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_forums_url(): string {
		return (string) bbp_get_forums_url();
	}

	/**
	 * A topic's permalink.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * ID of the forum currently in the forums loop.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_forum_id(): int {
		return (int) bbp_get_forum_id();
	}

	/**
	 * A forum's permalink.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_permalink( int $forum_id ): string {
		return (string) bbp_get_forum_permalink( $forum_id );
	}

	/**
	 * A forum's title.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_title( int $forum_id ): string {
		return (string) bbp_get_forum_title( $forum_id );
	}

	/**
	 * A forum's description, as bbPress renders it.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_content( int $forum_id ): string {
		return (string) bbp_get_forum_content( $forum_id );
	}

	/**
	 * A forum's topic count, sub-forum topics included.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return int
	 */
	public function get_forum_topic_count( int $forum_id ): int {
		return (int) bbp_get_forum_topic_count( $forum_id, true, true );
	}

	/**
	 * ID of the last active post in a forum.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return int
	 */
	public function get_forum_last_active_id( int $forum_id ): int {
		return (int) bbp_get_forum_last_active_id( $forum_id );
	}

	/**
	 * Human-readable time of a forum's last activity.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_last_active_time( int $forum_id ): string {
		return (string) bbp_get_forum_last_active_time( $forum_id );
	}

	/**
	 * ID of the reply currently in the loop.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_reply_id(): int {
		return (int) bbp_get_reply_id();
	}

	/**
	 * Display name of a reply's author.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function the_reply_content( int $reply_id ): void {
		bbp_reply_content( $reply_id );
	}

	/**
	 * ID of the topic currently in the loop.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_topic_id(): int {
		return (int) bbp_get_topic_id();
	}

	/**
	 * A topic's title.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_title( int $topic_id ): string {
		return (string) bbp_get_topic_title( $topic_id );
	}

	/**
	 * Display name of a topic's author.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_author_name( int $topic_id ): string {
		return (string) bbp_get_topic_author_display_name( $topic_id );
	}

	/**
	 * Human-readable time of a topic's last activity.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_last_active_time( int $topic_id ): string {
		return (string) bbp_get_topic_last_active_time( $topic_id );
	}

	/**
	 * A topic's reply count.
	 *
	 * @since 0.1.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return int
	 */
	public function get_topic_reply_count( int $topic_id ): int {
		return (int) bbp_get_topic_reply_count( $topic_id, true );
	}

	/**
	 * The forum ID a topic belongs to.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * Whether a post is password-protected and its password has not been supplied.
	 *
	 * Delegates to WordPress core's own check on the given post, keyed by ID so it
	 * is independent of the global `$post` — which bbPress's theme-compat resets
	 * mid-request, so the AJAX gate and the template branch both stay correct.
	 *
	 * @since 0.3.0
	 *
	 * @param int $post_id Forum or topic ID.
	 * @return bool
	 */
	public function is_password_required( int $post_id ): bool {
		return (bool) post_password_required( $post_id );
	}

	/**
	 * The topic post type key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_topic_post_type(): string {
		return (string) bbp_get_topic_post_type();
	}

	/**
	 * The reply post type key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_reply_post_type(): string {
		return (string) bbp_get_reply_post_type();
	}

	/**
	 * The "public" post status key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_public_status_id(): string {
		return (string) bbp_get_public_status_id();
	}

	/**
	 * The "closed" post status key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_closed_status_id(): string {
		return (string) bbp_get_closed_status_id();
	}

	/**
	 * Replies shown per page.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_replies_per_page(): int {
		return (int) bbp_get_replies_per_page();
	}

	/**
	 * The forum post type key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_forum_post_type(): string {
		return (string) bbp_get_forum_post_type();
	}

	/**
	 * Topics shown per page.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_topics_per_page(): int {
		return (int) bbp_get_topics_per_page();
	}

	/**
	 * Forums shown per page.
	 *
	 * Read from the option directly because bbPress ships no accessor for this one,
	 * with the same default (50) and the same empty-means-default floor its own
	 * bbp_get_topics_per_page() applies — so a site that blanks the setting gets a
	 * page size rather than a query for nothing.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_forums_per_page(): int {
		$per_page = (int) get_option( '_bbp_forums_per_page', 50 );

		return $per_page > 0 ? $per_page : 50;
	}

	/**
	 * The page number the current request asks for (1 when unpaged).
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_paged(): int {
		return max( 1, (int) bbp_get_paged() );
	}

	/**
	 * A post's type.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
				// Same (last-active, ID) order the thread list pages on, so the
				// bar walks threads in the order the list showed them. Without the
				// tiebreak, topics sharing a timestamp can order differently here
				// than there, and Next would revisit a thread or skip one.
				'orderby'          => array(
					'meta_value' => 'DESC',
					'ID'         => 'DESC',
				),
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * IDs of a forum's sticky topics, super stickies included.
	 *
	 * @since 0.1.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return int[]
	 */
	public function get_sticky_topic_ids( int $forum_id ): array {
		// The same union bbp_add_sticky_topics() pins to page 1: site-wide super
		// stickies first, then this forum's own. Both are stored as ID arrays in
		// options/meta, so duplicates and empty slots are filtered out here.
		$stickies = array_merge(
			(array) bbp_get_super_stickies(),
			(array) bbp_get_stickies( $forum_id )
		);

		return array_values( array_filter( array_unique( array_map( 'intval', $stickies ) ) ) );
	}

	/**
	 * Prime the forums loop.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return bool Whether any forums matched.
	 */
	public function has_forums( array $args ): bool {
		return (bool) bbp_has_forums( $args );
	}

	/**
	 * Advance the forums loop.
	 *
	 * @since 0.3.0
	 *
	 * @return bool Whether a forum remains.
	 */
	public function the_forums_loop(): bool {
		return (bool) bbp_forums();
	}

	/**
	 * Set up the current forum in the loop.
	 *
	 * @since 0.3.0
	 */
	public function the_forum(): void {
		bbp_the_forum();
	}

	/**
	 * The number of forum pages from the last forums query.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_max_forum_pages(): int {
		// forum_query is always primed by a has_forums() call before this runs (both
		// takeover forum screens and the AJAX handler do so).
		return (int) bbpress()->forum_query->max_num_pages;
	}

	/**
	 * Restore the global post after a secondary loop.
	 *
	 * @since 0.3.0
	 */
	public function reset_postdata(): void {
		wp_reset_postdata();
	}

	/**
	 * Prime the topics loop.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return bool Whether any topics matched.
	 */
	public function has_topics( array $args ): bool {
		return (bool) bbp_has_topics( $args );
	}

	/**
	 * Advance the topics loop.
	 *
	 * @since 0.1.0
	 *
	 * @return bool Whether a topic remains.
	 */
	public function the_topics_loop(): bool {
		return (bool) bbp_topics();
	}

	/**
	 * Set up the current topic in the loop.
	 *
	 * @since 0.1.0
	 */
	public function the_topic(): void {
		bbp_the_topic();
	}

	/**
	 * The number of topic pages from the last topics query.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_max_topic_pages(): int {
		// topic_query is always primed by a has_topics() call before this runs
		// (the forum screen and the AJAX handler both do so).
		return (int) bbpress()->topic_query->max_num_pages;
	}

	/**
	 * Prime the replies loop.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @return bool Whether a reply remains.
	 */
	public function the_replies_loop(): bool {
		return (bool) bbp_replies();
	}

	/**
	 * Set up the current reply in the loop.
	 *
	 * @since 0.1.0
	 */
	public function the_reply(): void {
		bbp_the_reply();
	}

	/**
	 * The number of reply pages from the last replies query.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_ajax_url(): string {
		return (string) bbp_get_ajax_url();
	}

	/**
	 * Handles of every currently enqueued stylesheet.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param string $handle Handle.
	 */
	public function dequeue_style( string $handle ): void {
		wp_dequeue_style( $handle );
	}

	/**
	 * The registered source URL of an enqueued stylesheet, or '' if unknown.
	 *
	 * @since 0.3.0
	 *
	 * @param string $handle Handle.
	 * @return string
	 */
	public function get_style_src( string $handle ): string {
		$styles = wp_styles();
		if ( ! isset( $styles->registered[ $handle ] ) ) {
			return '';
		}
		$src = $styles->registered[ $handle ]->src;
		return is_string( $src ) ? $src : '';
	}

	/**
	 * The active (parent) theme's directory URL, no trailing slash.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_template_directory_uri(): string {
		return (string) get_template_directory_uri();
	}

	/**
	 * The active theme's directory URL (child theme's when one is active), no
	 * trailing slash.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_stylesheet_directory_uri(): string {
		return (string) get_stylesheet_directory_uri();
	}

	/**
	 * Issue a safe redirect.
	 *
	 * @since 0.1.0
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
	 *
	 * @since 0.1.0
	 */
	public function terminate(): void {
		exit;
	}

	/**
	 * Send a JSON error response and end the request.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param array<string,mixed> $data Payload.
	 */
	public function send_json_success( array $data ): never {
		wp_send_json_success( $data );
	}
}
