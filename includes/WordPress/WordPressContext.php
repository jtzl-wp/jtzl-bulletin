<?php
/**
 * Live WordPress / bbPress implementation of the context seam.
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin\WordPress;

/**
 * Thin one-line delegations to WordPress and bbPress globals. This is the only
 * class in the plugin that calls those globals directly; everything else depends
 * on ContextInterface so it can be faked in tests.
 *
 * @since 0.1.0
 */
class WordPressContext implements ContextInterface {

	/**
	 * Object-cache group holding thread-rank answers.
	 *
	 * A group rather than a transient on purpose. Rank is per topic, so a transient
	 * would leave one wp_options row per topic to accumulate and expire on its own;
	 * object-cache entries evict under the host's policy and cost nothing where no
	 * persistent cache is installed, which is also where they buy nothing.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	private const RANK_CACHE_GROUP = 'bltn_thread_rank';

	/**
	 * How long a thread rank stays cached, in seconds.
	 *
	 * Short: the entry can only be as fresh as the last reply anywhere in the
	 * forum, and the bar's counter is decorative next to the thread it sits under.
	 *
	 * @since 0.3.0
	 * @var int
	 */
	private const RANK_CACHE_TTL = 300;

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
	 * Whether this is a member profile's Subscriptions tab.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_subscriptions(): bool {
		return (bool) bbp_is_subscriptions();
	}

	/**
	 * Whether this is bbPress's search screen.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_search(): bool {
		return (bool) bbp_is_search();
	}

	/**
	 * Whether the site allows searching.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function allow_search(): bool {
		return (bool) bbp_allow_search();
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
	 * Whether the current user may edit a given user.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id Subject user ID.
	 * @return bool
	 */
	public function current_user_can_edit_user( int $user_id ): bool {
		return (bool) current_user_can( 'edit_user', $user_id );
	}

	/**
	 * Whether the current user may moderate a given forum post.
	 *
	 * @since 0.3.0
	 *
	 * @param int $post_id Topic or reply ID the moderation would act on.
	 * @return bool
	 */
	public function current_user_can_moderate( int $post_id ): bool {
		return $post_id > 0 && (bool) current_user_can( 'moderate', $post_id );
	}

	/**
	 * Moderation links for a topic, in bbPress's own markup.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string Markup, or '' when the user may do nothing.
	 */
	public function get_topic_moderation_links( int $topic_id ): string {
		return $this->moderation_links(
			true,
			'bbp_topic_admin_links',
			static fn(): string => (string) bbp_get_topic_admin_links(
				array(
					'id'           => $topic_id,
					'sep'          => '',

					/*
					 * bbPress's own words for these are "Stick" and "(to front)",
					 * written to read inline as "Stick (to front)". Laid out as chips
					 * the parenthetical loses its host and names nothing, and "stick"
					 * is bbPress's jargon on a screen that says "Pinned" everywhere
					 * else — the forum screen's section label, and DESIGN.md's own
					 * vocabulary. So each chip names its action in the app's language.
					 * Same functions, same nonces; only the labels are ours.
					 */
					'stick_text'   => __( 'Pin', 'jtzl-bulletin' ),
					'unstick_text' => __( 'Unpin', 'jtzl-bulletin' ),
					'super_text'   => __( 'Pin everywhere', 'jtzl-bulletin' ),
				)
			)
		);
	}

	/**
	 * Moderation links for a reply, in bbPress's own markup.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string Markup, or '' when the user may do nothing.
	 */
	public function get_reply_moderation_links( int $reply_id ): string {
		return $this->moderation_links(
			! bbp_thread_replies(),
			'bbp_reply_admin_links',
			static fn(): string => (string) bbp_get_reply_admin_links(
				array(
					'id'  => $reply_id,
					'sep' => '',
				)
			)
		);
	}

	/**
	 * Render one of bbPress's admin-link sets, optionally minus its `reply` member,
	 * and report emptiness honestly.
	 *
	 * Two things happen here, both of them about staying out of bbPress's way.
	 *
	 * The set is bbPress's own — we ask for it and drop at most one member rather than
	 * enumerate the rest, so a link a future bbPress adds arrives without us updating
	 * a list (and without fifteen more functions in `stubs/bbpress-stubs.php`).
	 *
	 * ⚠ **The two sets get different answers, and 0.5.0 is where they parted.** Until
	 * P4 both dropped `reply`, because the takeover rendered no composer for either to
	 * arrive at. It renders one now, and only one of them came back:
	 *
	 * - **Topic — still dropped, permanently, and no longer for the old reason.**
	 *   `bbp_get_topic_reply_link()` resolves to `remove_query_arg( … ) . '#new-post'`
	 *   (`topics/template.php:2867`) — this same page, the identical destination as the
	 *   composer at the foot of the thread. Two controls to one place is the fault that
	 *   took "Start a thread" off the forums index; it does not become a feature by
	 *   living in a moderation tray.
	 * - **Reply — restored, and only where threading is on.** `bbp_get_reply_to_link()`
	 *   names *which post* you are answering, which the foot composer cannot say. With
	 *   `bbp_thread_replies()` off, bbPress omits the `onclick` and the destination
	 *   collapses to the foot slot's — so there it is the topic case again, and it goes.
	 *
	 * The filter is added and removed around the single call rather than left on. On
	 * the reskin tier bbPress renders its own admin links AND its own reply form, so
	 * both links work there and removing either would be us breaking a working control
	 * on a screen we only restyle.
	 *
	 * `sep` is emptied because bbPress joins with " | " and this design has no pipes:
	 * the links become chips laid out with a flex gap. One `sep` governs both levels —
	 * the set, and the sub-actions inside a single link (untrash / trash / delete) — so
	 * the two read alike, which they should.
	 *
	 * Emptiness has to survive the round trip because bbPress always wraps in its
	 * `before`/`after` span: a user who may do nothing still gets
	 * `<span class="bbp-admin-links"></span>` back, which is truthy markup for an empty
	 * control. Callers decide whether to render a group by whether there is anything in
	 * it, so the wrapper alone must read as nothing.
	 *
	 * @since 0.3.0
	 * @since 0.5.0 The `reply` member is dropped per set rather than always.
	 *
	 * @param bool              $drop_reply Whether to drop the set's `reply` member.
	 * @param string            $filter     bbPress filter naming the link set.
	 * @param callable():string $render     Produces the markup with the filter in place.
	 * @return string Markup, or '' when the set holds no links.
	 */
	private function moderation_links( bool $drop_reply, string $filter, callable $render ): string {
		$without_composer = static function ( $links ) {
			if ( is_array( $links ) ) {
				unset( $links['reply'] );
			}

			return $links;
		};

		if ( $drop_reply ) {
			add_filter( $filter, $without_composer );
		}

		try {
			$markup = $render();
		} finally {
			// In a finally because "for the duration of the call" has to be true even
			// when the call does not return: bbPress runs plugin hooks while building
			// these links, and one of those throwing would otherwise leave the filter
			// attached for the rest of the request — stripping "Reply To" from the
			// reskin tier, which renders both those links and a working reply form.
			// Unconditional, and safe when nothing was added: remove_filter() on a
			// callback that is not attached returns false and does nothing.
			remove_filter( $filter, $without_composer );
		}

		return '' === trim( wp_strip_all_tags( $markup ) ) ? '' : $markup;
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
	 * The URL of the request being served, query string included.
	 *
	 * ⚠ Built from `REQUEST_URI` rather than from `get_permalink()`, because the query
	 * string is the point: `?bbp_reply_to={id}` has to survive the login round-trip.
	 * Only the scheme, host and port are taken from `home_url()`; `esc_url_raw()` runs
	 * over the result, because `REQUEST_URI` is client input and this value is handed
	 * to `wp_login_url()` as a redirect target.
	 *
	 * ⚠ **`home_url( $path )` cannot be used for this, and the reason only shows on a
	 * subdirectory install.** It *appends* — and `REQUEST_URI` is already root-relative,
	 * so on a site at `example.com/blog` the two overlap and `home_url( REQUEST_URI )`
	 * yields `/blog/blog/…`. A reader would sign in and land on a 404, which is exactly
	 * the regression this getter exists to prevent. bbPress sidesteps it by keeping
	 * `bbp_redirect_to_field()`'s value relative; we need an absolute one for
	 * `wp_login_url()`, so the two halves are assembled rather than concatenated.
	 * Raised by Gitar on #111, reproduced before fixing.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_current_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? wp_unslash( $_SERVER['REQUEST_URI'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw() below is the sanitiser.
			: '';
		if ( ! is_string( $uri ) || '' === $uri ) {
			return '';
		}

		$home = wp_parse_url( (string) home_url() );
		if ( ! is_array( $home ) || ! isset( $home['scheme'], $home['host'] ) ) {
			return '';
		}

		$base = $home['scheme'] . '://' . $home['host'];
		if ( isset( $home['port'] ) ) {
			$base .= ':' . $home['port'];
		}

		return (string) esc_url_raw( $base . '/' . ltrim( $uri, '/' ) );
	}

	/**
	 * Whether bbPress would render a reply form for the current user, here.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function can_access_create_reply_form(): bool {
		return (bool) bbp_current_user_can_access_create_reply_form();
	}

	/**
	 * The reply this request asked to answer, or 0.
	 *
	 * Read-only and nonce-free by design: this decides whether a composer opens
	 * expanded, which is presentation. bbPress checks the nonce on the link when the
	 * reply is actually posted (`bbp_new_reply_handler()`), and `bbp_validate_reply_to()`
	 * already rejects anything that is not a real reply.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function get_requested_reply_to(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presentation only; see the docblock.
		$raw = $_REQUEST['bbp_reply_to'] ?? 0;

		return (int) bbp_validate_reply_to( absint( $raw ) );
	}

	/**
	 * Whether bbPress is holding an error to show on this request.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return (bool) bbp_has_errors();
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
	 * ID of the user whose profile is being viewed (0 when none is).
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_displayed_user_id(): int {
		return (int) bbp_get_displayed_user_id();
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
	 * The name bbPress would give the current screen.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_bbpress_screen_title(): string {
		return trim( (string) bbp_title( '', '', '' ) );
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
	 * The search screen's URL, with no terms.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_search_url(): string {
		return (string) bbp_get_search_url();
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
	 * Whether a forum is closed to new content.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	public function is_forum_closed( int $forum_id ): bool {
		// Second argument is bbPress's own default, spelled out because it is the
		// load-bearing half: a forum inside a closed category is closed as well.
		return (bool) bbp_is_forum_closed( $forum_id, true );
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
	 * A reply's stored title, unfiltered.
	 *
	 * See the interface for why this cannot go through `bbp_get_reply_title()`.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function get_reply_title( int $reply_id ): string {
		// 'raw', because the default 'display' context runs sanitize_post_field(),
		// which applies the very `the_title` filter the recursion lives on.
		return (string) get_post_field( 'post_title', $reply_id, 'raw' );
	}

	/**
	 * A short plain-text excerpt of a reply.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @param int $length   Maximum length in characters.
	 * @return string
	 */
	public function get_reply_excerpt( int $reply_id, int $length ): string {
		return $this->plain_excerpt( $reply_id, static fn(): string => (string) bbp_get_reply_excerpt( $reply_id, $length ) );
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
	 * The reply a reply answers, or 0 when it answers the thread itself.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return int Parent reply ID, or 0.
	 */
	public function get_reply_to( int $reply_id ): int {
		return (int) bbp_get_reply_to( $reply_id );
	}

	/**
	 * The thread a reply belongs to.
	 *
	 * @since 0.3.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return int Topic ID, or 0 when the ID is not a reply.
	 */
	public function get_reply_topic_id( int $reply_id ): int {
		return (int) bbp_get_reply_topic_id( $reply_id );
	}

	/**
	 * Every reply of a topic this reader may see, mapped to the reply it answers.
	 *
	 * This is the one unbounded query the reading view makes, so it asks for
	 * `fields => ids` and one primed meta read — never the thread's post objects,
	 * which is exactly what bbPress's hierarchical mode does and what issue #12 was
	 * about. A 20-reply thread and a 20,000-reply thread differ here by a list of
	 * integers.
	 *
	 * A plain WP_Query rather than bbp_has_replies(), for two reasons that are both
	 * bbPress's doing. `bbp_has_replies()` cannot answer `fields => ids` at all: it
	 * walks its own results reading `$post->post_type` to hang `reply_to` on each
	 * one (replies/template.php:219), which fatals on a list of integers. And it
	 * assigns `bbpress()->reply_query`, so calling it here would leave the loop
	 * global holding this query instead of the page's.
	 *
	 * What it does NOT reimplement is which replies a reader may see. The status
	 * block below is bbPress's own, copied from bbp_has_replies()'s defaults, and
	 * ReadingFlowTest asserts this method returns exactly the ID set bbPress's query
	 * returns — logged out and as a keymaster viewing all — so the copy cannot
	 * quietly drift from the original.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic to read.
	 * @return array<int,int> Reply ID => parent reply ID, in (date, ID) order.
	 */
	public function get_reply_parents( int $topic_id ): array {
		$args = array(
			'post_parent'            => $topic_id,
			'post_type'              => bbp_get_reply_post_type(),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => array(
				'date' => 'ASC',
				'ID'   => 'ASC',
			),
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'no_found_rows'          => true,
		);

		// bbPress's own visibility rules, verbatim from bbp_has_replies().
		if ( bbp_get_view_all( 'edit_others_replies' ) ) {
			$post_statuses = array_keys( bbp_get_topic_statuses() );
			if ( current_user_can( 'read_private_replies' ) ) {
				$post_statuses[] = bbp_get_private_status_id();
			}
			$args['post_status'] = $post_statuses;
		} else {
			$args['perm'] = 'readable';
		}

		// Run the same filters bbp_has_replies() runs, so a site that narrows the
		// reply query narrows the reading order with it. bbPress hooks these itself
		// (_bbp_has_replies_query, core/filters.php:426) to expose `bbp_has_replies_query`,
		// which makes them the documented way to change this query rather than an
		// obscure one. Skipping them let the order — and the page count derived from
		// it — count replies the rendered page excludes: a load-more that fetches a
		// short page, or a context link naming a post that is not in the document
		// (raised by Gitar).
		$args = bbp_parse_args( $args, array(), 'has_replies' );

		// A filter may say WHICH replies exist for this reader. It does not get to say
		// how they are enumerated: this query has to stay one unpaginated list of IDs
		// in reading order, and a plugin setting posts_per_page — the likeliest thing
		// for one to set — would otherwise truncate the order to a page and take the
		// rest of the thread with it.
		$args['fields']         = 'ids';
		$args['posts_per_page'] = -1;
		$args['nopaging']       = true;
		$args['paged']          = 1;
		$args['offset']         = 0;
		$args['post_parent']    = $topic_id;
		$args['post_type']      = bbp_get_reply_post_type();
		$args['orderby']        = array(
			'date' => 'ASC',
			'ID'   => 'ASC',
		);

		$ids = array_map( 'intval', ( new \WP_Query( $args ) )->posts ); // @phpstan-var int[] $ids
		if ( array() === $ids ) {
			return array();
		}

		// One read for the whole thread rather than a query per reply.
		update_meta_cache( 'post', $ids );

		$parents = array();
		foreach ( $ids as $id ) {
			$parents[ $id ] = (int) get_post_meta( $id, '_bbp_reply_to', true );
		}

		return $parents;
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
	 * Topic statuses this reader is entitled to see.
	 *
	 * @since 0.5.0
	 *
	 * @return array<int,string>
	 */
	public function get_readable_topic_statuses(): array {
		$statuses = bbp_get_public_topic_statuses();

		if ( current_user_can( 'read_private_topics' ) ) {
			$statuses[] = bbp_get_private_status_id();
		}

		if ( current_user_can( 'read_hidden_topics' ) ) {
			$statuses[] = bbp_get_hidden_status_id();
		}

		return array_values( array_unique( array_map( 'strval', $statuses ) ) );
	}

	/**
	 * Forum IDs this reader may not see, as bbPress computes them.
	 *
	 * @since 0.5.0
	 *
	 * @return array<int,int>
	 */
	public function get_excluded_forum_ids(): array {
		return array_map( 'intval', bbp_get_excluded_forum_ids() );
	}

	/**
	 * A topic's last-activity time as a raw MySQL datetime.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string MySQL datetime, or '' when the topic has neither.
	 */
	public function get_topic_last_active_datetime( int $topic_id ): string {
		$stored = (string) get_post_meta( $topic_id, '_bbp_last_active_time', true );

		if ( '' !== $stored ) {
			return $stored;
		}

		return (string) get_post_field( 'post_date', $topic_id, 'raw' );
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
	 * A topic's own post date.
	 *
	 * @since 0.3.0
	 *
	 * @param int  $topic_id Topic ID.
	 * @param bool $humanize Whether to return a human-readable diff.
	 * @return string
	 */
	public function get_topic_post_date( int $topic_id, bool $humanize = true ): string {
		return (string) bbp_get_topic_post_date( $topic_id, $humanize );
	}

	/**
	 * A short plain-text excerpt of a topic's opening post.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 * @param int $length   Maximum length in characters.
	 * @return string
	 */
	public function get_topic_excerpt( int $topic_id, int $length ): string {
		return $this->plain_excerpt( $topic_id, static fn(): string => (string) bbp_get_topic_excerpt( $topic_id, $length ) );
	}

	/**
	 * A short plain-text excerpt of a forum's description.
	 *
	 * Bounded here rather than in the getter, because bbPress ships no forum excerpt
	 * to bound — and after plain_excerpt(), not before it, so a cut cannot land in the
	 * middle of an entity that decoding was about to resolve.
	 *
	 * @since 0.3.0
	 *
	 * @param int $forum_id Forum ID.
	 * @param int $length   Maximum length in characters.
	 * @return string
	 */
	public function get_forum_excerpt( int $forum_id, int $length ): string {
		$text = $this->plain_excerpt(
			$forum_id,
			static fn(): string => wp_strip_all_tags( (string) bbp_get_forum_content( $forum_id ) )
		);

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return rtrim( mb_substr( $text, 0, $length - 1 ) ) . '…';
	}

	/**
	 * Reduce one of bbPress's excerpts to text a template can escape.
	 *
	 * Two things stand between bbPress's excerpt and a printable string.
	 *
	 * It is not plain text. bbPress strips tags but leaves entities behind, and
	 * appends a literal `&hellip;` when it truncates — so escaping it prints
	 * `&hellip;` to the reader, and prints `&amp;` where the post said `&`.
	 * Decoding first means one round of escaping at the template lands on the
	 * characters the author actually typed.
	 *
	 * And it is not password-aware at the point it matters. `bbp_get_*_content()`
	 * does check, and answers a protected post with the password form — but the
	 * excerpt reads `post_excerpt` first and returns it unguarded, so a protected
	 * post carrying one would spill it into a result row. Nothing in bbPress's own
	 * UI writes that field, which is exactly why an import is free to. Withholding
	 * the excerpt also spares the reader the alternative: the password form,
	 * stripped of its markup, rendered as though it were what the post says.
	 *
	 * @since 0.3.0
	 *
	 * @param int      $post_id Topic or reply ID.
	 * @param callable $excerpt Deferred excerpt getter, called only when readable.
	 * @return string
	 */
	private function plain_excerpt( int $post_id, callable $excerpt ): string {
		if ( post_password_required( $post_id ) ) {
			return '';
		}

		return trim( html_entity_decode( (string) $excerpt(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Whether a topic is closed to new replies.
	 *
	 * @since 0.3.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return bool
	 */
	public function is_topic_closed( int $topic_id ): bool {
		return (bool) bbp_is_topic_closed( $topic_id );
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
	 * Where a topic sits in its forum's freshness order, and its neighbours.
	 *
	 * Immediate forum only — topics in sub-forums are not folded in.
	 *
	 * Four bounded queries replace the unbounded one this method used to run
	 * (issue #59, from the #51 audit): membership, total-and-position together,
	 * and one per neighbour. Two of the four when the topic is not a member. No
	 * result set here is larger than a single row, where the ID array it replaced
	 * grew with the forum's topic count and was then cached at that size.
	 *
	 * The database still walks the forum's topics — wp_postmeta carries no index
	 * spanning meta_key and meta_value, so the timestamp comparison is a per-row
	 * filter rather than a range scan, and no arrangement of these queries avoids
	 * that. What the seek removes is everything downstream of it. Measured on a
	 * 20,000-topic forum: 113 ms against 135 ms and 10.7 MB for the query it
	 * replaces, and a repeat view of 0.05 ms against 5.8 ms, because a hit on the
	 * transient meant unserialising a 291 KB blob and searching it.
	 *
	 * The answer is cached in the object cache, which is what makes that walk
	 * survivable on a forum large enough to notice it. Bounded either way — four
	 * integers per entry — so a cache miss costs time and never memory.
	 *
	 * Raw SQL, deliberately: the order is the composite (_bbp_last_active_time,
	 * ID), so a seek predicate has to say "later stamp, OR the same stamp and a
	 * higher ID". WP_Query cannot express that — meta_query has no way to put an
	 * ID comparison inside an OR branch beside a meta comparison — and without the
	 * tiebreak, topics sharing a timestamp order differently here than in the
	 * thread list, so Next would revisit a thread or skip one (CLAUDE.md trap #4).
	 * Every value goes through $wpdb->prepare(); only table names and the fixed
	 * SELECT/ORDER fragments are interpolated.
	 *
	 * The INNER JOIN is also load-bearing. The WP_Query passed
	 * meta_key => '_bbp_last_active_time', which is itself an inner join, so a
	 * topic carrying no stamp was silently absent from the array and from its
	 * count. Joining the same way keeps the set identical, keeps position <= total,
	 * and keeps a stampless topic reporting position 0.
	 *
	 * Statuses stay the caller's (publish + closed) rather than bbPress's
	 * capability-derived set: raw SQL skips bbp_pre_get_posts_normalize_forum_
	 * visibility, and pinning them is what keeps that safe. Every row shares one
	 * forum, so readability is all-or-nothing and the reader is already inside it.
	 *
	 * @since 0.3.0
	 *
	 * @param int      $forum_id Forum ID.
	 * @param int      $topic_id Topic to locate within it.
	 * @param string[] $statuses Post statuses to include.
	 * @return array{total:int,position:int,prev_id:int,next_id:int}
	 */
	public function get_topic_rank( int $forum_id, int $topic_id, array $statuses ): array {
		$rank = array(
			'total'    => 0,
			'position' => 0,
			'prev_id'  => 0,
			'next_id'  => 0,
		);

		if ( $forum_id <= 0 || array() === $statuses ) {
			return $rank;
		}

		$cache_key = $this->rank_cache_key( $forum_id, $topic_id, $statuses );
		$cached    = wp_cache_get( $cache_key, self::RANK_CACHE_GROUP );

		if ( is_array( $cached ) ) {
			// Rebuilt to the documented shape rather than returned as found: what
			// comes back is whatever is in the cache, which is not necessarily what
			// this method put there.
			return array(
				'total'    => (int) ( $cached['total'] ?? 0 ),
				'position' => (int) ( $cached['position'] ?? 0 ),
				'prev_id'  => (int) ( $cached['prev_id'] ?? 0 ),
				'next_id'  => (int) ( $cached['next_id'] ?? 0 ),
			);
		}

		$rank = $this->seek_topic_rank( $forum_id, $topic_id, $statuses );

		wp_cache_set( $cache_key, $rank, self::RANK_CACHE_GROUP, self::RANK_CACHE_TTL );

		return $rank;
	}

	/**
	 * The cache key a topic's rank is stored under.
	 *
	 * Versioned on the forum's topic count rather than its last-active time, which
	 * is a deliberate departure from the transient this replaced. Last-active moves
	 * on every reply, so keying on it would miss the cache on exactly the busy,
	 * large forums the cache exists for. Topic count moves when a topic is trashed,
	 * spammed or deleted — the case that matters, because a stale neighbour there
	 * is a dead link rather than a slightly old one. Ordinary reordering is left to
	 * the TTL, since on a busy forum any cached order is stale the moment it is
	 * written.
	 *
	 * bbPress's counts can themselves lag after an import (see issue #38), so the
	 * TTL is the backstop rather than the optimisation.
	 *
	 * @since 0.3.0
	 *
	 * @param int      $forum_id Forum ID.
	 * @param int      $topic_id Topic being ranked.
	 * @param string[] $statuses Post statuses the rank covers.
	 * @return string
	 */
	private function rank_cache_key( int $forum_id, int $topic_id, array $statuses ): string {
		$count = (string) get_post_meta( $forum_id, '_bbp_topic_count', true );

		return $forum_id . '_' . $topic_id . '_' . $count . '_' . md5( implode( ',', $statuses ) );
	}

	/**
	 * Rank a topic by seeking, without consulting the cache.
	 *
	 * Four bounded queries, or two when the topic turns out not to be a member.
	 *
	 * @since 0.3.0
	 *
	 * @param int      $forum_id Forum ID.
	 * @param int      $topic_id Topic to locate within it.
	 * @param string[] $statuses Post statuses to include.
	 * @return array{total:int,position:int,prev_id:int,next_id:int}
	 */
	private function seek_topic_rank( int $forum_id, int $topic_id, array $statuses ): array {
		global $wpdb;

		$rank = array(
			'total'    => 0,
			'position' => 0,
			'prev_id'  => 0,
			'next_id'  => 0,
		);

		$scope = $this->rank_scope( count( $statuses ) );
		$where = array_merge( array( $forum_id, bbp_get_topic_post_type() ), array_values( $statuses ) );

		// Membership first, and it is the only cheap query here: p.ID = %d is a
		// primary-key lookup. A count predicate never asks whether the subject is in
		// the set, so without this a trashed, spammed or wrong-forum topic would come
		// back with a plausible position instead of 0.
		//
		// The placeholder sniffs cannot see into rank_scope(), so they read a query
		// whose placeholders all live in the fragment as having none, and one array
		// argument as one replacement. Both counts are right once assembled.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- interpolation is table names and a fixed fragment; values are prepared.
		$stamp = $wpdb->get_var( $wpdb->prepare( "SELECT m.meta_value {$scope} AND p.ID = %d LIMIT 1", array_merge( $where, array( $topic_id ) ) ) );

		if ( null === $stamp ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
			$rank['total'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$scope}", $where ) );

			return $rank;
		}

		// Total and position in one pass. Both walk the same rows, and MySQL has no
		// index that spans meta_key and meta_value, so each pass is a scan of the
		// forum's topics — measured at roughly a third of the cost to run them
		// separately. Position is 1 + however many topics sort ahead of this one, on
		// the same predicate the prev neighbour seeks with, so the count and the step
		// cannot disagree about where the reader is.
		//
		// The `p.ID <> %d` is about the gap between statements. The stamp was read a
		// query ago, and a reply landing on this very topic in between moves it —
		// after which the topic satisfies its own "sorts ahead" test and is counted
		// ahead of itself, or comes back as its own Prev. Excluding the subject makes
		// that impossible. It changes nothing under a consistent read, where a strict
		// comparison already excludes it.
		//
		// Other topics moving mid-flight is left alone: that is ordinary read
		// staleness, measured in the milliseconds between four statements, where the
		// transient this replaced could serve an order up to an hour old.
		//
		// Note the argument order: SQL puts the SELECT list before the FROM, so the
		// seek values bind ahead of the scope's.
		$counts_args = array_merge( array( $stamp, $stamp, $topic_id, $topic_id ), $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
		$counts = (array) $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM( CASE WHEN ( m.meta_value > %s OR ( m.meta_value = %s AND p.ID > %d ) ) AND p.ID <> %d THEN 1 ELSE 0 END ) AS ahead {$scope}", $counts_args ), ARRAY_A );

		$seek = array_merge( $where, array( $stamp, $stamp, $topic_id, $topic_id ) );

		// Read defensively rather than branching on it: a query that failed leaves
		// the reader with no position and no steps, which is how the bar already
		// renders for a topic outside the order.
		$rank['total']    = (int) ( $counts['total'] ?? 0 );
		$rank['position'] = isset( $counts['ahead'] ) ? (int) $counts['ahead'] + 1 : 0;
		$rank['prev_id']  = $this->rank_neighbour( $scope, $seek, true );
		$rank['next_id']  = $this->rank_neighbour( $scope, $seek, false );

		return $rank;
	}

	/**
	 * The FROM/WHERE every ranking query shares.
	 *
	 * Written once so total, position and the two neighbours cannot come to
	 * disagree about which topics they are ranking. Placeholders only — the caller
	 * supplies forum ID, post type and statuses to prepare(), in that order.
	 *
	 * @since 0.3.0
	 *
	 * @param int $status_count How many post statuses the caller will bind.
	 * @return string
	 */
	private function rank_scope( int $status_count ): string {
		global $wpdb;

		$statuses = implode( ', ', array_fill( 0, $status_count, '%s' ) );

		return "FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m
				ON m.post_id = p.ID AND m.meta_key = '_bbp_last_active_time'
			WHERE p.post_parent = %d
				AND p.post_type = %s
				AND p.post_status IN ( {$statuses} )";
	}

	/**
	 * The nearest topic on one side of the subject, or 0 at a forum boundary.
	 *
	 * Both directions come out of one method on purpose. The list runs freshest
	 * first, so prev seeks *up* it — a later stamp, ordered ascending to land on
	 * the nearest — while next seeks down, ordered descending. Written as two
	 * literals, a pair that shared an ORDER BY direction would return the far end
	 * of the forum rather than the neighbour, and would read as correct.
	 *
	 * The subject is excluded outright: under a consistent read the strict
	 * comparison already excludes it, but its stamp may have moved since the
	 * caller read it, and a topic offered as its own Prev is a broken link rather
	 * than a stale one.
	 *
	 * @since 0.3.0
	 *
	 * @param string            $scope   Shared FROM/WHERE from rank_scope().
	 * @param array<int,scalar> $seek    Bound values: scope values, stamp, stamp, topic ID, topic ID.
	 * @param bool              $fresher Seek towards the front of the list rather than the back.
	 * @return int
	 */
	private function rank_neighbour( string $scope, array $seek, bool $fresher ): int {
		global $wpdb;

		$cmp = $fresher ? '>' : '<';
		$dir = $fresher ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- interpolation is table names and two fixed keywords; values are prepared.
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID {$scope}
					AND ( m.meta_value {$cmp} %s OR ( m.meta_value = %s AND p.ID {$cmp} %d ) )
					AND p.ID <> %d
				ORDER BY m.meta_value {$dir}, p.ID {$dir}
				LIMIT 1",
				$seek
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $id;
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
	 * IDs of the site-wide super stickies.
	 *
	 * @since 0.3.0
	 *
	 * @return int[]
	 */
	public function get_super_sticky_ids(): array {
		return array_values( array_filter( array_unique( array_map( 'intval', (array) bbp_get_super_stickies() ) ) ) );
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
	 * Prime the forums loop with a user's subscribed forums.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return bool Whether any subscribed forums matched.
	 */
	public function has_forum_subscriptions( array $args ): bool {
		return (bool) bbp_get_user_forum_subscriptions( $args );
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
	 * Render bbPress's own row for the forum the loop is on.
	 *
	 * @since 0.3.0
	 */
	public function render_forum_row(): void {
		bbp_get_template_part( 'loop', 'single-forum' );
	}

	/**
	 * IDs of the forums a user subscribes to.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public function get_subscribed_forum_ids( int $user_id ): array {
		return array_values(
			array_filter(
				array_unique( array_map( 'intval', (array) bbp_get_user_subscribed_forum_ids( $user_id ) ) )
			)
		);
	}

	/**
	 * Whether subscriptions are switched on site-wide.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_subscriptions_active(): bool {
		return (bool) bbp_is_subscriptions_active();
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
	 * The search terms for this request, sanitised by bbPress.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_search_terms(): string {
		$terms = bbp_get_search_terms();

		return $this->normalize_search_terms( is_string( $terms ) ? $terms : '' );
	}

	/**
	 * Search terms arriving on a POSTed request parameter.
	 *
	 * The obvious thing to delegate to is `bbp_sanitize_search_request()`, and it
	 * cannot be: it refuses any key outside `bbp_get_search_type_ids()`, which is
	 * `s | fs | ts | rs` — and `bbp_search`, the rewrite id our control posts under,
	 * is not one of them. Renaming the parameter to `s` to fit is worse than it
	 * looks, because `s` is a *public query var*: WordPress reads `$_POST` into the
	 * main query before `$_GET`, so posting it would turn the continuation request
	 * into a WordPress search of its own.
	 *
	 * So this does what that function does once its allowlist has passed —
	 * `wp_unslash()`, then the same normalisation the rewrite path gets.
	 *
	 * @since 0.3.0
	 *
	 * @param string $key Request parameter name.
	 * @return string
	 */
	public function sanitize_search_request( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public read-only endpoint; see Asset\AssetManager for why there is no nonce, and LoadSearchController for what does gate the request.
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

		return is_scalar( $raw ) ? $this->normalize_search_terms( (string) $raw ) : '';
	}

	/**
	 * The one normalisation both ways into a search share.
	 *
	 * Not a tidy-up: it is what makes paging honest. The screen reads its terms from
	 * bbPress's rewrite value and the continuation reads them from the request body,
	 * and if those two derive the string differently then page 2 searches something
	 * page 1 did not — which is the duplicate-and-gap symptom `Query\SearchQuery`'s
	 * single arg builder and ID tiebreak exist to prevent, reintroduced one layer
	 * above them. `sanitize_text_field()` trims, collapses whitespace and strips
	 * tags, so `<b>foo</b>` and `foo` are one query rather than two (raised by
	 * Gitar on #69).
	 *
	 * It also closes something bbPress leaves open: `bbp_get_search_terms()` returns
	 * the rewrite value with only `wp_unslash()` applied, and bbPress's own
	 * `bbp_search_terms()` echoes it into a value attribute unescaped.
	 *
	 * @since 0.3.0
	 *
	 * @param string $terms Raw terms.
	 * @return string
	 */
	private function normalize_search_terms( string $terms ): string {
		return sanitize_text_field( $terms );
	}

	/**
	 * Prime the search-results loop.
	 *
	 * Delegates to bbPress rather than running a WP_Query of our own: its function is
	 * what maintains `found_posts` and `posts_per_page` on `bbpress()->search_query`,
	 * and those are what give the continuation control an honest bound. Deriving the
	 * bound separately is the mistake Query\ReplyQuery documents.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args Query args (see Query\SearchQuery).
	 * @return bool Whether any results matched.
	 */
	public function has_search_results( array $args ): bool {
		return (bool) bbp_has_search_results( $args );
	}

	/**
	 * Advance the search-results loop.
	 *
	 * @since 0.3.0
	 *
	 * @return bool Whether a result remains.
	 */
	public function the_search_results_loop(): bool {
		return (bool) bbp_search_results();
	}

	/**
	 * Set up the current search result in the loop.
	 *
	 * @since 0.3.0
	 */
	public function the_search_result(): void {
		bbp_the_search_result();
	}

	/**
	 * The post type of the result currently in the search loop.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_search_result_post_type(): string {
		return (string) get_post_type();
	}

	/**
	 * The ID of the result currently in the search loop.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_search_result_id(): int {
		return (int) get_the_ID();
	}

	/**
	 * The number of result pages from the last search query.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_max_search_pages(): int {
		// search_query is always primed by a has_search_results() call before this
		// runs (the search screen and the AJAX handler both do so), and bbPress
		// initialises it to an empty WP_Query at startup, so the property is there
		// even on a request that never searched.
		return (int) bbpress()->search_query->max_num_pages;
	}

	/**
	 * The total number of results the last search query matched.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function get_search_result_count(): int {
		return (int) bbpress()->search_query->found_posts;
	}

	/**
	 * A WHERE fragment admitting every row of one post type, plus every row whose
	 * status is in a list.
	 *
	 * Raw SQL because WP_Query has no way to say it: `post_status` applies to the
	 * whole query, and a search spans three post types for which one status string
	 * means two different things (see Query\SearchVisibility). Only the table name
	 * and the fixed operators are interpolated; the post type and every status are
	 * bound as placeholders.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $exempt_post_type Post type admitted whatever its status.
	 * @param string[] $statuses         Statuses admitted for every other post type.
	 * @return string A fragment beginning with AND, or '' if there is nothing to say.
	 */
	public function post_status_where_clause( string $exempt_post_type, array $statuses ): string {
		global $wpdb;

		if ( '' === $exempt_post_type || array() === $statuses ) {
			return '';
		}

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		return (string) $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_type = %s OR {$wpdb->posts}.post_status IN ( {$placeholders} ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array_merge( array( $exempt_post_type ), array_values( $statuses ) )
		);
	}

	/**
	 * A WHERE fragment withholding a reply whose parent topic exists and is one
	 * the reader may not read.
	 *
	 * "Exists" is deliberate. `orphan()` in SearchScreenTest and CLAUDE.md's
	 * pitfall #5 both name the same real state — an import can leave `post_parent`
	 * on 0, or pointing at nothing — and this codebase's answer to it is to keep
	 * the reply discoverable under its own title, not to swallow it. So a reply
	 * is withheld only when a row of the topic post type actually sits at
	 * `post_parent` and that row's status is not in the captured list, or its
	 * password is set; a missing or wrongly-typed parent (also possible from a
	 * corrupt import) admits the reply exactly like a real, readable one does.
	 * One `NOT EXISTS` says that directly — it matches only a parent that is
	 * both a topic and unreadable, so a missing or readable parent never makes
	 * it true, with no second subquery needed to say so.
	 *
	 * Not cookie-aware: `post_password` is read as stored, not checked against
	 * `post_password_required()`, which additionally consults the `wp-postpass_*`
	 * cookie and can only be evaluated in PHP, per row, against the password the
	 * reader actually typed. So a reader who has already unlocked a topic still
	 * has its replies withheld from search until the DB row's own password is
	 * cleared. Conservative rather than wrong: it can hide a reply the reader is
	 * in fact entitled to, never the reverse. Not solved here — see the #72
	 * PR thread.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $reply_post_type Post type this predicate applies to; every
	 *                                  other post type is admitted untouched.
	 * @param string   $topic_post_type Post type a parent must carry to count.
	 * @param string[] $topic_statuses  Statuses the reader may see a topic in.
	 * @return string A fragment beginning with AND, or '' if there is nothing to say.
	 */
	public function reply_parent_where_clause( string $reply_post_type, string $topic_post_type, array $topic_statuses ): string {
		global $wpdb;

		if ( '' === $reply_post_type || '' === $topic_post_type || array() === $topic_statuses ) {
			return '';
		}

		$placeholders = implode( ', ', array_fill( 0, count( $topic_statuses ), '%s' ) );

		return (string) $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_type != %s OR NOT EXISTS ( SELECT 1 FROM {$wpdb->posts} AS bltn_parent WHERE bltn_parent.ID = {$wpdb->posts}.post_parent AND bltn_parent.post_type = %s AND ( bltn_parent.post_status NOT IN ( {$placeholders} ) OR ( bltn_parent.post_password <> '' AND bltn_parent.post_password IS NOT NULL ) ) ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array_merge( array( $reply_post_type, $topic_post_type ), array_values( $topic_statuses ) )
		);
	}

	/**
	 * Read a query variable off a query object.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed  $query The query, as a hook received it.
	 * @param string $key   Variable name.
	 * @return mixed The value, or null if there is no query to ask.
	 */
	public function get_query_arg( $query, string $key ) {
		return $query instanceof \WP_Query ? $query->get( $key ) : null;
	}

	/**
	 * Write a query variable onto a query object.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed  $query The query, as a hook received it.
	 * @param string $key   Variable name.
	 * @param mixed  $value Value to set.
	 */
	public function set_query_arg( $query, string $key, $value ): void {
		if ( $query instanceof \WP_Query ) {
			$query->set( $key, $value );
		}
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
	 * Handles of every currently enqueued script.
	 *
	 * @since 0.5.0
	 *
	 * @return string[]
	 */
	public function get_enqueued_script_handles(): array {
		$scripts = wp_scripts();
		return array_values( array_map( 'strval', (array) $scripts->queue ) );
	}

	/**
	 * Dequeue a script by handle.
	 *
	 * @since 0.5.0
	 *
	 * @param string $handle Handle.
	 */
	public function dequeue_script( string $handle ): void {
		wp_dequeue_script( $handle );
	}

	/**
	 * The registered source URL of an enqueued script, or '' if unknown.
	 *
	 * @since 0.5.0
	 *
	 * @param string $handle Handle.
	 * @return string
	 */
	public function get_script_src( string $handle ): string {
		$scripts = wp_scripts();
		if ( ! isset( $scripts->registered[ $handle ] ) ) {
			return '';
		}
		$src = $scripts->registered[ $handle ]->src;
		return is_string( $src ) ? $src : '';
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
