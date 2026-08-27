<?php
/**
 * The WordPress/bbPress seam the REST layer talks through.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\WordPress;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * A second seam, beside ContextInterface, for the things only the API asks.
 *
 * **Why two interfaces rather than a hundred more methods on one.** ContextInterface
 * answers what a *screen* needs — loop cursors, permalinks, humanised dates, rendered
 * markup — and its double, Tests\Support\FakeContext, already implements every one of
 * them. Every method added there has to be faked there, so growing it by the API's
 * whole surface would double the cost of a class that browser tests already carry,
 * and would leave REST tests configuring loop state they never use.
 *
 * The split is by *shape of answer*, not by feature: this seam returns data — IDs,
 * counts, dates in wire format, absolute URLs — where ContextInterface returns
 * whatever a template needs next. REST classes depend on both, and take whichever
 * answers the question; a question ContextInterface already answers is not asked
 * again here, because two seams answering it is how they come to disagree.
 *
 * Everything here is a thin delegation or one prepared query. No policy lives in this
 * file: what a reader may see is Rest\AccessPolicy's and Rest\CollectionVisibility's
 * to decide, and it is decided from what these methods report.
 *
 * @since 0.6.0
 */
interface RestContextInterface {

	/**
	 * Run a post query and report only what a collection needs.
	 *
	 * Always by IDs, always with the total — a REST collection has to send
	 * `X-WP-Total`, and it must be the total *after* visibility filtering, which is
	 * why the count comes from the same query as the page rather than from a second
	 * one built from the same arguments.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $args WP_Query arguments.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function query( array $args ): array;

	/**
	 * One post, or null when there is no such post.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|null
	 */
	public function get_post( int $id ): ?\WP_Post;

	/**
	 * One user, or null when there is no such user.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id User ID.
	 * @return \WP_User|null
	 */
	public function get_user( int $id ): ?\WP_User;

	/**
	 * A topic's tags, as terms.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return \WP_Term[]
	 */
	public function get_topic_tags( int $topic_id ): array;

	/**
	 * How many topics each of these tags carries that this reader may see.
	 *
	 * ⚠ Not `WP_Term::$count`, which counts every topic on the site — including ones
	 * in a forum this reader cannot open, and ones behind a password. A tag whose
	 * count exceeds what its own filtered collection returns is a disclosure in
	 * miniature: it tells a visitor how much they are not being shown.
	 *
	 * One grouped query for the whole set, never one per tag.
	 *
	 * @since 0.6.0
	 *
	 * @param int[]               $term_ids          Tags to count.
	 * @param array<string,mixed> $topic_query_args  Unused by the SQL; carried so a
	 *                                               caller cannot forget the counts are
	 *                                               scoped to a reader, not global.
	 * @return array<int,int> Keyed by term ID; every requested ID present.
	 */
	public function visible_tag_counts( array $term_ids, array $topic_query_args ): array;

	/**
	 * One page of the tag vocabulary this reader can actually reach.
	 *
	 * ⚠ **A term nobody may reach is absent, not zero.** This route *is* an
	 * enumeration — it exists so the app can build a tag filter without scraping IDs
	 * out of topics it happened to fetch — so a term returned with a count of nought
	 * would name a tag that exists somewhere the reader cannot go, and a list of those
	 * names describes the forum they came from. Hence the visibility filter selects
	 * the terms rather than merely counting them.
	 *
	 * The counts come from the same grouped query as the page, and the total is taken
	 * after the same filtering, so the vocabulary, its counts and its `X-WP-Total`
	 * cannot disagree.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $topic_query_args The caller's topic query, for its
	 *                                              statuses — carried for the same
	 *                                              reason `visible_tag_counts()` takes
	 *                                              it, so a caller cannot forget the
	 *                                              vocabulary is a reader's and not the
	 *                                              site's.
	 * @param int                 $page             1-based page number.
	 * @param int                 $per_page         Terms per page, already bounded.
	 * @return array{terms:\WP_Term[],counts:array<int,int>,total:int}
	 */
	public function visible_tags( array $topic_query_args, int $page, int $per_page ): array;

