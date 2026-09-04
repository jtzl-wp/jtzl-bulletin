<?php
/**
 * WordPress and bbPress REST context.
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


class RestContext implements RestContextInterface {

	private \wpdb $wpdb;


	private ?array $forum_map = null;


	private array $readable_forum_ids = array();

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/** {@inheritdoc} */
	public function query( array $args ): array {
		$args['fields'] = 'ids';

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

	/** {@inheritdoc} */
	public function user_query( array $args ): array {
		$args['fields']      = 'ID';
		$args['count_total'] = true;

		$declared = isset( $args['search_columns'] ) && is_array( $args['search_columns'] )
			? array_values( $args['search_columns'] )
			: array();

		$pin = static fn( $filtered ): array => array_values(
			array_intersect( is_array( $filtered ) ? $filtered : array(), $declared )
		);

		if ( array() !== $declared ) {
			add_filter( 'user_search_columns', $pin, PHP_INT_MAX );
		}

		try {
			$query = new \WP_User_Query( $args );
		} finally {
			if ( array() !== $declared ) {
				remove_filter( 'user_search_columns', $pin, PHP_INT_MAX );
			}
		}

		$ids   = array_values( array_map( 'intval', (array) $query->get_results() ) );
		$total = (int) $query->get_total();

		if ( array() !== $ids ) {
			cache_users( $ids );
		}

		$per_page = isset( $args['number'] ) ? max( 1, (int) $args['number'] ) : 1;

		return array(
			'ids'         => $ids,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * @param \WP_Query           $query The query that produced the page.
	 * @param array<string,mixed> $args  Its arguments.
	 * @return array{total:int,total_pages:int}
	 */
	private function totals( \WP_Query $query, array $args ): array {
		$total  = (int) $query->found_posts;
		$paged  = (int) ( $args['paged'] ?? 1 );
		$offset = (int) ( $args['offset'] ?? 0 );

		if ( 0 !== $total || ( $paged < 2 && $offset < 1 ) ) {
			return array(
				'total'       => $total,
				'total_pages' => (int) $query->max_num_pages,
			);
		}

		$per_page = max( 1, (int) $query->get( 'posts_per_page' ) );

		unset( $args['paged'], $args['offset'] );
		$args['posts_per_page'] = 1;

		$count = new \WP_Query( $args );
		$total = (int) $count->found_posts;

		wp_reset_postdata();

		return array(
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/** {@inheritdoc} */
	public function get_post( int $id ): ?\WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;

		return $post instanceof \WP_Post ? $post : null;
	}

	/** {@inheritdoc} */
	public function get_user( int $id ): ?\WP_User {
		$user = $id > 0 ? get_user_by( 'id', $id ) : false;

		return $user instanceof \WP_User ? $user : null;
	}

	/** {@inheritdoc} */
	public function get_topic_tags( int $topic_id ): array {
		$terms = get_the_terms( $topic_id, bbp_get_topic_tag_tax_id() );

		return is_array( $terms ) ? array_values( array_filter( $terms, static fn( $t ): bool => $t instanceof \WP_Term ) ) : array();
	}

	/** {@inheritdoc} */
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholder lists match the merged values.
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
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['term_id'] ] = (int) $row['visible'];
		}

		return $counts;
	}

	/** {@inheritdoc} */
	public function visible_tags( array $topic_query_args, int $page, int $per_page ): array {
		$empty = array(
			'terms'  => array(),
			'counts' => array(),
			'total'  => 0,
		);

		$forums = $this->readable_forum_ids();

		if ( array() === $forums ) {
			return $empty;
		}

		$where  = $this->visible_tag_where( $forums, $this->statuses_from( $topic_query_args ) );
		$total  = $this->visible_tag_total( $where );
		$counts = $this->visible_tag_page( $where, max( 1, $page ), max( 1, $per_page ) );

		if ( 0 === $total || array() === $counts ) {
			return array_merge( $empty, array( 'total' => $total ) );
		}

		return array(

			'terms'  => $this->terms_in_order( array_keys( $counts ) ),
			'counts' => $counts,
			'total'  => $total,
		);
	}

	/** {@inheritdoc} */
	public function topic_tags_enabled(): bool {
		return (bool) bbp_allow_topic_tags();
	}


	public function topic_tag_taxonomy(): string {
		return (string) bbp_get_topic_tag_tax_id();
	}

	/**
	 * Builds the prepared SQL visibility predicate shared by tag queries.
	 *
	 * @param int[]    $forums   Forums the reader may open.
	 * @param string[] $statuses Topic statuses the reader may see.
	 */
	private function visible_tag_where( array $forums, array $statuses ): string {
		$forum_slots  = implode( ',', array_fill( 0, count( $forums ), '%d' ) );
		$status_slots = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholder lists match the merged values.
		return (string) $this->wpdb->prepare(
			"FROM {$this->wpdb->term_relationships} tr
			   JOIN {$this->wpdb->term_taxonomy} tt
			     ON tt.term_taxonomy_id = tr.term_taxonomy_id
			   JOIN {$this->wpdb->terms} t
			     ON t.term_id = tt.term_id
			   JOIN {$this->wpdb->posts} p
			     ON p.ID = tr.object_id
			  WHERE tt.taxonomy = %s
			    AND p.post_type = %s
			    AND p.post_status IN ( {$status_slots} )
			    AND p.post_password = ''
			    AND p.post_parent IN ( {$forum_slots} )",
			array_merge(
				array( bbp_get_topic_tag_tax_id(), bbp_get_topic_post_type() ),
				$statuses,
				$forums
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}


	private function visible_tag_total( string $where ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $this->wpdb->get_var( "SELECT COUNT( DISTINCT tt.term_id ) {$where}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return array<int,int> Counts keyed by term ID, in name order.
	 */
	private function visible_tag_page( string $where, int $page, int $per_page ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT tt.term_id AS term_id, COUNT( DISTINCT p.ID ) AS visible
				 {$where}
			   GROUP BY tt.term_id
			   ORDER BY t.name ASC, tt.term_id ASC
				  LIMIT %d OFFSET %d",
				$per_page,
				( $page - 1 ) * $per_page
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['term_id'] ] = (int) $row['visible'];
		}

		return $counts;
	}

	/**
	 * @param int[] $term_ids Terms, in the order the page wants them.
	 * @return \WP_Term[]
	 */
	private function terms_in_order( array $term_ids ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => bbp_get_topic_tag_tax_id(),
				'include'    => $term_ids,
				'orderby'    => 'include',
				'hide_empty' => false,
			)
		);

		return is_array( $terms )
			? array_values( array_filter( $terms, static fn( $term ): bool => $term instanceof \WP_Term ) )
			: array();
	}

	/** {@inheritdoc} */
	public function avatar_url( int $post_id, int $size ): string {
		$author = (int) get_post_field( 'post_author', $post_id, 'raw' );

		if ( $author > 0 ) {
			return $this->user_avatar_url( $author, $size );
		}

		$email = (string) get_post_meta( $post_id, '_bbp_anonymous_email', true );

		return '' === $email ? '' : (string) get_avatar_url( $email, array( 'size' => $size ) );
	}


	public function user_avatar_url( int $user_id, int $size ): string {
		$url = get_avatar_url( $user_id, array( 'size' => $size ) );

		return is_string( $url ) ? $url : '';
	}


	public function rendered_topic_content( int $topic_id ): string {
		return (string) bbp_get_topic_content( $topic_id );
	}


	public function rendered_reply_content( int $reply_id ): string {
		return (string) bbp_get_reply_content( $reply_id );
	}

	/** {@inheritdoc} */
	public function has_stored_password( int $post_id ): bool {
		return '' !== (string) get_post_field( 'post_password', $post_id, 'raw' );
	}

	/** {@inheritdoc} */
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

	/** {@inheritdoc} */
	public function forum_post_statuses(): array {
		return array(
			bbp_get_public_status_id(),
			bbp_get_private_status_id(),
			bbp_get_hidden_status_id(),
		);
	}

	/** {@inheritdoc} */
	public function readable_forum_ids(): array {
		$reader = get_current_user_id();

		if ( isset( $this->readable_forum_ids[ $reader ] ) ) {
			return $this->readable_forum_ids[ $reader ];
		}

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

	/** {@inheritdoc} */
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolation is the posts table name.
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
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values are supplied as a three-item array.
			$clause = (string) $this->wpdb->prepare( "{$posts}.post_type NOT IN ( %s, %s, %s )", $types );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

			return " AND ( 1=1 {$where} ) AND ( {$clause} )";
		}

		$slots = implode( ',', array_fill( 0, count( $forum_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholder lists match the merged values.
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
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return " AND ( 1=1 {$where} ) AND ( {$clause} )";
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/** {@inheritdoc} */
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
	 * @param int[] $forum_ids Forums to count.
	 * @return array<int,int>
	 */
	private function subforum_counts( array $forum_ids ): array {

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
	 * @param int[]    $forum_ids Forums to count.
	 * @param string[] $statuses  Statuses this reader may see.
	 * @return array<int,array{topics:int,replies:int}>
	 */
	private function topic_counts( array $forum_ids, array $statuses ): array {
		$forum_slots  = implode( ',', array_fill( 0, count( $forum_ids ), '%d' ) );
		$status_slots = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholder lists match the merged values.
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
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['forum_id'] ] = array(
				'topics'  => (int) $row['topics'],
				'replies' => (int) $row['replies'],
			);
		}

		return $counts;
	}

	/** {@inheritdoc} */
	public function favorite_topic_ids( int $user_id ): array {
		return $this->ids( bbp_get_user_favorites_topic_ids( $user_id ) );
	}

	/** {@inheritdoc} */
	public function subscribed_topic_ids( int $user_id ): array {
		return $this->ids( bbp_get_user_subscribed_topic_ids( $user_id ) );
	}


	public function favorites_enabled(): bool {
		return (bool) bbp_is_favorites_active();
	}


	public function is_favorite( int $user_id, int $topic_id ): bool {
		return (bool) bbp_is_user_favorite( $user_id, $topic_id );
	}

	/** {@inheritdoc} */
	public function add_favorite( int $user_id, int $topic_id ): bool {
		return (bool) bbp_add_user_favorite( $user_id, $topic_id );
	}

	/** {@inheritdoc} */
	public function remove_favorite( int $user_id, int $topic_id ): bool {
		return (bool) bbp_remove_user_favorite( $user_id, $topic_id );
	}


	public function is_subscribed( int $user_id, int $object_id ): bool {
		return (bool) bbp_is_user_subscribed( $user_id, $object_id, 'post' );
	}

	/** {@inheritdoc} */
	public function add_subscription( int $user_id, int $object_id ): bool {
		return (bool) bbp_add_user_subscription( $user_id, $object_id, 'post' );
	}


	public function remove_subscription( int $user_id, int $object_id ): bool {
		return (bool) bbp_remove_user_subscription( $user_id, $object_id, 'post' );
	}

	/** {@inheritdoc} */
	public function post_date_rfc3339( int $post_id ): string {
		return $this->to_utc( (string) get_post_field( 'post_date', $post_id, 'raw' ) );
	}

	/** {@inheritdoc} */
	public function last_active_rfc3339( int $post_id ): string {
		$stored = (string) get_post_meta( $post_id, '_bbp_last_active_time', true );

		return '' !== $stored ? $this->to_utc( $stored ) : $this->post_date_rfc3339( $post_id );
	}

	/** {@inheritdoc} */
	public function registered_rfc3339( int $user_id ): string {
		$user = $this->get_user( $user_id );

		if ( null === $user || '' === (string) $user->user_registered ) {
			return '';
		}

		return (string) mysql2date( DATE_RFC3339, $user->user_registered . '+00:00', false );
	}


	public function topic_voice_count( int $topic_id ): int {
		return (int) bbp_get_topic_voice_count( $topic_id, true );
	}


	public function is_topic_sticky( int $topic_id ): bool {
		return (bool) bbp_is_topic_sticky( $topic_id, true );
	}

	/** {@inheritdoc} */
	public function normalize_status( string $status ): ?string {
		$map = array(
			bbp_get_public_status_id()  => 'published',
			bbp_get_closed_status_id()  => 'closed',
			bbp_get_pending_status_id() => 'pending',
			bbp_get_private_status_id() => 'private',
			bbp_get_hidden_status_id()  => 'hidden',
		);

		return $map[ $status ] ?? null;
	}

	/**
	 * Builds a capability-aware forum tree without exposing password-protected branches.
	 *
	 * @return array<int,array{parent:int,password:bool}>
	 */
	private function forum_map(): array {
		if ( null !== $this->forum_map ) {
			return $this->forum_map;
		}

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
	 * @param mixed $ids Whatever bbPress returned.
	 * @return int[]
	 */
	private function ids( $ids ): array {
		$clean = array_filter( array_map( 'intval', (array) $ids ), static fn( int $id ): bool => $id > 0 );

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Converts a site-local MySQL datetime to RFC 3339 UTC.
	 */
	private function to_utc( string $local ): string {
		return '' === $local ? '' : (string) get_gmt_from_date( $local, DATE_RFC3339 );
	}

	/** {@inheritdoc} */
	public function current_user_can_for( string $capability, int $object_id ): bool {
		return (bool) current_user_can( $capability, $object_id );
	}


	public function get_spam_status_id(): string {
		return (string) bbp_get_spam_status_id();
	}


	public function get_trash_status_id(): string {
		return (string) bbp_get_trash_status_id();
	}

	/** {@inheritdoc} */
	public function swap_bbp_errors( \WP_Error $fresh ): \WP_Error {
		$bbp      = bbpress();
		$previous = $bbp->errors instanceof \WP_Error ? $bbp->errors : new \WP_Error();

		$bbp->errors = $fresh;

		return $previous;
	}


	public function create_nonce( string $action ): string {
		return wp_create_nonce( $action );
	}

	/** {@inheritdoc} */
	public function run_bbp_form_handler( string $action ): void {
		match ( $action ) {
			'bbp-new-topic'  => bbp_new_topic_handler( $action ),
			'bbp-new-reply'  => bbp_new_reply_handler( $action ),
			'bbp-edit-topic' => bbp_edit_topic_handler( $action ),
			'bbp-edit-reply' => bbp_edit_reply_handler( $action ),
			default          => throw new \InvalidArgumentException( 'Unknown bbPress form action.' ),
		};
	}

	/** {@inheritdoc} */
	public function swap_handler_globals( array $values ): array {
		$server_keys = array(
			'REQUEST_METHOD',
			'HTTP_HOST',
			'REQUEST_URI',
			'HTTP_REFERER',
			'SERVER_PORT',
			'HTTPS',
		);
		$server      = array();
		foreach ( $server_keys as $key ) {
			$server[ $key ] = array(
				'exists' => array_key_exists( $key, $_SERVER ),
				'value'  => $_SERVER[ $key ] ?? null,
			);
		}

		$previous = array(
			'post'    => $_POST,    // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'request' => $_REQUEST, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'server'  => $server,
		);
		$home     = wp_parse_url( home_url( '/' ) );
		$home     = is_array( $home ) ? $home : array();
		$scheme   = isset( $home['scheme'] ) ? (string) $home['scheme'] : 'http';
		$port     = isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === $scheme ? 443 : 80 );
		$host     = isset( $home['host'] ) ? (string) $home['host'] : '';
		$host    .= isset( $home['port'] ) ? ':' . $port : '';

		$_POST                     = $values; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_REQUEST                  = $values;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_HOST']      = $host;
		$_SERVER['REQUEST_URI']    = isset( $home['path'] ) ? (string) $home['path'] : '/';
		$_SERVER['HTTP_REFERER']   = home_url( '/' );
		$_SERVER['SERVER_PORT']    = (string) $port;
		$_SERVER['HTTPS']          = 'https' === $scheme ? 'on' : 'off';

		return $previous;
	}

	/** {@inheritdoc} */
	public function restore_handler_globals( array $previous ): void {
		$_POST    = $previous['post'];    // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_REQUEST = $previous['request'];

		foreach ( $previous['server'] as $key => $state ) {
			if ( $state['exists'] ) {
				$_SERVER[ $key ] = $state['value'];
			} else {
				unset( $_SERVER[ $key ] );
			}
		}
	}


	public function current_filter_depth(): int {
		global $wp_current_filter;

		return is_array( $wp_current_filter ) ? count( $wp_current_filter ) : 0;
	}

	/** {@inheritdoc} */
	public function unwind_filters( int $depth ): void {
		global $wp_current_filter;

		if ( is_array( $wp_current_filter ) && count( $wp_current_filter ) > $depth ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- repairing the stack an exception was thrown through; see the interface.
			$wp_current_filter = array_slice( $wp_current_filter, 0, $depth );
		}
	}


	public function is_akismet_strict(): bool {
		return (bool) get_option( 'akismet_strictness' );
	}


	public function strip_markup( string $message ): string {
		return trim( (string) wp_strip_all_tags( $message ) );
	}

	/** {@inheritdoc} */
	public function slash( string $value ): string {
		return (string) wp_slash( $value );
	}

	/** {@inheritdoc} */
	public function get_post_title_raw( int $post_id ): string {
		return (string) get_post_field( 'post_title', $post_id, 'raw' );
	}

	/** {@inheritdoc} */
	public function get_post_content_raw( int $post_id ): string {
		return (string) get_post_field( 'post_content', $post_id, 'raw' );
	}

	/** {@inheritdoc} */
	public function update_display_name( int $user_id, string $name ): bool {
		$result = wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $name,
			)
		);

		return ! is_wp_error( $result );
	}
}
