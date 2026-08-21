<?php
/**
 * The REST layer's WordPress/bbPress adapter.
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
 * One-line delegations to WordPress and bbPress, plus the few prepared queries that
 * belong to this boundary rather than to a caller.
 *
 * The sibling of WordPressContext, and the same rule applies: this and that class are
 * the only places in the plugin that touch those globals, so everything above them can
 * be tested against a double. Where a question has an answer in bbPress, the answer
 * comes from bbPress — a reader's forum visibility, a topic's voice count, an avatar —
 * because a second implementation of a rule bbPress already owns is a second
 * implementation to keep in step.
 *
 * @since 0.6.0
 */
class RestContext implements RestContextInterface {

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 * @since 0.6.0
	 */
	private \wpdb $wpdb;

	/**
	 * Every forum on the site as `ID => array{parent:int,password:bool}`, or null
	 * before it has been asked for.
	 *
	 * @var array<int,array{parent:int,password:bool}>|null
	 * @since 0.6.0
	 */
	private ?array $forum_map = null;

	/**
	 * Readable, password-free forum IDs, keyed by the reader they were computed for.
	 *
	 * ⚠ Keyed by user, not a bare list. A request has one reader, so a bare memo would
	 * be correct in production and quietly wrong anywhere the current user changes —
	 * a test walking a matrix of roles, or any future code that answers for somebody
	 * other than the caller. The answer depends on who is asking, so the cache does
	 * too.
	 *
	 * @var array<int,int[]>
	 * @since 0.6.0
	 */
	private array $readable_forum_ids = array();

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param \wpdb $wpdb WordPress database handle.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Run a post query and report only what a collection needs.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $args WP_Query arguments.
	 * @return array{ids:int[],total:int,total_pages:int}
	 */
	public function query( array $args ): array {
		$args['fields'] = 'ids';
		// Totals are the point of this method: a collection cannot send X-WP-Total
		// without them, and an argument builder that turned them off for speed would
		// silently make every page claim to be the only one.
		$args['no_found_rows'] = false;

		$query = new \WP_Query( $args );
		$ids   = array_map( 'intval', (array) $query->posts );

		wp_reset_postdata();

		$totals = $this->totals( $query, $args );

		return array(
			'ids'         => array_values( $ids ),
			'total'       => $totals['total'],
			'total_pages' => $totals['total_pages'],
		);
	}

