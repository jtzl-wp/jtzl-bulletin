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

	/**
	 * Whether the request is bbPress's merge-topic form.
	 *
	 * The three moderation forms are each built on top of an edit request — merge and
	 * split are `bbp_is_topic_edit()` plus an `action` parameter, move is
	 * `bbp_is_reply_edit()` plus one — so they need asking about separately from the
	 * edit screens the posting phase owns (issue #36).
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_topic_merge(): bool;

	/**
	 * Whether the request is bbPress's split-topic form.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_topic_split(): bool;

	/**
	 * Whether the request is bbPress's move-reply form.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_reply_move(): bool;

	/**
	 * Whether this is a member profile's Subscriptions tab.
	 *
	 * The one reskin screen that reaches `bbp_has_forums()`, so it is what scopes
	 * the forum-list paging Query\SubscribedForumQuery adds (issue #50). bbPress
	 * reads it from the main query, which is also what makes it answerable inside
	 * its own AJAX request: `bbp_get_ajax_url()` posts back to the page's own URL,
	 * so the request is routed to the same profile tab.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_subscriptions(): bool;

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
	 * Whether the current user may edit a given user.
	 *
	 * Separate from current_user_can() because this capability is only meaningful
	 * against a subject, and it is the test bbPress itself uses to decide who may
	 * see a profile's private tabs (`user-subscriptions.php` gates its whole
	 * section on `bbp_is_user_home() || current_user_can( 'edit_user', … )`). The
	 * subscribed-forums continuation applies the same pair, so a moderator keeps
	 * the access the screen already grants and nobody else gains any.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id Subject user ID.
	 * @return bool
	 */
	public function current_user_can_edit_user( int $user_id ): bool;

	/**
	 * Whether the current user may moderate a given forum post.
	 *
	 * The one gate on the reading view's moderation mode, and deliberately coarser
	 * than bbPress's own per-link tests. bbPress lets each admin link answer for
	 * itself, which means a participant inside the edit window gets an "Edit" link on
	 * their own post — a *posting* affordance, and this release has no composer to
	 * edit in (that is P4). Gating the whole mode on `moderate` keeps the reading view
	 * free of controls for everyone who is not moderating, which is what issue #36
	 * asks for; the per-link tests still run inside, so a Moderator sees a narrower
	 * set than a Keymaster without us enumerating either.
	 *
	 * @since 0.3.0
	 *
	 * @param int $post_id Topic or reply ID the moderation would act on.
	 * @return bool
	 */
	public function current_user_can_moderate( int $post_id ): bool;

	/**
	 * Moderation links for a topic, in bbPress's own markup.
	 *
	 * Rendered by bbPress rather than re-authored, so the nonces, the capability
	 * tests each link runs, and the `confirm()` it puts on permanent delete (and
	 * correctly omits on reversible trash) all arrive intact — we restyle the result.
	 * The posting link is dropped: `reply` opens a composer this release does not have.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string Markup, or '' when the user may do nothing.
	 */
	public function get_topic_moderation_links( int $topic_id ): string;

	/**
	 * Moderation links for a reply, in bbPress's own markup.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string Markup, or '' when the user may do nothing.
	 */
	public function get_reply_moderation_links( int $reply_id ): string;

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
	 * ID of the user whose profile is being viewed (0 when none is).
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_displayed_user_id(): int;

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

	// --- bbPress forum getters ----------------------------------------------

	/**
	 * ID of the forum currently in the forums loop.
	 *
	 * Ambient, and only meaningful inside one: bbPress resolves this from the forum
	 * loop first and the viewed forum second, so on a screen that runs both it
	 * answers differently before and after the loop.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_forum_id(): int;

	/**
	 * A forum's permalink.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_permalink( int $forum_id ): string;

	/**
	 * A forum's title.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_title( int $forum_id ): string;

	/**
	 * A forum's description, as bbPress renders it.
	 *
	 * Returned with its markup intact, because bbPress masks this for a
	 * password-protected forum and callers should strip rather than re-derive it.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_content( int $forum_id ): string;

	/**
	 * A forum's topic count, sub-forum topics included.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return int
	 */
	public function get_forum_topic_count( int $forum_id ): int;

	/**
	 * ID of the last active post in a forum (0 when it has never been posted in).
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return int
	 */
	public function get_forum_last_active_id( int $forum_id ): int;

	/**
	 * Human-readable time of a forum's last activity.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_last_active_time( int $forum_id ): string;

	/**
	 * Whether a forum is closed to new content.
	 *
	 * Ancestors count, which is bbPress's own default: a forum inside a closed
	 * category is closed too, and answering otherwise would call a forum open
	 * that nothing can be posted to.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	public function is_forum_closed( int $forum_id ): bool;

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
	 * The reply a reply answers, or 0 when it answers the thread itself.
	 *
	 * Reads `_bbp_reply_to` directly, so unlike the value bbp_has_replies() hangs on
	 * each post it is NOT normalised against the thread: bbPress zeroes a parent that
	 * turns out to be the topic itself before its loop sees it, and this does not.
	 * Callers check that themselves — see View\ReplyView, which has to establish the
	 * parent belongs to this thread anyway.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return int Parent reply ID, or 0.
	 */
	public function get_reply_to( int $reply_id ): int;

	/**
	 * The thread a reply belongs to.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return int Topic ID, or 0 when the ID is not a reply.
	 */
	public function get_reply_topic_id( int $reply_id ): int;

	/**
	 * Every reply of a topic this reader may see, mapped to the reply it answers.
	 *
	 * The input to Query\ReplyOrder, and the only unbounded query the reading view
	 * makes — so it asks for IDs and one meta key, never post objects or content,
	 * and Query\ReplyQuery only calls it when threading is actually on.
	 *
	 * "May see" is bbPress's own answer, not ours: this runs through
	 * bbp_has_replies(), so the status and `perm` handling that decides whether a
	 * moderator sees trashed and spammed replies is the same code that decides it
	 * for the rendered page. A reader and a moderator therefore get orders built
	 * from their own sets, and a parent missing from one of them is an orphan there
	 * — which ReplyOrder promotes to a root rather than dropping.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic to read.
	 * @return array<int,int> Reply ID => parent reply ID (0 for a reply to the
	 *                        thread), in (date, ID) order.
	 */
	public function get_reply_parents( int $topic_id ): array;

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
	 * Whether a topic is closed to new replies.
	 *
	 * The topic's own status only. A topic in a closed forum takes no replies
	 * either, but bbPress reports that against the forum, and so do we — the
	 * forum row and forum header carry it (issue #38).
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return bool
	 */
	public function is_topic_closed( int $topic_id ): bool;

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
	 * Forums shown per page.
	 *
	 * Unlike topics and replies, this one has no accessor in bbPress, so the option
	 * is read directly — with bbPress's own default and floor.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_forums_per_page(): int;

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

	// --- WordPress post & meta ---------------------------------------------

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

	// --- bbPress query & replies loop --------------------------------------

	/**
	 * Prime the forums loop.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query args (see Query\ForumQuery).
	 * @return bool Whether any forums matched.
	 */
	public function has_forums( array $args ): bool;

	/**
	 * Prime the forums loop with a user's subscribed forums.
	 *
	 * Goes through bbPress rather than assembling the relationship itself, because
	 * how a subscription is *stored* is a site's choice: bbPress dispatches to a
	 * meta, taxonomy or user-option strategy, so the same subscription arrives as a
	 * `meta_query`, a `tax_query` or a `post__in` depending on which one is in use
	 * (`BBP_User_Engagements_*::get_query()`). Only the paging is ours; the user and
	 * the relationship stay bbPress's own, resolved from the profile the request is
	 * routed to.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query args (see Query\SubscribedForumQuery).
	 * @return bool Whether any subscribed forums matched.
	 */
	public function has_forum_subscriptions( array $args ): bool;

	/**
	 * Advance the forums loop.
	 *
	 * @since 0.3.0
	 *
	 * @return bool Whether a forum remains.
	 */
	public function the_forums_loop(): bool;

	/**
	 * Set up the current forum in the loop.
	 *
	 * @since 0.3.0
	 */
	public function the_forum(): void;

	/**
	 * The number of forum pages from the last forums query.
	 *
	 * Only answerable because Query\ForumQuery switches bbPress's `no_found_rows`
	 * back off — with it on, the row count is never computed and this is always 0.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_max_forum_pages(): int;

	/**
	 * Render bbPress's own row for the forum the loop is on.
	 *
	 * Used by the subscribed-forums continuation, where the rows that shipped with
	 * the document are bbPress's `loop-single-forum.php` — the reskin tier restyles
	 * that markup rather than replacing it, so an appended row has to be the same
	 * template, not a copy of it. The template stack means a theme's override wins
	 * here exactly as it does on page 1.
	 *
	 * @since 0.3.0
	 */
	public function render_forum_row(): void;

	/**
	 * IDs of the forums a user subscribes to.
	 *
	 * Reads the relationship directly rather than through a paged query, so the
	 * continuation can bound a requested page against the set that exists before it
	 * issues an offset query for it.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public function get_subscribed_forum_ids( int $user_id ): array;

	/**
	 * Whether subscriptions are switched on site-wide.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_subscriptions_active(): bool;

	/**
	 * Restore the global post after a secondary loop.
	 *
	 * Load-bearing after a forums loop specifically: bbPress resolves the ambient
	 * forum ID from the forum loop before the viewed forum, so a single-forum screen
	 * that lists sub-forums reads the last sub-forum as "the forum" until this runs.
	 *
	 * @since 0.3.0
	 */
	public function reset_postdata(): void;

	/**
	 * Where a topic sits in its forum's freshness order, and its neighbours.
	 *
	 * Immediate forum only — topics in sub-forums are not folded in. Bounded by
	 * design: this answers with four integers no matter how large the forum is,
	 * where the array it replaced grew with the topic count (issue #59).
	 *
	 * Position is 0 and both neighbours are 0 when the topic is not a member of
	 * the ordered set — trashed, spammed, or in another forum.
	 *
	 * @since 0.3.0
	 *
	 * @param int      $forum_id Forum ID.
	 * @param int      $topic_id Topic to locate within it.
	 * @param string[] $statuses Post statuses to include.
	 * @return array{total:int,position:int,prev_id:int,next_id:int}
	 */
	public function get_topic_rank( int $forum_id, int $topic_id, array $statuses ): array;

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
	 * IDs of the site-wide super stickies, which are pinned into every forum.
	 *
	 * Returned separately from the union above because they outrank a forum's own
	 * stickies: bbPress renders supers first (bbp_add_sticky_topics partitions the
	 * two after sorting), and the pinned section does the same.
	 *
	 * @since 0.3.0
	 *
	 * @return int[]
	 */
	public function get_super_sticky_ids(): array;

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