	/**
	 * Whether this forum tags its topics at all.
	 *
	 * ⚠ Asked rather than inferred from whether a topic carries terms. bbPress leaves
	 * the taxonomy *registered* when the setting is off — it only stops using it — so
	 * a topic tagged before the switch was thrown keeps its terms in the database, and
	 * `get_the_terms()` keeps returning them.
	 *
	 * @since 0.6.0
	 *
	 * @return bool
	 */
	public function topic_tags_enabled(): bool;

	/**
	 * The taxonomy bbPress keeps topic tags in.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function topic_tag_taxonomy(): string;

	/**
	 * The avatar for whoever wrote a post, at one size.
	 *
	 * ⚠ An anonymous post carries its author's email in post meta, and that email is
	 * how WordPress resolves the avatar. It goes into the resolver and nowhere else —
	 * never into a response.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 * @param int $size    Pixels.
	 * @return string Absolute URL, or '' when there is none.
	 */
	public function avatar_url( int $post_id, int $size ): string;

	/**
	 * The avatar for a user, at one size.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id User ID.
	 * @param int $size    Pixels.
	 * @return string Absolute URL, or '' when there is none.
	 */
	public function user_avatar_url( int $user_id, int $size ): string;

	/**
	 * A topic's opening post as rendered HTML, exactly as the website renders it.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function rendered_topic_content( int $topic_id ): string;

	/**
	 * A reply as rendered HTML, exactly as the website renders it.
	 *
	 * @since 0.6.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function rendered_reply_content( int $reply_id ): string;

	/**
	 * Whether this post has a password of its own.
	 *
	 * Asks the stored value, not `post_password_required()`: the website unlocks
	 * protected content with a cookie, and the API has no equivalent, so a request
	 * carrying a cookie from a browser session must not unlock content over the API.
	 * The v1 answer to protected content is the same for everyone — 403.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function has_stored_password( int $post_id ): bool;

	/**
	 * Whether this forum, or any forum above it, has a password.
	 *
	 * The whole chain, including the forum itself: a topic in a protected forum is
	 * protected by it, and so is a topic in a forum beneath a protected one.
	 *
	 * @since 0.6.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	public function has_password_in_forum_chain( int $forum_id ): bool;

	/**
	 * The post statuses a forum can legitimately have.
	 *
	 * There are three — public, private and hidden — and a row in any other state is
	 * not a forum anyone is meant to reach. ⚠ Not
	 * `bbp_get_forum_statuses()`, which is bbPress's *open/closed* vocabulary and
	 * unrelated to post status.
	 *
	 * @since 0.6.0
	 *
	 * @return string[]
	 */
	public function forum_post_statuses(): array;

	/**
	 * Every forum this reader may actually open: visible to their capabilities, and
	 * with no password anywhere in its chain.
	 *
	 * The one scope every collection, count and unread roll-up narrows to, computed
	 * once per request. A password on a forum removes the forum *and everything
	 * beneath it*, because reaching a child through its parent is exactly what the
	 * password is there to stop.
	 *
	 * @since 0.6.0
	 *
	 * @return int[]
	 */
	public function readable_forum_ids(): array;

	/**
	 * Narrow a WHERE clause to content inside a set of forums, with no password on the
	 * row itself or on the topic a reply belongs to.
	 *
	 * Wraps rather than appends — ⚠ a bare `AND` after a clause that already contains a
	 * top-level `OR` binds to the last branch alone, which would leave the other
	 * branch unrestricted — the failure mode being a row admitted, not a row lost.
	 *
	 * Returns the clause untouched when it is not already a conjunction, for the same
	 * reason its sibling in ContextInterface does: refusing to edit an unexpected
	 * clause loses rows, editing one wrongly returns the posts table.
	 *
	 * @since 0.6.0
	 *
	 * @param string $where      Clause built so far.
	 * @param int[]  $forum_ids  Forums the reader may open; an empty set admits none
	 *                           of the three bbPress types.
	 * @param string $forum_type Forum post type.
	 * @param string $topic_type Topic post type.
	 * @param string $reply_type Reply post type.
	 * @return string
	 */
	public function collection_where_clause(
		string $where,
		array $forum_ids,
		string $forum_type,
		string $topic_type,
		string $reply_type
	): string;