	/**
	 * How many rows the collection holds, and how many pages that is.
	 *
	 * ⚠ **WordPress does not count a page past the end.** `WP_Query::set_found_posts()`
	 * returns early when the page came back as an empty array, so `found_posts` and
	 * `max_num_pages` are both 0 — and a client paging to the end would be told the
	 * collection it has just finished reading is empty. Worse, the two answers
	 * disagree: page 1 of the same collection says 40. The contract promises
	 * `X-WP-Total` describes the collection, not the page, so an empty page past the
	 * first is counted again without its paging. Core's own posts controller does the
	 * same thing for the same reason.
	 *
	 * The extra query costs nothing on the path that matters: it runs only when a page
	 * came back empty and was not the first. "Not the first" is read off `paged`,
	 * which is how every collection in this plugin pages; a caller that paged with
	 * `offset` instead would get WordPress's 0 back, and would need this to ask about
	 * that too.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_Query           $query The query that produced the page.
	 * @param array<string,mixed> $args  Its arguments.
	 * @return array{total:int,total_pages:int}
	 */
	private function totals( \WP_Query $query, array $args ): array {
		$total = (int) $query->found_posts;
		$paged = (int) ( $args['paged'] ?? 1 );

		if ( 0 !== $total || $paged < 2 ) {
			return array(
				'total'       => $total,
				'total_pages' => (int) $query->max_num_pages,
			);
		}

		// One row, not a page of them. `SQL_CALC_FOUND_ROWS` is added whenever there is
		// any LIMIT at all, so the count is the same either way and this stops the
		// recount materialising up to a hundred IDs it would immediately discard.
		//
		// ⚠ **But the page count then has to be worked out here.** WordPress derives
		// `max_num_pages` as `ceil( found_posts / posts_per_page )`, so asking for one
		// row makes it report one page per row — a collection of 40 would come back as
		// 40 pages. The size the *caller* asked for is what the pages are counted in,
		// and it is read off the finished query rather than the arguments because
		// WP_Query writes its default back into its own query vars when a caller
		// leaves `posts_per_page` out.
		$per_page = max( 1, (int) $query->get( 'posts_per_page' ) );

		unset( $args['paged'] );
		$args['posts_per_page'] = 1;

		$count = new \WP_Query( $args );
		$total = (int) $count->found_posts;

		wp_reset_postdata();

		return array(
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * One post, or null when there is no such post.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|null
	 */
	public function get_post( int $id ): ?\WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;

		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * One user, or null when there is no such user.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id User ID.
	 * @return \WP_User|null
	 */
	public function get_user( int $id ): ?\WP_User {
		$user = $id > 0 ? get_user_by( 'id', $id ) : false;

		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * A topic's tags, as terms.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return \WP_Term[]
	 */
	public function get_topic_tags( int $topic_id ): array {
		$terms = get_the_terms( $topic_id, bbp_get_topic_tag_tax_id() );

		return is_array( $terms ) ? array_values( array_filter( $terms, static fn( $t ): bool => $t instanceof \WP_Term ) ) : array();
	}

	/**
	 * How many topics each of these tags carries that this reader may see.
	 *
	 * The statuses come from the caller's own topic query, so the count and the
	 * collection it describes can never be answered from two different capability
	 * lists. The forum scope is this reader's readable, password-free set.
	 *
	 * @since 0.6.0
	 *
	 * @param int[]               $term_ids         Tags to count.
	 * @param array<string,mixed> $topic_query_args The caller's topic query, for its statuses.
	 * @return array<int,int>
	 */
	public function visible_tag_counts( array $term_ids, array $topic_query_args ): array {
		$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
		$counts   = array_fill_keys( $term_ids, 0 );
		$forums   = $this->readable_forum_ids();
		$statuses = $this->statuses_from( $topic_query_args );

		if ( array() === $term_ids || array() === $forums ) {
			return $counts;
		}

		$term_slots   = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
		$forum_slots  = implode( ',', array_fill( 0, count( $forums ), '%d' ) );
		$status_slots = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT tt.term_id AS term_id, COUNT( DISTINCT p.ID ) AS visible
				   FROM {$this->wpdb->term_relationships} tr
				   JOIN {$this->wpdb->term_taxonomy} tt
				     ON tt.term_taxonomy_id = tr.term_taxonomy_id
				   JOIN {$this->wpdb->posts} p
				     ON p.ID = tr.object_id
				  WHERE tt.taxonomy = %s
				    AND tt.term_id IN ( {$term_slots} )
				    AND p.post_type = %s
				    AND p.post_status IN ( {$status_slots} )
				    AND p.post_password = ''
				    AND p.post_parent IN ( {$forum_slots} )
			   GROUP BY tt.term_id",
				array_merge(
					array( bbp_get_topic_tag_tax_id() ),
					$term_ids,
					array( bbp_get_topic_post_type() ),
					$statuses,
					$forums
				)
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['term_id'] ] = (int) $row['visible'];
		}

		return $counts;
	}

	/**
	 * The avatar for whoever wrote a post, at one size.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 * @param int $size    Pixels.
	 * @return string
	 */
	public function avatar_url( int $post_id, int $size ): string {
		$author = (int) get_post_field( 'post_author', $post_id, 'raw' );

		if ( $author > 0 ) {
			return $this->user_avatar_url( $author, $size );
		}

		// An anonymous post keeps its author's email in meta. It resolves the avatar
		// and goes no further: AuthorSerializer never puts it in a response.
		$email = (string) get_post_meta( $post_id, '_bbp_anonymous_email', true );

		return '' === $email ? '' : (string) get_avatar_url( $email, array( 'size' => $size ) );
	}

	/**
	 * The avatar for a user, at one size.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id User ID.
	 * @param int $size    Pixels.
	 * @return string
	 */
	public function user_avatar_url( int $user_id, int $size ): string {
		$url = get_avatar_url( $user_id, array( 'size' => $size ) );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * A topic's opening post as rendered HTML.
	 *
	 * ⚠ bbPress returns WordPress's password form from this call when the post is
	 * protected. Rest\AccessPolicy refuses a protected topic with 403 long before a
	 * serializer runs, so this is a second floor rather than the one that holds: if
	 * it ever fires, the response carries a login form instead of somebody's words —
	 * useless, but not a disclosure.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return string
	 */
	public function rendered_topic_content( int $topic_id ): string {
		return (string) bbp_get_topic_content( $topic_id );
	}

	/**
	 * A reply as rendered HTML.
	 *
	 * @since 0.6.0
	 *
	 * @param int $reply_id Reply ID.
	 * @return string
	 */
	public function rendered_reply_content( int $reply_id ): string {
		return (string) bbp_get_reply_content( $reply_id );
	}

	/**
	 * Whether this post has a password of its own.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function has_stored_password( int $post_id ): bool {
		return '' !== (string) get_post_field( 'post_password', $post_id, 'raw' );
	}

	/**
	 * Whether this forum, or any forum above it, has a password.
	 *
	 * @since 0.6.0
	 *
	 * @param int $forum_id Forum ID.
	 * @return bool
	 */
	public function has_password_in_forum_chain( int $forum_id ): bool {
		$map  = $this->forum_map();
		$seen = array();

		while ( $forum_id > 0 && isset( $map[ $forum_id ] ) && ! isset( $seen[ $forum_id ] ) ) {
			if ( $map[ $forum_id ]['password'] ) {
				return true;
			}

			$seen[ $forum_id ] = true;
			$forum_id          = $map[ $forum_id ]['parent'];
		}

		return false;
	}

	/**
	 * The post statuses a forum can legitimately have.
	 *
	 * @since 0.6.0
	 *
	 * @return string[]
	 */
	public function forum_post_statuses(): array {
		return array(
			bbp_get_public_status_id(),
			bbp_get_private_status_id(),
			bbp_get_hidden_status_id(),
		);
	}

	/**
	 * Every forum this reader may open, with no password anywhere in its chain.
	 *
	 * @since 0.6.0
	 *
	 * @return int[]
	 */
	public function readable_forum_ids(): array {
		$reader = get_current_user_id();

		if ( isset( $this->readable_forum_ids[ $reader ] ) ) {
			return $this->readable_forum_ids[ $reader ];
		}

		// bbPress already answers the capability half, for private and hidden forums
		// together, in one call — and answers it the way the website answers it.
		$excluded = array_flip( array_map( 'intval', (array) bbp_get_excluded_forum_ids() ) );
		$readable = array();

		foreach ( array_keys( $this->forum_map() ) as $forum_id ) {
			if ( ! isset( $excluded[ $forum_id ] ) && ! $this->has_password_in_forum_chain( $forum_id ) ) {
				$readable[] = $forum_id;
			}
		}

		$this->readable_forum_ids[ $reader ] = $readable;

		return $readable;
	}

	/**
	 * Narrow a WHERE clause to content inside a set of forums.
	 *
	 * @since 0.6.0
	 *
	 * @param string $where      Clause built so far.
	 * @param int[]  $forum_ids  Forums the reader may open.
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
	): string {
		if ( 1 !== preg_match( '/^\s*AND\s/i', $where ) ) {
			return $where;
		}

		$posts     = $this->wpdb->posts;
		$forum_ids = array_values( array_filter( array_map( 'intval', $forum_ids ) ) );
		$types     = array( $forum_type, $topic_type, $reply_type );

		if ( array() === $forum_ids ) {
			// A reader who may open no forum at all may still be running a query that
			// returns something else entirely, so this excludes the three kinds rather
			// than returning nothing.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$clause = (string) $this->wpdb->prepare( "{$posts}.post_type NOT IN ( %s, %s, %s )", $types );

			return " AND ( 1=1 {$where} ) AND ( {$clause} )";
		}

		$slots = implode( ',', array_fill( 0, count( $forum_ids ), '%d' ) );

		// A forum by its own ID; a topic by the forum it sits in and its own password;
		// a reply by the topic it answers, which carries both. The last branch leaves
		// anything that is not one of bbPress's three kinds alone.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clause = (string) $this->wpdb->prepare(
			"( {$posts}.post_type = %s AND {$posts}.ID IN ( {$slots} ) )
			 OR ( {$posts}.post_type = %s AND {$posts}.post_password = '' AND {$posts}.post_parent IN ( {$slots} ) )
			 OR ( {$posts}.post_type = %s AND EXISTS (
			        SELECT 1 FROM {$posts} bltn_parent
			         WHERE bltn_parent.ID = {$posts}.post_parent
			           AND bltn_parent.post_type = %s
			           AND bltn_parent.post_password = ''
			           AND bltn_parent.post_parent IN ( {$slots} )
			      ) )
			 OR {$posts}.post_type NOT IN ( %s, %s, %s )",
			array_merge(
				array( $forum_type ),
				$forum_ids,
				array( $topic_type ),
				$forum_ids,
				array( $reply_type, $topic_type ),
				$forum_ids,
				$types
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return " AND ( 1=1 {$where} ) AND ( {$clause} )";
	}

	/**
	 * Sub-forum, topic and reply counts for a set of forums, reader-visible only.
	 *
	 * @since 0.6.0
	 *
	 * @param int[]    $forum_ids Forums to count.
	 * @param string[] $statuses  Topic/reply statuses this reader may see.
	 * @return array<int,array{subforums:int,topics:int,replies:int}>
	 */
	public function forum_counts( array $forum_ids, array $statuses ): array {
		$forum_ids = array_values( array_filter( array_map( 'intval', $forum_ids ) ) );
		$counts    = array_fill_keys(
			$forum_ids,
			array(
				'subforums' => 0,
				'topics'    => 0,
				'replies'   => 0,
			)
		);

		$readable = array_values( array_intersect( $this->readable_forum_ids(), $forum_ids ) );

		if ( array() === $readable || array() === $statuses ) {
			return $counts;
		}

		foreach ( $this->subforum_counts( $readable ) as $forum_id => $total ) {
			$counts[ $forum_id ]['subforums'] = $total;
		}

		foreach ( $this->topic_counts( $readable, $statuses ) as $forum_id => $pair ) {
			$counts[ $forum_id ]['topics']  = $pair['topics'];
			$counts[ $forum_id ]['replies'] = $pair['replies'];
		}

		return $counts;
	}

	/**
	 * How many readable sub-forums each of these forums has.
	 *
	 * @since 0.6.0
	 *
	 * @param int[] $forum_ids Forums to count.
	 * @return array<int,int>
	 */
	private function subforum_counts( array $forum_ids ): array {
		// ⚠ Both lists are non-empty by precondition: forum_counts() is the only
		// caller and returns before reaching here when the reader's readable set
		// intersects nothing. A guard here could never fire, and an unreachable guard
		// reads as a case somebody thought about — the same reasoning
		// Unread\ReadState states over forums_holding_unread().
		$readable = $this->readable_forum_ids();

		$parent_slots   = implode( ',', array_fill( 0, count( $forum_ids ), '%d' ) );
		$readable_slots = implode( ',', array_fill( 0, count( $readable ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT post_parent AS forum_id, COUNT( ID ) AS total
				   FROM {$this->wpdb->posts}
				  WHERE post_type = %s
				    AND post_parent IN ( {$parent_slots} )
				    AND ID IN ( {$readable_slots} )
			   GROUP BY post_parent",
				array_merge( array( bbp_get_forum_post_type() ), $forum_ids, $readable )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['forum_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * How many readable topics and replies each of these forums holds.
	 *
	 * Replies are counted through their topic rather than through their own
	 * `_bbp_forum_id` meta, so a reply whose meta drifted from its thread's forum is
	 * counted where its thread actually is — the same place the list would show it.
	 *
	 * @since 0.6.0
	 *
	 * @param int[]    $forum_ids Forums to count.
	 * @param string[] $statuses  Statuses this reader may see.
	 * @return array<int,array{topics:int,replies:int}>
	 */
	private function topic_counts( array $forum_ids, array $statuses ): array {
		$forum_slots  = implode( ',', array_fill( 0, count( $forum_ids ), '%d' ) );
		$status_slots = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT t.post_parent AS forum_id,
				        COUNT( DISTINCT t.ID ) AS topics,
				        COUNT( r.ID ) AS replies
				   FROM {$this->wpdb->posts} t
			  LEFT JOIN {$this->wpdb->posts} r
				     ON r.post_parent = t.ID
				    AND r.post_type = %s
				    AND r.post_status IN ( {$status_slots} )
				  WHERE t.post_type = %s
				    AND t.post_status IN ( {$status_slots} )
				    AND t.post_password = ''
				    AND t.post_parent IN ( {$forum_slots} )
			   GROUP BY t.post_parent",
				array_merge(
					array( bbp_get_reply_post_type() ),
					$statuses,
					array( bbp_get_topic_post_type() ),
					$statuses,
					$forum_ids
				)
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['forum_id'] ] = array(
				'topics'  => (int) $row['topics'],
				'replies' => (int) $row['replies'],
			);
		}

		return $counts;
	}

	/**
	 * Topics a member has favourited.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id Member ID.
	 * @return int[]
	 */
	public function favorite_topic_ids( int $user_id ): array {
		return $this->ids( bbp_get_user_favorites_topic_ids( $user_id ) );
	}

	/**
	 * Topics a member subscribes to.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id Member ID.
	 * @return int[]
	 */
	public function subscribed_topic_ids( int $user_id ): array {
		return $this->ids( bbp_get_user_subscribed_topic_ids( $user_id ) );
	}

	/**
	 * A post's creation time as an RFC 3339 string in UTC.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function post_date_rfc3339( int $post_id ): string {
		return $this->to_utc( (string) get_post_field( 'post_date', $post_id, 'raw' ) );
	}

	/**
	 * A topic's or forum's last-activity time as an RFC 3339 string in UTC.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Topic or forum ID.
	 * @return string
	 */
	public function last_active_rfc3339( int $post_id ): string {
		$stored = (string) get_post_meta( $post_id, '_bbp_last_active_time', true );

		return '' !== $stored ? $this->to_utc( $stored ) : $this->post_date_rfc3339( $post_id );
	}

	/**
	 * When a member joined, as an RFC 3339 string in UTC.
	 *
	 * @since 0.6.0
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function registered_rfc3339( int $user_id ): string {
		$user = $this->get_user( $user_id );

		if ( null === $user || '' === (string) $user->user_registered ) {
			return '';
		}

		// Already UTC in the database, so it is formatted rather than converted.
		return (string) mysql2date( DATE_RFC3339, $user->user_registered . '+00:00', false );
	}

	/**
	 * How many distinct people have posted in a topic.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return int
	 */
	public function topic_voice_count( int $topic_id ): int {
		return (int) bbp_get_topic_voice_count( $topic_id, true );
	}

	/**
	 * Whether a topic is pinned, in its own forum or site-wide.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Topic ID.
	 * @return bool
	 */
	public function is_topic_sticky( int $topic_id ): bool {
		return (bool) bbp_is_topic_sticky( $topic_id, true );
	}

	/**
	 * A WordPress post status as the app's vocabulary, or null.
	 *
	 * @since 0.6.0
	 *
	 * @param string $status WordPress post status.
	 * @return string|null
	 */
	public function normalize_status( string $status ): ?string {
		$map = array(
			bbp_get_public_status_id()  => 'published',
			bbp_get_closed_status_id()  => 'closed',
			bbp_get_pending_status_id() => 'pending',
			bbp_get_private_status_id() => 'private',
			bbp_get_hidden_status_id()  => 'hidden',
		);

		// Anything unlisted — spam, trash, a status another plugin invented — has no
		// app-facing name on purpose. The caller decides what to do with null; what it
		// must not do is invent a word for it.
		return $map[ $status ] ?? null;
	}

	/**
	 * Every forum on the site, with its parent and whether it carries a password.
	 *
	 * One query for the whole tree, because both questions asked of it — "is anything
	 * above this protected?" and "which forums may this reader open?" — walk the same
	 * chains, and per-forum lookups would multiply by depth.
	 *
	 * @since 0.6.0
	 *
	 * @return array<int,array{parent:int,password:bool}>
	 */
	private function forum_map(): array {
		if ( null !== $this->forum_map ) {
			return $this->forum_map;
		}

		// A trashed or spammed forum is not part of the tree any reader walks, so it
		// neither hides its children behind a password nor joins a readable set.
		$statuses     = $this->forum_post_statuses();
		$status_slots = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT ID, post_parent, post_password
				   FROM {$this->wpdb->posts}
				  WHERE post_type = %s
				    AND post_status IN ( {$status_slots} )",
				array_merge( array( bbp_get_forum_post_type() ), $statuses )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row['ID'] ] = array(
				'parent'   => (int) $row['post_parent'],
				'password' => '' !== (string) $row['post_password'],
			);
		}

		$this->forum_map = $map;

		return $map;
	}

	/**
	 * The statuses a caller's topic query names, or bbPress's public pair.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $args Topic query arguments.
	 * @return string[]
	 */
	private function statuses_from( array $args ): array {
		$statuses = $args['post_status'] ?? array();
		$statuses = array_values( array_filter( array_map( 'strval', (array) $statuses ) ) );

		return array() === $statuses
			? array( bbp_get_public_status_id(), bbp_get_closed_status_id() )
			: $statuses;
	}

	/**
	 * Positive, unique, integer IDs, reindexed.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $ids Whatever bbPress returned.
	 * @return int[]
	 */
	private function ids( $ids ): array {
		$clean = array_filter( array_map( 'intval', (array) $ids ), static fn( int $id ): bool => $id > 0 );

		return array_values( array_unique( $clean ) );
	}

	/**
	 * A site-local MySQL datetime as RFC 3339 in UTC.
	 *
	 * `get_gmt_from_date()` parses in the site's timezone, applies whatever DST was in
	 * force on that date, converts, and emits an explicit `+00:00`.
	 *
	 * @since 0.6.0
	 *
	 * @param string $local MySQL datetime in site time.
	 * @return string
	 */
	private function to_utc( string $local ): string {
		return '' === $local ? '' : (string) get_gmt_from_date( $local, DATE_RFC3339 );
	}
}
