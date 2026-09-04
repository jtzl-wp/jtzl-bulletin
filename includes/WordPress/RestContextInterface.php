<?php
/**
 * REST context contract.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\WordPress;

// phpcs:disable Generic.Commenting, Squiz.Commenting -- Contract-only and PHPStan-only docblocks intentionally omit redundant prose.

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

interface RestContextInterface {

	/**
	 * IDs and totals come from the same visibility-filtered query.
	 *
	 * @param array<string,mixed> $args WP_Query arguments.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function query( array $args ): array;

	/**
	 * @return \WP_Post|null
	 */
	public function get_post( int $id ): ?\WP_Post;

	/**
	 * @return \WP_User|null
	 */
	public function get_user( int $id ): ?\WP_User;

	/**
	 * Enforces the requested search columns for the duration of one query.
	 *
	 * @param array<string,mixed> $args WP_User_Query arguments.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function user_query( array $args ): array;

	/**
	 * @return \WP_Term[]
	 */
	public function get_topic_tags( int $topic_id ): array;

	/**
	 * Counts only topics visible to the reader; every requested term ID is present.
	 *
	 * @param int[]               $term_ids          Tags to count.
	 * @param array<string,mixed> $topic_query_args  Reader-scoped topic query arguments.
	 * @return array<int,int> Keyed by term ID; every requested ID present.
	 */
	public function visible_tag_counts( array $term_ids, array $topic_query_args ): array;

	/**
	 * Omits unreachable terms; counts and totals use the same reader-visible scope.
	 *
	 * @param array<string,mixed> $topic_query_args The caller's reader-scoped topic query.
	 * @return array{terms:\WP_Term[],counts:array<int,int>,total:int}
	 */
	public function visible_tags( array $topic_query_args, int $page, int $per_page ): array;

	/**
	 * Uses the bbPress setting, because disabling tags leaves the taxonomy registered.
	 */
	public function topic_tags_enabled(): bool;

	public function topic_tag_taxonomy(): string;

	/**
	 * Anonymous author email may resolve the avatar but must not enter the response.
	 */
	public function avatar_url( int $post_id, int $size ): string;

	public function user_avatar_url( int $user_id, int $size ): string;

	public function rendered_topic_content( int $topic_id ): string;

	public function rendered_reply_content( int $reply_id ): string;

	/**
	 * Checks stored state, not password cookies; API access remains locked.
	 */
	public function has_stored_password( int $post_id ): bool;

	/**
	 * Checks the forum and every ancestor.
	 */
	public function has_password_in_forum_chain( int $forum_id ): bool;

	/**
	 * Returns post statuses, not bbPress open/closed states.
	 *
	 * @return string[]
	 */
	public function forum_post_statuses(): array;

	/**
	 * Excludes capability-inaccessible and password-protected forum branches.
	 *
	 * @return int[]
	 */
	public function readable_forum_ids(): array;

	/**
	 * Wraps a conjunctive clause to preserve OR precedence; unexpected clauses are returned unchanged.
	 *
	 * @param int[] $forum_ids Forums the reader may open; an empty set admits none of the bbPress types.
	 */
	public function collection_where_clause(
		string $where,
		array $forum_ids,
		string $forum_type,
		string $topic_type,
		string $reply_type
	): string;

	/**
	 * Counts only reader-visible, password-free content.
	 *
	 * @param int[]    $forum_ids Forums to count.
	 * @param string[] $statuses  Topic/reply statuses this reader may see.
	 * @return array<int,array{subforums:int,topics:int,replies:int}>
	 */
	public function forum_counts( array $forum_ids, array $statuses ): array;

	/**
	 * @return int[]
	 */
	public function favorite_topic_ids( int $user_id ): array;

	/**
	 * @return int[]
	 */
	public function subscribed_topic_ids( int $user_id ): array;

	public function favorites_enabled(): bool;

	public function is_favorite( int $user_id, int $topic_id ): bool;

	/**
	 * False also means the relationship already existed.
	 */
	public function add_favorite( int $user_id, int $topic_id ): bool;

	/**
	 * False also means the relationship did not exist.
	 */
	public function remove_favorite( int $user_id, int $topic_id ): bool;

	public function is_subscribed( int $user_id, int $object_id ): bool;

	/**
	 * False also means the relationship already existed.
	 */
	public function add_subscription( int $user_id, int $object_id ): bool;

	public function remove_subscription( int $user_id, int $object_id ): bool;

	/**
	 * Converts the site-local stored date to RFC 3339 UTC.
	 */
	public function post_date_rfc3339( int $post_id ): string;

	/**
	 * Converts site-local activity to RFC 3339 UTC and falls back to the post date.
	 */
	public function last_active_rfc3339( int $post_id ): string;

	/**
	 * Reformats user_registered, which WordPress stores in UTC.
	 */
	public function registered_rfc3339( int $user_id ): string;

	public function topic_voice_count( int $topic_id ): int;

	public function is_topic_sticky( int $topic_id ): bool;

	/**
	 * Spam and trash normalize to null to avoid disclosing moderation outcomes.
	 */
	public function normalize_status( string $status ): ?string;

	/**
	 * Checks a capability against the specified object.
	 */
	public function current_user_can_for( string $capability, int $object_id ): bool;

	public function get_spam_status_id(): string;

	public function get_trash_status_id(): string;

	/**
	 * Swaps bbPress’s process-global error bag and returns the previous bag.
	 *
	 * @param \WP_Error $fresh Bag to install.
	 * @return \WP_Error The bag that was there.
	 */
	public function swap_bbp_errors( \WP_Error $fresh ): \WP_Error;

	public function create_nonce( string $action ): string;

	/**
	 * Accepts only the supported native bbPress form actions.
	 */
	public function run_bbp_form_handler( string $action ): void;

	/**
	 * Returns browser globals that must be restored after the handler call.
	 *
	 * @param array<string,mixed> $values Form values.
	 * @return array<string,mixed> Previous global state.
	 */
	public function swap_handler_globals( array $values ): array;

	/**
	 * Restores browser globals after the handler call.
	 *
	 * @param array<string,mixed> $previous Previous global state.
	 */
	public function restore_handler_globals( array $previous ): void;

	public function current_filter_depth(): int;

	/**
	 * Restores WordPress filter-stack depth after an exception escapes a callback.
	 */
	public function unwind_filters( int $depth ): void;

	public function is_akismet_strict(): bool;

	public function strip_markup( string $message ): string;

	/**
	 * Applies the slashing expected by native WordPress and bbPress write handlers.
	 */
	public function slash( string $value ): string;

	/**
	 * Raw context avoids recursion through the_title.
	 */
	public function get_post_title_raw( int $post_id ): string;

	/**
	 * Raw content avoids filtering before the native write pipeline.
	 */
	public function get_post_content_raw( int $post_id ): string;

	/**
	 * Writes only the sanitized display name; callers cannot widen the user update.
	 */
	public function update_display_name( int $user_id, string $name ): bool;
}