	/**
	 * Sub-forum, topic and reply counts for a set of forums, counting only what this
	 * reader may see.
	 *
	 * ⚠ Not bbPress's stored `_bbp_topic_count` meta. Those counters are maintained
	 * for the site, not for a reader: they include topics in a forum branch this
	 * reader cannot open, and topics behind a password. A number larger than the list
	 * it labels is a small disclosure with a clear meaning — *there is more in here
	 * than you are being shown* — and it is the number the app puts beside the forum's
	 * name.
	 *
	 * Counted over the readable, password-free scope, in one grouped query per kind
	 * rather than one per row.
	 *
	 * @since 0.6.0
	 *
	 * @param int[]    $forum_ids Forums to count.
	 * @param string[] $statuses  Topic/reply statuses this reader may see.
	 * @return array<int,array{subforums:int,topics:int,replies:int}>
	 */
	public function forum_counts( array $forum_ids, array $statuses ): array;

	/**
	 * Topics a member has favourited.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id Member ID.
	 * @return int[]
	 */
	public function favorite_topic_ids( int $user_id ): array;

	/**
	 * Topics a member subscribes to.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id Member ID.
	 * @return int[]
	 */
	public function subscribed_topic_ids( int $user_id ): array;

	/**
	 * Whether favouriting is switched on site-wide.
	 *
	 * ⚠ The sibling flag, `is_subscriptions_active()`, is on WordPress\ContextInterface
	 * rather than here — the website's Subscribe control needed it in 0.3.0 and the
	 * API reuses that one instead of declaring a second. Favouriting has no website
	 * surface in Bulletin, so this half of the pair arrives here. Two interfaces, one
	 * question each, and no method answering the same thing twice.
	 *
	 * @since 0.6.0
	 *
	 * @return bool
	 */
	public function favorites_enabled(): bool;

	/**
	 * Whether a member has already favourited a topic.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id  Member ID.
	 * @param int $topic_id Topic ID.
	 * @return bool
	 */
	public function is_favorite( int $user_id, int $topic_id ): bool;

	/**
	 * Add a topic to a member's favourites.
	 *
	 * ⚠ **False means "did not happen", including "was already there".** bbPress
	 * bails out of `bbp_add_user_favorite()` when the relationship exists, so this
	 * cannot be called speculatively — ask `is_favorite()` first, or an idempotent
	 * request reports a persistence failure for the one case that is actually fine.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id  Member ID.
	 * @param int $topic_id Topic ID.
	 * @return bool Whether the relationship was written.
	 */
	public function add_favorite( int $user_id, int $topic_id ): bool;

	/**
	 * Take a topic out of a member's favourites.
	 *
	 * ⚠ False again means "did not happen", and again includes "was not there".
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id  Member ID.
	 * @param int $topic_id Topic ID.
	 * @return bool Whether the relationship was removed.
	 */
	public function remove_favorite( int $user_id, int $topic_id ): bool;

	/**
	 * Whether a member subscribes to a forum or a topic.
	 *
	 * One method for both because bbPress has one relationship for both: a
	 * subscription is stored against an object ID, and which kind of object it is
	 * never enters the question.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id   Member ID.
	 * @param int $object_id Forum or topic ID.
	 * @return bool
	 */
	public function is_subscribed( int $user_id, int $object_id ): bool;

	/**
	 * Subscribe a member to a forum or a topic.
	 *
	 * ⚠ False means "did not happen", "was already there" included — the same
	 * pre-check `add_favorite()` needs.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id   Member ID.
	 * @param int $object_id Forum or topic ID.
	 * @return bool Whether the relationship was written.
	 */
	public function add_subscription( int $user_id, int $object_id ): bool;

	/**
	 * Unsubscribe a member from a forum or a topic.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id   Member ID.
	 * @param int $object_id Forum or topic ID.
	 * @return bool Whether the relationship was removed.
	 */
	public function remove_subscription( int $user_id, int $object_id ): bool;

