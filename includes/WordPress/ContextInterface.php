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
	 * Remove a filter registered as a closure.
	 *
	 * The sibling of `remove_filter()`, which takes a string function name and so
	 * cannot name one. A callback installed for the length of a single call — the
	 * REST write path installs one around Akismet's pre-insert filter — has to be
	 * removed by identity, and leaving it in place would apply it to every later
	 * write in the request.
	 *
	 * @since 0.6.0
	 *
	 * @param string   $hook     Filter name.
	 * @param callable $callback Callback to remove.
	 * @param int      $priority Priority it was added at.
	 */
	public function remove_filter_callback( string $hook, callable $callback, int $priority ): void;

	/**
	 * Apply filters to a value.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  $value   Value to filter.
	 * @param mixed  ...$args Further arguments the hook passes to its listeners.
	 * @return mixed Filtered value.
	 */
	public function apply_filters( string $hook, $value, ...$args );

	/**
	 * Register one REST route under a namespace.
	 *
	 * Wrapped for the reason `add_action()` is: it binds something to WordPress
	 * rather than answering a question, and a controller that called the global
	 * directly could only be checked by booting a REST server. Through the seam, the
	 * routes a controller declares — their paths, methods, permission callbacks and
	 * argument schemas — are readable in a unit test, and the integration tests are
	 * then free to prove the dispatcher agrees rather than being the only witness.
	 *
	 * @since 0.6.0
	 *
	 * @param string              $route_namespace Route namespace, e.g. `jtzl-bulletin/v1`.
	 * @param string              $route           Route pattern, e.g. `/forums/(?P<id>[\d]+)`.
	 * @param array<string,mixed> $args            Route arguments, as register_rest_route() takes them.
	 */
	public function register_rest_route( string $route_namespace, string $route, array $args ): void;

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
	 * Whether this is the create/edit-forum form (keymaster administration).
	 *
	 * The one bbPress front-end screen Bulletin still leaves to the active theme.
	 *
	 * ⚠ This docblock used to add that it was "the only edit conditional the seam
	 * needs", because topic and reply edit reskin by *falling through* the classifier
	 * rather than by being asked about. True until 0.5.2, when the two below acquired
	 * a caller: an edit screen has to know which post it is editing so the reader can
	 * get back to it.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_forum_edit(): bool;

	/**
	 * Whether this request is the topic edit form.
	 *
	 * ⚠ **True on the moderation forms as well**, and that is bbPress's design rather
	 * than a leak: `bbp_is_topic_merge()` and `bbp_is_topic_split()` are this
	 * conditional plus an `action` parameter (`common/template.php:317`, `:338`). So a
	 * caller asking "is a topic being edited here" gets yes on all three — which is
	 * exactly right for deciding where the reader goes when they leave, since all
	 * three leave to the same topic.
	 *
	 * @since 0.5.3
	 *
	 * @return bool
	 */
	public function is_topic_edit(): bool;

	/**
	 * Whether this request is the reply edit form.
	 *
	 * ⚠ **True on the reply move form too**, for the reason given above:
	 * `bbp_is_reply_move()` is this plus an `action` parameter
	 * (`common/template.php:505`).
	 *
	 * @since 0.5.3
	 *
	 * @return bool
	 */
	public function is_reply_edit(): bool;

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

	/**
	 * Whether this is bbPress's search screen.
	 *
	 * The screen, in both its states: with terms, and the bare form at
	 * `/forums/search/`. `bbp_is_search_results()` is deliberately NOT part of the
	 * pair — it answers true whenever `$_REQUEST['bbp_search']` is set on any
	 * request at all, so classifying on it would flip a profile or a tag archive
	 * into the search screen the moment that query string rode along.
	 *
	 * It already answers false when a site has turned search off, so nothing
	 * downstream has to ask twice.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_search(): bool;

	/**
	 * Whether the site allows searching.
	 *
	 * Only the entry point needs this separately from is_search(): the magnifier
	 * is rendered on screens that are not the search screen, so it cannot infer
	 * the setting from where it is.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function allow_search(): bool;

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
	 * their own post. Gating the whole mode on `moderate` keeps the reading view free
	 * of controls for everyone who is not moderating, which is what issue #36 asks
	 * for; the per-link tests still run inside, so a Moderator sees a narrower set
	 * than a Keymaster without us enumerating either.
	 *
	 * ⚠ Since 0.5.0 that participant's Edit is *rendered*, by `View\AuthorEdit`, in
	 * the byline rather than in this mode — and only for a reader this method answers
	 * `false` for. The gate is unchanged; what changed is that its suppression is no
	 * longer the last word on the subject (§3 decision 10).
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
	 * The author's own "Edit" link for a topic, in bbPress's own markup.
	 *
	 * Asked of bbPress rather than assembled here, because the question is harder
	 * than it looks and bbPress already answers it: `bbp_get_topic_edit_link()` runs
	 * the `edit_topic` capability, the `_bbp_edit_lock` window against the post's GMT
	 * date, and `bbp_get_topic_edit_url()`, and returns nothing at all when any of
	 * them declines. Re-deriving the window here would mean re-implementing
	 * `bbp_past_edit_lock()`, whose "0 minutes means forever" branch is exactly the
	 * case a hand-rolled subtraction gets wrong.
	 *
	 * ⚠ **It is not a pure "may this author edit" test.** bbPress bypasses the whole
	 * block — the lock included — for anyone holding `edit_others_topics`, so a
	 * moderator gets a link that never expires. `View\AuthorEdit` is what makes the
	 * answer meaningful, by declining to ask on behalf of a moderator at all.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string Markup, or '' when this reader may not edit it.
	 */
	public function get_topic_edit_link( int $topic_id ): string;

	/**
	 * The author's own "Edit" link for a reply, in bbPress's own markup.
	 *
	 * @since 0.5.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string Markup, or '' when this reader may not edit it.
	 */
	public function get_reply_edit_link( int $reply_id ): string;

	/**
	 * The topic statuses bbPress considers public — `publish` and `closed`.
	 *
	 * Asked rather than hardcoded, for the reason
	 * {@see self::get_readable_topic_statuses()} gives at greater length: the list is
	 * filterable, and `closed` being in it is the load-bearing part. A closed thread
	 * is a fully public one in bbPress's model, so a rule written as "status is
	 * `publish`" would withhold the author's Edit from a thread a moderator happened
	 * to close inside the window.
	 *
	 * @since 0.5.0
	 *
	 * @return array<int,string>
	 */
	public function get_public_topic_statuses(): array;

	/**
	 * The reply statuses bbPress considers public — `publish` alone.
	 *
	 * The complement is what matters: `pending`, `spam` and `trash` are all out, which
	 * is how one test covers the "Awaiting review" exclusion without naming it.
	 *
	 * @since 0.5.0
	 *
	 * @return array<int,string>
	 */
	public function get_public_reply_statuses(): array;

	/**
	 * The status bbPress gives a post held for moderation.
	 *
	 * Asked for rather than written as `'pending'` for the same reason the public
	 * status lists are: it is bbPress's vocabulary, filterable at source, and this
	 * plugin's job is to speak it rather than to restate it.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_pending_status_id(): string;

	/**
	 * The user ID a post is attributed to, or 0.
	 *
	 * Zero is a real answer, not an error: an anonymous post carries `post_author = 0`
	 * and its identity in post meta, which is why every caller here treats zero as
	 * "nobody" rather than as a user to match.
	 *
	 * @since 0.5.0
	 *
	 * @param int $post_id Post to ask about.
	 * @return int
	 */
	public function get_post_author( int $post_id ): int;

	/**
	 * The post statuses WordPress admits to a status-less query only because it
	 * believes the request is an admin screen.
	 *
	 * Asked of WordPress rather than written out, because the list is what the
	 * defect is defined against: `Query\ProtectedStatusGuard` subtracts exactly what
	 * `WP_Query`'s `is_admin` branch added, and a hard-coded copy would drift from it
	 * the moment a plugin registers another protected status.
	 *
	 * @since 0.5.0
	 *
	 * @return string[]
	 */
	public function get_admin_only_statuses(): array;

	/**
	 * A WHERE fragment withholding every row in a list of statuses.
	 *
	 * @since 0.5.0
	 *
	 * @param string[] $statuses Statuses to withhold.
	 * @return string A fragment beginning with AND, or '' if there is nothing to say.
	 */
	public function status_exclusion_where_clause( array $statuses ): string;

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
	 * The URL of the request being served, query string included.
	 *
	 * The composer's sign-in control needs it so `wp_login_url()` can return a reader
	 * to the thread they were on rather than to the forums index. The query string is
	 * part of it and not an implementation detail: `?bbp_reply_to={id}` names the post
	 * a reader meant to answer, and dropping it across the login round-trip silently
	 * turns a reply-to-a-post into a reply-to-the-thread.
	 *
	 * bbPress reaches the same value through `bbp_redirect_to_field()`, which defaults
	 * to `REQUEST_URI` (`common/template.php:1345`); this is that behaviour on our own
	 * tier, where we author the control rather than render bbPress's form.
	 *
	 * @since 0.5.0
	 *
	 * @return string Absolute URL, or '' when the request cannot supply one.
	 */
	public function get_current_url(): string;

	/**
	 * Whether bbPress would render a reply form for the current user, here.
	 *
	 * One question, asked of bbPress rather than re-derived: it already folds together
	 * keymaster status, an open topic, an open forum, a published topic, the
	 * `publish_replies` capability and the anonymous-posting option
	 * (`users/template.php:2320`, `:2194`). Re-implementing any of that would be a
	 * second opinion about who may post, which is the one thing a companion plugin
	 * must never hold.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function can_access_create_reply_form(): bool;

	/**
	 * Whether bbPress would render a topic form for the current user, here.
	 *
	 * The topic twin of the reply test, and asked for the same reason: it already folds
	 * together keymaster status, an open forum, the `publish_topics` capability and the
	 * anonymous-posting option (`users/template.php:2291`). Re-implementing any of it
	 * would be a second opinion about who may post.
	 *
	 * ⚠ **It does NOT answer for categories, and callers must ask separately.** Its
	 * forum test is `bbp_is_forum_open()`, which is only `! bbp_is_forum_closed()`
	 * (`forums/template.php:1516`) and says nothing about type — so on a category it
	 * returns true, bbPress renders a topic form, and `bbp_new_topic_handler()` then
	 * refuses the post outright: *"This forum is a category. No topics can be created
	 * in this forum."* (`topics/functions.php:222`). See `is_forum_category()`.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function can_access_create_topic_form(): bool;

	/**
	 * Whether a forum is a container for other forums rather than for topics.
	 *
	 * Asked because `can_access_create_topic_form()` does not, and a category is the one
	 * place on this screen where bbPress would render a form it will not accept a post
	 * from — a control leading straight to a refusal, which is the shape
	 * `Chrome\ReplyToLink` exists to prevent.
	 *
	 * @since 0.5.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	public function is_forum_category( int $forum_id ): bool;

	/**
	 * The reply this request asked to answer, or 0.
	 *
	 * ⚠ **Not `bbp_get_form_reply_to()`, and the difference is the whole reason this
	 * exists.** That helper falls back to `bbp_get_reply_to()` — the *current reply in
	 * the loop's* stored parent (`replies/template.php:2498`) — so asked from anywhere
	 * a reply loop has run it answers a question about bbPress's loop state rather than
	 * about the request. What the compose slot needs to know is narrower and stable:
	 * did the reader arrive by following "Reply To" on a specific post.
	 *
	 * Validated through `bbp_validate_reply_to()`, so a request naming a topic, a
	 * deleted post or a non-reply gets 0 — the same answer bbPress's own form will
	 * reach, rather than a second opinion about it.
	 *
	 * @since 0.5.0
	 *
	 * @return int Reply ID, or 0 when the request named none.
	 */
	public function get_requested_reply_to(): int;

	/**
	 * Whether this request carries a query argument at all.
	 *
	 * **Presence only, deliberately.** The one caller is the held-reply
	 * acknowledgement, which must never distinguish `pending` from `spam` in
	 * anything a reader can read (§3 decision 6) — so the value is not returned
	 * here, and there is nothing for a later caller to start branching on.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key Argument name.
	 * @return bool
	 */
	public function has_query_flag( string $key ): bool;

	/**
	 * A URL with one argument added.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key   Argument name.
	 * @param string $value Argument value.
	 * @param string $url   URL to add it to.
	 * @return string
	 */
	public function add_query_arg( string $key, string $value, string $url ): string;

	/**
	 * Whether bbPress is holding an error to show on this request.
	 *
	 * The signal `bbp_template_notices()` itself branches on, asked separately because
	 * the compose slot has to make a decision **before** that notice renders: a form
	 * carrying a rejection cannot rest collapsed, or the rejection is invisible and
	 * submitting appears to do nothing at all.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function has_errors(): bool;

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
	 * The name bbPress would give the current screen.
	 *
	 * This is computed by bbPress for every screen it owns — a member's profile, a tag
	 * archive, a registered view, a search — but hung on the legacy `wp_title`
	 * filter only, which `wp_get_document_title()` never calls. So the answer exists
	 * and simply never reaches a document that titles itself the modern way. Asked
	 * for with empty separators, so it returns the name alone: WordPress adds the
	 * site name and the separator itself.
	 *
	 * @since 0.3.0
	 *
	 * @return string Screen name, or '' where bbPress has none.
	 */
	public function get_bbpress_screen_title(): string;

	/**
	 * The forums index URL.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_forums_url(): string;

	/**
	 * The search screen's URL, with no terms.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_search_url(): string;

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
	 * A forum's description as plain text, with its markup removed.
	 *
	 * The API publishes a description as a string a client will put in a label, so
	 * the stripping belongs on this side of the seam rather than in a serializer:
	 * a caller that has to remember to strip is a caller that will one day forget,
	 * and the markup would reach the app as literal tags.
	 *
	 * @since 0.6.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return string
	 */
	public function get_forum_description( int $forum_id ): string;

	/**
	 * The forum a forum sits in, or 0 for a top-level one.
	 *
	 * @since 0.6.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return int
	 */
	public function get_forum_parent_id( int $forum_id ): int;

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
	 * A reply's stored title, unfiltered, or '' when it has none.
	 *
	 * The field holds bbPress's "Reply To: {thread}", written at insert time, and
	 * nothing normally shows it — a reply is displayed under the thread it belongs
	 * to. Search is where that breaks down: a result naming its thread has nothing to
	 * name when the reply has lost it.
	 *
	 * ⚠ It has to be the raw field. `bbp_get_reply_title()` **does not return** on a
	 * reply with no thread — bbPress hangs, and takes the request with it. Measured:
	 * an empty title runs `bbp_get_reply_title_fallback()`, which asks
	 * `bbp_get_reply_topic_title()`, which asks `bbp_get_topic_title( 0 )`, which asks
	 * `bbp_get_topic_id( 0 )` — and inside a search loop whose current post is a
	 * reply, that resolution walks back to the reply's own topic and starts again.
	 * Xdebug stops it at 512 frames; production would not.
	 *
	 * So this is `get_post_field()`, which runs no filters and therefore cannot enter
	 * that loop, and callers handle '' themselves.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function get_reply_title( int $reply_id ): string;

	/**
	 * A short plain-text excerpt of a reply.
	 *
	 * Search is the one screen that shows one. A reply result is titled with the
	 * *thread* it sits in, which need not contain the search terms at all, so
	 * without the reply's own words the row cannot say why it is in the list.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @param int $length   Maximum length in characters.
	 * @return string
	 */
	public function get_reply_excerpt( int $reply_id, int $length ): string;

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
	 * @param int                 $topic_id   Topic to read.
	 * @param array<string,mixed> $extra_args Arguments merged into the query before
	 *                                        bbPress parses it. The reading view
	 *                                        passes Query\PendingVisibility's marker
	 *                                        here, because the seam may not reach the
	 *                                        Query layer to arm itself and the two
	 *                                        reply queries have to agree.
	 * @return array<int,int> Reply ID => parent reply ID (0 for a reply to the
	 *                        thread), in (date, ID) order.
	 */
	public function get_reply_parents( int $topic_id, array $extra_args = array() ): array;

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
	 * Topic statuses this reader is entitled to see.
	 *
	 * There is no single bbPress helper for this — it assembles the list inline in
	 * `bbp_has_search_results()` (`includes/search/template.php:50-62`), and this
	 * mirrors that assembly exactly: the public topic statuses, plus `private` with
	 * `read_private_topics`, plus `hidden` with `read_hidden_topics`.
	 *
	 * Mirroring rather than capturing is the difference from Query\SearchVisibility,
	 * which declines to recompute because bbPress had already computed the list for
	 * that query and thrown it away. Nothing computes it for a query we build
	 * ourselves, so the choice is to ask in bbPress's own terms or to hardcode two
	 * statuses and be wrong for a moderator.
	 *
	 * @since 0.5.0
	 *
	 * @return array<int,string>
	 */
	public function get_readable_topic_statuses(): array;

	/**
	 * Forum IDs this reader may not see, as bbPress computes them.
	 *
	 * `bbp_get_excluded_forum_ids()` is capability-aware — it returns private forums
	 * only when the reader lacks `read_private_forums`, hidden ones only when they
	 * lack `read_hidden_forums`, and nothing at all to a keymaster — so the answer is
	 * per request, not per site, and must not be cached across users.
	 *
	 * @since 0.5.0
	 *
	 * @return array<int,int>
	 */
	public function get_excluded_forum_ids(): array;

	/**
	 * A topic's last-activity time as a raw MySQL datetime.
	 *
	 * Distinct from get_topic_last_active_time(), which humanizes it for a byline.
	 * Unread compares this against a stored read time, so it needs the stored value
	 * rather than "3 days ago" — and stores the same string it compares, so the two
	 * sides of that comparison can never disagree about format or timezone.
	 *
	 * Falls back to the topic's own post date when the meta is missing or empty, which
	 * is what bbPress itself falls back to: a topic nobody has replied to was last
	 * active when it was written. Unread\ReadState's SQL applies the same COALESCE, so
	 * the read path and the write path agree about a topic bbPress has not stamped.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string MySQL datetime, or '' when the topic has neither.
	 */
	public function get_topic_last_active_datetime( int $topic_id ): string;

	/**
	 * The ID of the post a topic was last active on.
	 *
	 * The tiebreak beside get_topic_last_active_datetime(), and the reason both are
	 * needed: bbPress stamps last-active to the second, so a thread that takes two
	 * replies inside one second is indistinguishable by time alone. Comparing the
	 * pair separates them.
	 *
	 * Falls back to the topic's own ID when bbPress has not stamped one, which is the
	 * same fallback Unread\ReadState applies in SQL — so a topic bbPress left alone
	 * compares equal on both sides instead of reading as permanently unread.
	 *
	 * ⚠ **The stored ID wins even when it is lower than the topic's own**, which an
	 * import can easily produce. Taking the larger of the two would swallow a reply
	 * that arrived in the same second as the read, which is the case this exists to
	 * catch.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return int Post ID, or 0 when the topic does not exist.
	 */
	public function get_topic_last_active_id( int $topic_id ): int;

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
	 * A topic's own post date.
	 *
	 * Distinct from get_topic_last_active_time(), which the thread list uses. A
	 * thread list is browsed for what has moved; a result list is read in the order
	 * things were written, so it shows the date it is sorted by.
	 *
	 * @since 0.3.0
	 *
	 * @param int  $topic_id Topic ID.
	 * @param bool $humanize Whether to return a human-readable diff.
	 * @return string
	 */
	public function get_topic_post_date( int $topic_id, bool $humanize = true ): string;

	/**
	 * A short plain-text excerpt of a topic's opening post.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 * @param int $length   Maximum length in characters.
	 * @return string
	 */
	public function get_topic_excerpt( int $topic_id, int $length ): string;

	/**
	 * A short plain-text excerpt of a forum's description.
	 *
	 * There is no excerpt getter for a forum in bbPress, so this is the one built here
	 * — and both halves it adds matter. It is **bounded**, so a long description cannot make
	 * one result row taller than the list it is in. And it is **password-aware**:
	 * WordPress replaces protected content with its password *form*, so
	 * `bbp_get_forum_content()` returns that markup, and stripping its tags leaves the
	 * form's own prose behind — a row that reads "This content is password-protected.
	 * To view it, please enter the password below. Password:" as though it were what
	 * the forum is about. View\ForumList says the same thing about the same hazard, in
	 * its own units; raised by Qodo on #69, where the search row had neither guard.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @param int $length   Maximum length in characters.
	 * @return string
	 */
	public function get_forum_excerpt( int $forum_id, int $length ): string;

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

	/**
	 * The search terms for this request.
	 *
	 * '' when none were given — which is the terms-less search screen, not an
	 * error. bbPress's own getter returns `false` in that case; the seam narrows it
	 * to a string so callers test one thing, and normalises it the same way
	 * sanitize_search_request() does, so the screen and its continuation cannot
	 * search two different strings.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_search_terms(): string;

	/**
	 * Search terms arriving on a POSTed request parameter.
	 *
	 * The continuation endpoint's own reader. It is a separate method because the
	 * getter above reads the *query var* bbPress's rewrite rules populate, and a
	 * continuation request carries its subject in the request body like every other
	 * one of ours — but the two normalise identically, which is what keeps a page
	 * two searching what page one searched. See the implementation for why bbPress's
	 * own sanitiser cannot be handed this key.
	 *
	 * @since 0.3.0
	 *
	 * @param string $key Request parameter name.
	 * @return string
	 */
	public function sanitize_search_request( string $key ): string;

	/**
	 * Prime the search-results loop.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query args (see Query\SearchQuery).
	 * @return bool Whether any results matched.
	 */
	public function has_search_results( array $args ): bool;

	/**
	 * Advance the search-results loop.
	 *
	 * @since 0.3.0
	 *
	 * @return bool Whether a result remains.
	 */
	public function the_search_results_loop(): bool;

	/**
	 * Set up the current search result in the loop.
	 *
	 * @since 0.3.0
	 */
	public function the_search_result(): void;

	/**
	 * The post type of the result currently in the search loop.
	 *
	 * Search is the only loop we render that returns more than one type, so the
	 * row renderer has to ask. bbPress's own loop-search.php asks the same
	 * question, in the same place, to pick its partial.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_search_result_post_type(): string;

	/**
	 * The ID of the result currently in the search loop.
	 *
	 * Asked for outright rather than through bbp_get_topic_id() / bbp_get_reply_id()
	 * / bbp_get_forum_id(), which each answer from a different ambient global that
	 * bbp_the_search_result() has to reset on every iteration. One result, one ID,
	 * and the row renderer passes it explicitly to every getter it then calls.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_search_result_id(): int;

	/**
	 * The number of result pages from the last search query.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_max_search_pages(): int;

	/**
	 * The total number of results the last search query matched.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_search_result_count(): int;

	/**
	 * A WHERE fragment admitting every row of one post type, plus every row whose
	 * status is in a list.
	 *
	 * The seam owns it because it is SQL: the caller supplies the post type to
	 * exempt and the statuses to admit, and never sees a table name or a
	 * placeholder. Both are bound through $wpdb->prepare().
	 *
	 * @since 0.3.0
	 *
	 * @param string   $exempt_post_type Post type admitted whatever its status.
	 * @param string[] $statuses         Statuses admitted for every other post type.
	 * @return string A fragment beginning with AND, or '' if there is nothing to say.
	 */
	public function post_status_where_clause( string $exempt_post_type, array $statuses ): string;

	/**
	 * A WHERE fragment withholding a reply whose parent topic exists and is one
	 * the reader may not read — its status is not in a list, or it carries a
	 * password.
	 *
	 * A reply's own row cannot say either of those things: bbPress rewrites no
	 * reply status when its topic's changes, and a reply inherits no password of
	 * its own, so `post_status_where_clause()` — which reads only the row being
	 * filtered — passes a reply through whatever its topic's state (issue #72). A
	 * reply whose parent does not exist, or is not itself a topic, is admitted
	 * rather than withheld: that is the orphaned-reply state CLAUDE.md's pitfall
	 * #5 names, and this codebase's answer to it is to keep the reply
	 * discoverable under its own title, not to swallow it here.
	 *
	 * The password half reads the stored column, not `post_password_required()`
	 * — which is cookie-aware but only callable per row, in PHP, against the
	 * password a specific reader typed. So this can withhold a reply from a
	 * reader who has already unlocked its topic; it will never do the reverse.
	 *
	 * The seam owns this one for the same reason it owns
	 * `post_status_where_clause()`: it is SQL run against the posts table
	 * directly, and the caller never sees a table name.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $reply_post_type Post type this predicate applies to; every
	 *                                  other post type is admitted untouched.
	 * @param string   $topic_post_type Post type a parent must carry to count.
	 * @param string[] $topic_statuses  Statuses the reader may see a topic in.
	 * @return string A fragment beginning with AND, or '' if there is nothing to say.
	 */
	public function reply_parent_where_clause( string $reply_post_type, string $topic_post_type, array $topic_statuses ): string;

	/**
	 * A WHERE clause rewritten to *also* admit one reader's own replies held for
	 * moderation — and nothing else, ever.
	 *
	 * ## Why this widens instead of narrowing
	 *
	 * `pending` is a WordPress *protected* status. The obvious move — naming it in
	 * the query's `post_status` — was measured on bbPress 2.6.14 and is a
	 * disclosure. WP_Query author-restricts only its *private* bucket, and only
	 * under `perm => readable` (`class-wp-query.php:2688`); a protected status goes
	 * to the unrestricted bucket, so `post_status => array( 'publish', 'pending' )`
	 * emits
	 *
	 * ```sql
	 * AND post_type = 'reply' AND ( post_status = 'publish' OR post_status = 'pending' )
	 * ```
	 *
	 * — every held reply on the site, to a logged-out visitor. Setting `post_status`
	 * at all is also a regression on its own: with it unset, WP builds
	 * `publish OR closed OR ( post_author = me AND private ) OR ( post_author = me AND
	 * hidden )`, and naming a list replaces all of that.
	 *
	 * So the existing clause is never touched. One fully-qualified predicate is
	 * OR-ed beside it, naming the post type, the status, the author and the topic —
	 * every one of them bound. There is no argument to this method that can widen it
	 * past one reader's own held replies in one thread, which is the property that
	 * makes it reviewable: **its failure mode is a missing row, never a leak.** A
	 * widen-then-subtract pair (`Query\SearchVisibility`'s shape) fails the other
	 * way, and on this surface that is the difference between a defect and a
	 * disclosure.
	 *
	 * ## Two guards, both refusing rather than guessing
	 *
	 * - **`$author_id` must be positive.** That is the logged-out rule and the
	 *   anonymous carve-out in one line: an anonymous reply carries `post_author = 0`
	 *   and its identity in post meta, so a zero would match every anonymous held
	 *   reply in the thread rather than nobody's.
	 * - **`$where` must already begin with `AND`.** The rewrite wraps it as
	 *   `AND ( 1=1 <where> OR ( mine ) )`, and `1=1` alone is `TRUE` — so a `$where`
	 *   that constrains nothing would turn the whole query into a full-table read.
	 *   It cannot happen for the two queries that arm this (both set a post type),
	 *   and it is refused anyway.
	 *
	 * The wrap is what keeps a *later* `posts_where` filter honest. Appending
	 * ` OR ( mine )` bare would leave `A AND B OR C`, and a plugin that then appends
	 * ` AND X` would narrow only `C` — letting `A AND B` escape a restriction its
	 * author meant for the whole query. Wrapped, anything appended after us applies
	 * to both sides.
	 *
	 * The reader's ID is bound *into the SQL* rather than applied to the rows
	 * afterwards. That is deliberate beyond taste: the query differs per reader, so
	 * a persistent object cache keys it per reader too. Deciding visibility in PHP
	 * after a shared query would leave one reader's held reply in a cache entry
	 * another reader can be served.
	 *
	 * @since 0.5.0
	 *
	 * @param string $where           The clause built so far.
	 * @param string $reply_post_type Post type the predicate admits.
	 * @param string $pending_status  Status the predicate admits.
	 * @param int    $author_id       The one author whose held replies are admitted.
	 * @param int    $topic_id        The one thread they are admitted in.
	 * @param int[]  $ids             Further narrows the predicate to these reply
	 *                                IDs. The threaded reading view MUST pass its
	 *                                page slice: its page query is scoped by
	 *                                `post__in`, which the predicate would otherwise
	 *                                escape — putting the held reply on every page.
	 *                                Empty means "no ID constraint", which is right
	 *                                only where `post_parent` is the whole scope.
	 * @return string The rewritten clause, or $where untouched if either guard fired.
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
	 * Read a query variable off a query object.
	 *
	 * Typed loosely on purpose. The callers are hooks that WordPress hands a
	 * WP_Query to, and this seam exists so they never have to name the class to
	 * take it: the type check happens here, and anything that is not a query
	 * answers null.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed  $query The query, as a hook received it.
	 * @param string $key   Variable name.
	 * @return mixed The value, or null if there is no query to ask.
	 */
	public function get_query_arg( $query, string $key );

	/**
	 * Write a query variable onto a query object.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed  $query The query, as a hook received it.
	 * @param string $key   Variable name.
	 * @param mixed  $value Value to set.
	 */
	public function set_query_arg( $query, string $key, $value ): void;

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
	 * Handles of every currently enqueued script.
	 *
	 * @since 0.5.0
	 *
	 * @return string[]
	 */
	public function get_enqueued_script_handles(): array;

	/**
	 * Dequeue a script by handle.
	 *
	 * @since 0.5.0
	 *
	 * @param string $handle Handle.
	 */
	public function dequeue_script( string $handle ): void;

	/**
	 * The registered source URL of an enqueued script, or '' if unknown.
	 *
	 * Used by takeover suppression to identify scripts from the active theme.
	 *
	 * @since 0.5.0
	 *
	 * @param string $handle Handle.
	 * @return string
	 */
	public function get_script_src( string $handle ): string;

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
