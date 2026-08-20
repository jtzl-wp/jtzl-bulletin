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
}