	/**
	 * A post's creation time as an RFC 3339 string in UTC.
	 *
	 * ⚠ WordPress and bbPress store datetimes in the *site's* timezone, so this is a
	 * conversion and not a reformat. `mysql_to_rfc3339()` is not it — despite the
	 * name it emits no offset at all, which invites a client to read a local time as
	 * UTC and be wrong by the site's offset every time.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function post_date_rfc3339( int $post_id ): string;

	/**
	 * A topic's or forum's last-activity time as an RFC 3339 string in UTC.
	 *
	 * One method for both because it is one piece of metadata: bbPress stamps
	 * `_bbp_last_active_time` on forums exactly as it does on topics. Falls back to
	 * the post's own date, which is what a thread nobody has answered was last active.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Topic or forum ID.
	 * @return string
	 */
	public function last_active_rfc3339( int $post_id ): string;

	/**
	 * When a member joined, as an RFC 3339 string in UTC.
	 *
	 * ⚠ `user_registered` is stored in UTC already, unlike a post's date — so this is
	 * a reformat where the others are a conversion. Converting it as if it were local
	 * would move every join date by the site's offset.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function registered_rfc3339( int $user_id ): string;

	/**
	 * How many distinct people have posted in a topic.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return int
	 */
	public function topic_voice_count( int $topic_id ): int;

	/**
	 * Whether a topic is pinned, in its own forum or site-wide.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return bool
	 */
	public function is_topic_sticky( int $topic_id ): bool;

	/**
	 * A WordPress post status as the app's vocabulary, or null when the app is not
	 * told about it at all.
	 *
	 * ⚠ Spam and trash normalise to null and must never reach a response — not as a
	 * status, not as an error reason. Naming them is a free tuning oracle: it tells
	 * whoever posted the content which filter caught it.
	 *
	 * @since 0.6.0
	 *
	 * @param string $status WordPress post status.
	 * @return string|null
	 */
	public function normalize_status( string $status ): ?string;

	/**
	 * Whether the current reader holds a capability against one object.
	 *
	 * The object-aware sibling of `ContextInterface::current_user_can()`. bbPress maps
	 * `edit_forum`, `read_forum` and `assign_topic_tags` per post, so asking without
	 * the object answers a different question from the one the write path asks.
	 *
	 * @since 0.6.0
	 *
	 * @param string $capability Capability name.
	 * @param int    $object_id  Post the capability is asked about.
	 * @return bool
	 */
	public function current_user_can_for( string $capability, int $object_id ): bool;

	/**
	 * The status bbPress marks spam with.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_spam_status_id(): string;

	/**
	 * The status bbPress marks trash with.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_trash_status_id(): string;

	/**
	 * Whether a title exceeds the length bbPress accepts.
	 *
	 * @since 0.6.0
	 *
	 * @param string $title Title to measure.
	 * @return bool
	 */
	public function is_title_too_long( string $title ): bool;

	/**
	 * Whether this content clears the moderation keys.
	 *
	 * ⚠ The two settings are one function with a flag. Strict is bbPress's
	 * *disallowed* list, whose failure means the post is refused outright; non-strict
	 * is the *moderation* list, whose failure means the post is held. Both are asked,
	 * in that order, exactly as the browser handlers ask them.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $author_id Author ID.
	 * @param string $title     Title being written.
	 * @param string $content   Content being written.
	 * @param bool   $strict    True for the disallowed list, false for moderation.
	 * @return bool True when the content passes.
	 */
	public function passes_moderation_check( int $author_id, string $title, string $content, bool $strict ): bool;

	/**
	 * A topic's tags, in the comma-joined form bbPress's own write path passes around.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function get_topic_tag_names( int $topic_id ): string;

	/**
	 * Replace a topic's tags.
	 *
	 * @since 0.6.0
	 *
	 * @param int             $topic_id Topic ID.
	 * @param string|string[] $terms    Comma-joined names, or a list of them.
	 * @return bool Whether the taxonomy accepted them.
	 */
	public function set_topic_tags( int $topic_id, $terms ): bool;

