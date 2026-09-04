<?php
/**
 * WordPress and bbPress context contract.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\WordPress;

// phpcs:disable Generic.Commenting, Squiz.Commenting -- Contract-only and PHPStan-only docblocks intentionally omit redundant prose.

interface ContextInterface {

	/**
	 * @param callable $callback      Callback.
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void;

	/**
	 * @param callable $callback      Callback.
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void;

	public function remove_filter( string $hook, string $callback, int $priority = 10 ): void;

	/**
	 * @param callable $callback Callback to remove.
	 */
	public function remove_filter_callback( string $hook, callable $callback, int $priority ): void;

	/**
	 * @param mixed $value   Value to filter.
	 * @param mixed ...$args Further arguments the hook passes to its listeners.
	 * @return mixed Filtered value.
	 */
	public function apply_filters( string $hook, $value, ...$args );

	/**
	 * Routes must provide a permission callback.
	 *
	 * @param array<string,mixed> $args            Route arguments, as register_rest_route() takes them.
	 */
	public function register_rest_route( string $route_namespace, string $route, array $args ): void;

	public function is_bbpress(): bool;

	public function is_forum_archive(): bool;

	public function is_single_forum(): bool;

	public function is_single_topic(): bool;

	public function is_single_reply(): bool;

	public function is_thread_replies_active(): bool;

	public function is_forum_edit(): bool;

	/** True for topic merge and split forms as well as ordinary edits. */
	public function is_topic_edit(): bool;

	/** True for reply move forms as well as ordinary edits. */
	public function is_reply_edit(): bool;

	public function is_subscriptions(): bool;

	/** Uses bbp_is_search(); request parameters alone must not classify a search screen. */
	public function is_search(): bool;

	public function allow_search(): bool;

	public function is_user_logged_in(): bool;

	public function get_current_user_id(): int;

	public function current_user_can( string $capability ): bool;

	public function current_user_can_edit_user( int $user_id ): bool;

	public function current_user_can_moderate( int $post_id ): bool;

	public function get_topic_moderation_links( int $topic_id ): string;

	public function get_reply_moderation_links( int $reply_id ): string;

	public function get_topic_edit_link( int $topic_id ): string;

	public function get_reply_edit_link( int $reply_id ): string;

	/**
	 * @return array<int,string>
	 */
	public function get_public_topic_statuses(): array;

	/**
	 * @return array<int,string>
	 */
	public function get_public_reply_statuses(): array;

	public function get_pending_status_id(): string;

	public function get_post_author( int $post_id ): int;

	/**
	 * @return string[]
	 */
	public function get_admin_only_statuses(): array;

	/**
	 * @param string[] $statuses Statuses to withhold.
	 */
	public function status_exclusion_where_clause( array $statuses ): string;

	public function get_login_url( string $redirect = '' ): string;

	public function get_current_url(): string;

	public function can_access_create_reply_form(): bool;

	public function can_access_create_topic_form(): bool;

	public function is_forum_category( int $forum_id ): bool;

	/**
	 * Reads presentation state only; no nonce is required.
	 */
	public function get_requested_reply_to(): int;

	/**
	 * Checks flag presence only; no nonce is required.
	 */
	public function has_query_flag( string $key ): bool;

	public function add_query_arg( string $key, string $value, string $url ): string;

	public function has_errors(): bool;

	public function get_bloginfo( string $key ): string;

	public function get_user_profile_url( int $user_id ): string;

	public function get_displayed_user_name(): string;

	public function get_displayed_user_id(): int;

	public function get_displayed_user_nicename(): string;

	public function get_displayed_user_role(): string;

	public function get_bbpress_screen_title(): string;

	public function get_forums_url(): string;

	public function get_search_url(): string;

	public function get_topic_permalink( int $topic_id ): string;

	public function get_reply_url( int $reply_id ): string;

	public function get_author_name( int $post_id ): string;

	public function get_forum_id(): int;

	public function get_forum_permalink( int $forum_id ): string;

	public function get_forum_title( int $forum_id ): string;

	public function get_forum_content( int $forum_id ): string;

	public function get_forum_description( int $forum_id ): string;

	public function get_forum_parent_id( int $forum_id ): int;

	public function get_forum_topic_count( int $forum_id ): int;

	public function get_forum_last_active_id( int $forum_id ): int;

	public function get_forum_last_active_time( int $forum_id ): string;

	public function is_forum_closed( int $forum_id ): bool;

	public function get_reply_id(): int;

	public function get_reply_author_display_name( int $reply_id ): string;

	public function get_reply_post_date( int $reply_id, bool $humanize = true ): string;

	/**
	 * Returns the raw title to avoid recursion through the_title.
	 */
	public function get_reply_title( int $reply_id ): string;

	public function get_reply_excerpt( int $reply_id, int $length ): string;

	public function the_reply_content( int $reply_id ): void;

	public function get_reply_to( int $reply_id ): int;

	public function get_reply_topic_id( int $reply_id ): int;

	/**
	 * Uses bbPress visibility filters, then restores an unpaginated ID order.
	 *
	 * @param array<string,mixed> $extra_args Arguments merged into the query before bbPress parses it.
	 * @return array<int,int> Reply ID => parent reply ID (0 for a reply to the thread), in (date, ID) order.
	 */
	public function get_reply_parents( int $topic_id, array $extra_args = array() ): array;

	public function get_topic_id(): int;

	public function get_topic_title( int $topic_id ): string;

	public function get_topic_author_name( int $topic_id ): string;

	public function get_topic_last_active_time( int $topic_id ): string;

	/**
	 * Includes private and hidden statuses only when the reader has the corresponding capability.
	 *
	 * @return array<int,string>
	 */
	public function get_readable_topic_statuses(): array;

	/**
	 * @return array<int,int>
	 */
	public function get_excluded_forum_ids(): array;

	/**
	 * Falls back to the topic date when bbPress has no activity stamp.
	 */
	public function get_topic_last_active_datetime( int $topic_id ): string;

	/**
	 * Falls back to the topic ID; the stored ID wins even when lower.
	 */
	public function get_topic_last_active_id( int $topic_id ): int;

	public function get_topic_reply_count( int $topic_id ): int;

	public function get_topic_forum_id( int $topic_id ): int;

	public function get_topic_post_date( int $topic_id, bool $humanize = true ): string;

	public function get_topic_excerpt( int $topic_id, int $length ): string;

	/**
	 * Returns no excerpt for password-protected content.
	 */
	public function get_forum_excerpt( int $forum_id, int $length ): string;

	public function is_topic_closed( int $topic_id ): bool;

	/**
	 * Checks capabilities and restricted ancestors.
	 */
	public function user_can_view_forum( int $forum_id ): bool;

	/**
	 * Uses WordPress password-cookie semantics.
	 */
	public function is_password_required( int $post_id ): bool;

	public function get_forum_post_type(): string;

	public function get_topic_post_type(): string;

	public function get_reply_post_type(): string;

	public function get_public_status_id(): string;

	public function get_closed_status_id(): string;

	public function get_forums_per_page(): int;

	public function get_replies_per_page(): int;

	public function get_topics_per_page(): int;

	public function get_paged(): int;

	public function get_post_type( int $post_id ): string;

	public function get_post_status( int $post_id ): string;

	/**
	 * @param array<string,mixed> $args Query args (see Query\ForumQuery).
	 */
	public function has_forums( array $args ): bool;

	/**
	 * @param array<string,mixed> $args Query args (see Query\SubscribedForumQuery).
	 */
	public function has_forum_subscriptions( array $args ): bool;

	public function the_forums_loop(): bool;

	public function the_forum(): void;

	public function get_max_forum_pages(): int;

	public function render_forum_row(): void;

	/**
	 * @return int[]
	 */
	public function get_subscribed_forum_ids( int $user_id ): array;

	public function is_subscriptions_active(): bool;

	/**
	 * Restores the ambient post after a secondary bbPress loop.
	 */
	public function reset_postdata(): void;

	/**
	 * Returns zero position and neighbours when the topic is outside the ordered set.
	 *
	 * @param string[] $statuses Post statuses to include.
	 * @return array{total:int,position:int,prev_id:int,next_id:int}
	 */
	public function get_topic_rank( int $forum_id, int $topic_id, array $statuses ): array;

	/**
	 * @return int[]
	 */
	public function get_sticky_topic_ids( int $forum_id ): array;

	/**
	 * @return int[]
	 */
	public function get_super_sticky_ids(): array;

	/**
	 * @param array<string,mixed> $args Query args.
	 */
	public function has_topics( array $args ): bool;

	public function the_topics_loop(): bool;

	public function the_topic(): void;

	public function get_max_topic_pages(): int;

	/**
	 * @param array<string,mixed> $args Query args.
	 */
	public function has_replies( array $args ): bool;

	public function the_replies_loop(): bool;

	public function the_reply(): void;

	public function get_max_reply_pages(): int;

	public function get_search_terms(): string;

	public function sanitize_search_request( string $key ): string;

	/**
	 * @param array<string,mixed> $args Query args (see Query\SearchQuery).
	 */
	public function has_search_results( array $args ): bool;

	public function the_search_results_loop(): bool;

	public function the_search_result(): void;

	public function get_search_result_post_type(): string;

	public function get_search_result_id(): int;

	public function get_max_search_pages(): int;

	public function get_search_result_count(): int;

	/**
	 * Returns prepared SQL beginning with AND, or an empty string.
	 *
	 * @param string[] $statuses         Statuses admitted for every other post type.
	 */
	public function post_status_where_clause( string $exempt_post_type, array $statuses ): string;

	/**
	 * Withholds replies whose topic status or stored password is not readable; orphaned replies remain visible.
	 *
	 * @param string[] $topic_statuses  Statuses the reader may see a topic in.
	 */
	public function reply_parent_where_clause( string $reply_post_type, string $topic_post_type, array $topic_statuses ): string;

	/**
	 * Admits only one authenticated author’s pending replies in one topic. The input must begin with AND; page-scoped callers must pass their reply IDs.
	 *
	 * @param int[] $ids Further narrows the predicate to these reply IDs; empty means no ID constraint.
	 */
	public function own_pending_where_clause(
		string $where,
		string $reply_post_type,
		string $pending_status,
		int $author_id,
		int $topic_id,
		array $ids
	): string;

	/**
	 * Returns null unless the value is a WP_Query.
	 *
	 * @param mixed $query The query, as a hook received it.
	 * @return mixed The value, or null if there is no query to ask.
	 */
	public function get_query_arg( $query, string $key );

	/**
	 * @param mixed $query The query, as a hook received it.
	 * @param mixed $value Value to set.
	 */
	public function set_query_arg( $query, string $key, $value ): void;

	/**
	 * @param string[] $deps    Dependencies.
	 */
	public function enqueue_style( string $handle, string $src, array $deps, string $version ): void;

	/**
	 * @param string[] $deps      Dependencies.
	 */
	public function enqueue_script( string $handle, string $src, array $deps, string $version, bool $in_footer ): void;

	/**
	 * @param array<string,mixed> $data        Data.
	 */
	public function localize_script( string $handle, string $object_name, array $data ): void;

	public function get_ajax_url(): string;

	/**
	 * @return string[]
	 */
	public function get_enqueued_script_handles(): array;

	public function dequeue_script( string $handle ): void;

	public function get_script_src( string $handle ): string;

	/**
	 * @return string[]
	 */
	public function get_enqueued_style_handles(): array;

	public function dequeue_style( string $handle ): void;

	public function get_style_src( string $handle ): string;

	public function get_template_directory_uri(): string;

	public function get_stylesheet_directory_uri(): string;

	public function safe_redirect( string $url, int $status ): void;

	public function terminate(): void;

	/**
	 * @param array<string,mixed> $data   Payload.
	 */
	public function send_json_error( array $data, int $status ): never;

	/**
	 * @param array<string,mixed> $data Payload.
	 */
	public function send_json_success( array $data ): never;
}