	/**
	 * Let extensions rewrite the terms a new reply is about to set on its topic.
	 *
	 * Load-bearing beyond tagging: Akismet hooks this to stash a spammed reply's terms
	 * in post meta and hand back the topic's existing ones, so a reply later hammed can
	 * be restored. Skipping it would silently drop that.
	 *
	 * @since 0.6.0
	 *
	 * @param string $terms    Terms about to be set.
	 * @param int    $topic_id Topic being tagged.
	 * @param int    $reply_id Reply that occasioned it.
	 * @return string
	 */
	public function filter_new_reply_terms( string $terms, int $topic_id, int $reply_id ): string;

	/**
	 * Trash a post.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function trash_post( int $post_id ): void;

	/**
	 * Record the status a spammed post should return to when it is hammed.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function mark_spam_meta_status( int $post_id ): void;

	/**
	 * Fire `bbp_new_reply`, the action that updates every count, voice and engagement.
	 *
	 * @since 0.6.0
	 *
	 * @param int $reply_id  Reply that was created.
	 * @param int $topic_id  Topic it answers.
	 * @param int $forum_id  Forum that topic sits in.
	 * @param int $author_id Author, as the filtered post data left it.
	 * @param int $reply_to  Reply this one answers, or 0.
	 */
	public function fire_new_reply( int $reply_id, int $topic_id, int $forum_id, int $author_id, int $reply_to ): void;

	/**
	 * Fire a `*_post_extras` action.
	 *
	 * @since 0.6.0
	 *
	 * @param string $hook    Action name.
	 * @param int    $post_id Post that was written.
	 */
	public function fire_post_extras( string $hook, int $post_id ): void;

	/**
	 * Fire a `*_pre_extras` action with the arguments bbPress passes it.
	 *
	 * @since 0.6.0
	 *
	 * @param string $hook Action name.
	 * @param int[]  $args Positional arguments.
	 */
	public function fire_pre_extras( string $hook, array $args ): void;

	/**
	 * Put a different error bag in bbPress's hand, and take the old one back.
	 *
	 * `bbp_add_error()` writes to one process-global `WP_Error` on the bbPress
	 * singleton. Swapping it is how a REST write gives a form-compatible hook somewhere
	 * to complain without inheriting, or leaving behind, anybody else's complaints.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_Error $fresh Bag to install.
	 * @return \WP_Error The bag that was there.
	 */
	public function swap_bbp_errors( \WP_Error $fresh ): \WP_Error;

	/**
	 * Create a nonce for a native bbPress form action.
	 *
	 * @param string $action Nonce action.
	 * @return string Nonce value.
	 */
	public function create_nonce( string $action ): string;

	/**
	 * Invoke one closed-list native bbPress form handler.
	 *
	 * @param string $action Native form action.
	 */
	public function run_bbp_form_handler( string $action ): void;

	/**
	 * Replace browser request globals for a native form-handler call.
	 *
	 * @param array<string,mixed> $values Form values.
	 * @return array<string,mixed> Previous global state.
	 */
	public function swap_handler_globals( array $values ): array;

	/**
	 * Restore browser request globals after a native form-handler call.
	 *
	 * @param array<string,mixed> $previous Previous global state.
	 */
	public function restore_handler_globals( array $previous ): void;

	/**
	 * Put sanitized form values where a form-compatible hook expects to read them.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,scalar> $values Form-equivalent values.
	 * @return array{post:array<string,mixed>,request:array<string,mixed>} What was there.
	 */
	public function swap_request_globals( array $values ): array;

	/**
	 * Put back what `swap_request_globals()` took.
	 *
	 * @since 0.6.0
	 *
	 * @param array{post:array<string,mixed>,request:array<string,mixed>} $previous Prior values.
	 */
	public function restore_request_globals( array $previous ): void;

	/**
	 * How many filters WordPress currently believes it is inside.
	 *
	 * @since 0.6.0
	 *
	 * @return int
	 */
	public function current_filter_depth(): int;

	/**
	 * Tell WordPress it is back out of the filters an exception was thrown through.
	 *
	 * ⚠ `$wp_current_filter` is pushed by `apply_filters()` and popped only when it
	 * returns normally. Throwing out of a callback leaves every enclosing hook name on
	 * that stack for the rest of the process, so `current_filter()` lies afterwards and
	 * the stack grows on every occurrence.
	 *
	 * @since 0.6.0
	 *
	 * @param int $depth Depth recorded before the call that threw.
	 */
	public function unwind_filters( int $depth ): void;

	/**
	 * Whether Akismet is set to discard what it is certain about, rather than hold it.
	 *
	 * @since 0.6.0
	 *
	 * @return bool
	 */
	public function is_akismet_strict(): bool;

	/**
	 * A message with any markup taken out of it.
	 *
	 * Errors are written for a form by bbPress — `<strong>Error</strong>: …` — and an
	 * extension writing one will match that house style. A JSON message is not markup,
	 * and shipping it would put HTML in a field an app is going to render as text.
	 *
	 * @since 0.6.0
	 *
	 * @param string $message Message, possibly carrying markup.
	 * @return string
	 */
	public function strip_markup( string $message ): string;

	/**
	 * A value slashed the way PHP would have handed it to a form handler.
	 *
	 * ⚠ **Not decoration.** WordPress's write path is built on the assumption that
	 * content arrives slashed: `wp_filter_kses()` strips slashes and adds them back,
	 * `bbp_check_for_duplicate()` unslashes before it queries, and `wp_insert_post()`
	 * unslashes immediately before the INSERT. REST hands over an unslashed string, so a
	 * reply containing a quote or a backslash would be run through that pipeline one
	 * unslash too many and reach the database with characters missing. Slashing on the
	 * way in makes the REST lifecycle byte-identical to the browser one.
	 *
	 * @since 0.6.0
	 *
	 * @param string $value Unslashed value.
	 * @return string
	 */
	public function slash( string $value ): string;

	/**
	 * A post's stored title, unfiltered.
	 *
	 * ⚠ **The `raw` context is not optional.** `get_post_field()` defaults to `display`,
	 * which runs `sanitize_post_field()` and so applies the `the_title` filter — the very
	 * filter CLAUDE.md's trap 5 documents bbPress hanging the request inside, when the
	 * post being asked about is a reply with no topic. `raw` is the only context that
	 * escapes it, and an edit reads a stored title on every request that omits one.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_post_title_raw( int $post_id ): string;

	/**
	 * A post's stored body, unfiltered.
	 *
	 * Raw for the same reason as the title, and additionally because the value is about
	 * to be slashed and pushed back through bbPress's own `*_pre_content` chain: a body
	 * that had already been through `the_content` would be filtered twice.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_post_content_raw( int $post_id ): string;

	/**
	 * Update a post, with revision support off for the length of the write.
	 *
	 * Revision support is removed around bbPress's own `wp_update_post()` so the edit does
	 * not leave a duplicate revision behind, and added back afterwards. bbPress does that
	 * with two straight-line statements, which means a fatal between them leaves the site
	 * without revisions for the rest of the process; here the restore is in a `finally`,
	 * so a listener that throws cannot strip a post type of a feature permanently.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $data Post data, already filtered. Must carry `ID`.
	 * @return int|\WP_Error Post ID, or the failure WordPress reported.
	 */
	public function update_post( array $data );

	/**
	 * Fire `bbp_edit_reply`, the action that updates everything an edit touches.
	 *
	 * @since 0.6.0
	 *
	 * @param int $reply_id  Reply that was edited.
	 * @param int $topic_id  Thread it answers.
	 * @param int $forum_id  Forum that thread sits in.
	 * @param int $author_id Its author.
	 * @param int $reply_to  Reply it answers, or 0.
	 */
	public function fire_edit_reply( int $reply_id, int $topic_id, int $forum_id, int $author_id, int $reply_to ): void;

	/**
	 * Record who edited a post, and release the lock the form took out.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post that was edited.
	 */
	public function record_edit( int $post_id ): void;

	/**
	 * Let extensions rewrite the terms an edited reply is about to set on its topic.
	 *
	 * @since 0.6.0
	 *
	 * @param string $terms    Terms about to be set.
	 * @param int    $topic_id Topic being tagged.
	 * @param int    $reply_id Reply being edited.
	 * @return string
	 */
	public function filter_edit_reply_terms( string $terms, int $topic_id, int $reply_id ): string;
}
