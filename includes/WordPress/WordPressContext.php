<?php
/**
 * WordPress and bbPress context.
 *
 * @package JTZL\Bulletin
 */

namespace JTZL\Bulletin\WordPress;

// phpcs:disable Generic.Commenting, Squiz.Commenting -- Contract-only and PHPStan-only docblocks intentionally omit redundant prose.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook names are supplied by the context caller.
// phpcs:disable WordPress.WP.Capabilities.Unknown -- Capabilities are registered by bbPress.
// phpcs:disable Squiz.PHP.CommentedOutCode.Found -- PHPStan directives describe inferred values.

class WordPressContext implements ContextInterface {


	private const RANK_CACHE_GROUP = 'bltn_thread_rank';


	private const RANK_CACHE_TTL = 300;

	/** {@inheritdoc} */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/** {@inheritdoc} */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}


	public function remove_filter( string $hook, string $callback, int $priority = 10 ): void {
		remove_filter( $hook, $callback, $priority );
	}

	/** {@inheritdoc} */
	public function remove_filter_callback( string $hook, callable $callback, int $priority ): void {
		remove_filter( $hook, $callback, $priority );
	}

	/** {@inheritdoc} */
	public function apply_filters( string $hook, $value, ...$args ) {
		return apply_filters( $hook, $value, ...$args );
	}

	/** {@inheritdoc} */
	public function register_rest_route( string $route_namespace, string $route, array $args ): void {
		register_rest_route( $route_namespace, $route, $args );
	}


	public function is_bbpress(): bool {
		return (bool) is_bbpress();
	}


	public function is_forum_archive(): bool {
		return (bool) bbp_is_forum_archive();
	}


	public function is_single_forum(): bool {
		return (bool) bbp_is_single_forum();
	}


	public function is_single_topic(): bool {
		return (bool) bbp_is_single_topic();
	}


	public function is_single_reply(): bool {
		return (bool) bbp_is_single_reply();
	}


	public function is_thread_replies_active(): bool {
		return (bool) bbp_thread_replies();
	}


	public function is_forum_edit(): bool {
		return (bool) bbp_is_forum_edit();
	}


	public function is_topic_edit(): bool {
		return bbp_is_topic_edit();
	}


	public function is_reply_edit(): bool {
		return bbp_is_reply_edit();
	}


	public function is_subscriptions(): bool {
		return (bool) bbp_is_subscriptions();
	}


	public function is_search(): bool {
		return (bool) bbp_is_search();
	}


	public function allow_search(): bool {
		return (bool) bbp_allow_search();
	}


	public function is_user_logged_in(): bool {
		return (bool) is_user_logged_in();
	}


	public function get_current_user_id(): int {
		return (int) get_current_user_id();
	}


	public function current_user_can( string $capability ): bool {
		return (bool) current_user_can( $capability );
	}


	public function current_user_can_edit_user( int $user_id ): bool {
		return (bool) current_user_can( 'edit_user', $user_id );
	}


	public function current_user_can_moderate( int $post_id ): bool {
		return $post_id > 0 && (bool) current_user_can( 'moderate', $post_id );
	}


	public function get_topic_moderation_links( int $topic_id ): string {
		return $this->moderation_links(
			true,
			'bbp_topic_admin_links',
			static fn(): string => (string) bbp_get_topic_admin_links(
				array(
					'id'           => $topic_id,
					'sep'          => '',

					'stick_text'   => __( 'Pin', 'jtzl-bulletin' ),
					'unstick_text' => __( 'Unpin', 'jtzl-bulletin' ),
					'super_text'   => __( 'Pin everywhere', 'jtzl-bulletin' ),
				)
			)
		);
	}


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


	public function get_topic_edit_link( int $topic_id ): string {

		return (string) bbp_get_topic_edit_link( array( 'id' => $topic_id ) );
	}


	public function get_reply_edit_link( int $reply_id ): string {
		return (string) bbp_get_reply_edit_link( array( 'id' => $reply_id ) );
	}

	/** {@inheritdoc} */
	public function get_public_topic_statuses(): array {
		return array_values( bbp_get_public_topic_statuses() );
	}

	/** {@inheritdoc} */
	public function get_public_reply_statuses(): array {
		return array_values( bbp_get_public_reply_statuses() );
	}


	public function get_pending_status_id(): string {
		return (string) bbp_get_pending_status_id();
	}


	public function get_post_author( int $post_id ): int {
		return (int) get_post_field( 'post_author', $post_id );
	}

	/** {@inheritdoc} */
	public function get_admin_only_statuses(): array {
		return array_values(
			get_post_stati(
				array(
					'protected'              => true,
					'show_in_admin_all_list' => true,
				)
			)
		);
	}

	/** {@inheritdoc} */
	public function status_exclusion_where_clause( array $statuses ): string {
		global $wpdb;

		if ( array() === $statuses ) {
			return '';
		}

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		return (string) $wpdb->prepare(
			" AND {$wpdb->posts}.post_status NOT IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders match $statuses.
			array_values( $statuses )
		);
	}

	/**
	 * Temporarily filters one bbPress link set and always restores global filter state.
	 *
	 * @param callable():string $render     Produces the markup with the filter in place.
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

			remove_filter( $filter, $without_composer );
		}

		return '' === trim( wp_strip_all_tags( $markup ) ) ? '' : $markup;
	}


	public function get_login_url( string $redirect = '' ): string {
		return (string) wp_login_url( $redirect );
	}


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


	public function can_access_create_reply_form(): bool {
		return (bool) bbp_current_user_can_access_create_reply_form();
	}


	public function can_access_create_topic_form(): bool {
		return (bool) bbp_current_user_can_access_create_topic_form();
	}


	public function is_forum_category( int $forum_id ): bool {
		return (bool) bbp_is_forum_category( $forum_id );
	}

	/** {@inheritdoc} */
	public function get_requested_reply_to(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presentation only; see the docblock.
		$raw = isset( $_REQUEST['bbp_reply_to'] ) ? absint( $_REQUEST['bbp_reply_to'] ) : 0;

		return (int) bbp_validate_reply_to( $raw );
	}

	/** {@inheritdoc} */
	public function has_query_flag( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence of a flag, value never read; see the docblock.
		return isset( $_GET[ $key ] );
	}


	public function add_query_arg( string $key, string $value, string $url ): string {
		return (string) add_query_arg( $key, rawurlencode( $value ), $url );
	}


	public function has_errors(): bool {
		return (bool) bbp_has_errors();
	}


	public function get_bloginfo( string $key ): string {
		return (string) get_bloginfo( $key );
	}


	public function get_user_profile_url( int $user_id ): string {
		return (string) bbp_get_user_profile_url( $user_id );
	}


	public function get_displayed_user_name(): string {
		return html_entity_decode(
			(string) bbp_get_displayed_user_field( 'display_name', 'raw' ),
			ENT_QUOTES,
			'UTF-8'
		);
	}


	public function get_displayed_user_id(): int {
		return (int) bbp_get_displayed_user_id();
	}


	public function get_displayed_user_nicename(): string {
		return (string) bbp_get_displayed_user_field( 'user_nicename', 'raw' );
	}


	public function get_displayed_user_role(): string {
		return (string) bbp_get_user_display_role( bbp_get_displayed_user_id() );
	}


	public function get_bbpress_screen_title(): string {
		return trim( (string) bbp_title( '', '', '' ) );
	}


	public function get_forums_url(): string {
		return (string) bbp_get_forums_url();
	}


	public function get_search_url(): string {
		return (string) bbp_get_search_url();
	}


	public function get_topic_permalink( int $topic_id ): string {
		return (string) bbp_get_topic_permalink( $topic_id );
	}


	public function get_reply_url( int $reply_id ): string {
		return (string) bbp_get_reply_url( $reply_id );
	}


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


	public function get_forum_id(): int {
		return (int) bbp_get_forum_id();
	}


	public function get_forum_permalink( int $forum_id ): string {
		return (string) bbp_get_forum_permalink( $forum_id );
	}


	public function get_forum_title( int $forum_id ): string {
		return (string) bbp_get_forum_title( $forum_id );
	}


	public function get_forum_content( int $forum_id ): string {
		return (string) bbp_get_forum_content( $forum_id );
	}


	public function get_forum_description( int $forum_id ): string {
		return wp_strip_all_tags( $this->get_forum_content( $forum_id ) );
	}


	public function get_forum_parent_id( int $forum_id ): int {
		return (int) get_post_field( 'post_parent', $forum_id, 'raw' );
	}


	public function get_forum_topic_count( int $forum_id ): int {
		return (int) bbp_get_forum_topic_count( $forum_id, true, true );
	}


	public function get_forum_last_active_id( int $forum_id ): int {
		return (int) bbp_get_forum_last_active_id( $forum_id );
	}


	public function get_forum_last_active_time( int $forum_id ): string {
		return (string) bbp_get_forum_last_active_time( $forum_id );
	}


	public function is_forum_closed( int $forum_id ): bool {

		return (bool) bbp_is_forum_closed( $forum_id, true );
	}


	public function get_reply_id(): int {
		return (int) bbp_get_reply_id();
	}


	public function get_reply_author_display_name( int $reply_id ): string {
		return (string) bbp_get_reply_author_display_name( $reply_id );
	}


	public function get_reply_post_date( int $reply_id, bool $humanize = true ): string {
		return (string) bbp_get_reply_post_date( $reply_id, $humanize );
	}

	/** {@inheritdoc} */
	public function get_reply_title( int $reply_id ): string {

		return (string) get_post_field( 'post_title', $reply_id, 'raw' );
	}


	public function get_reply_excerpt( int $reply_id, int $length ): string {
		return $this->plain_excerpt( $reply_id, static fn(): string => (string) bbp_get_reply_excerpt( $reply_id, $length ) );
	}


	public function the_reply_content( int $reply_id ): void {
		bbp_reply_content( $reply_id );
	}


	public function get_reply_to( int $reply_id ): int {
		return (int) bbp_get_reply_to( $reply_id );
	}


	public function get_reply_topic_id( int $reply_id ): int {
		return (int) bbp_get_reply_topic_id( $reply_id );
	}

	/** {@inheritdoc} */
	public function get_reply_parents( int $topic_id, array $extra_args = array() ): array {
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

		if ( bbp_get_view_all( 'edit_others_replies' ) ) {
			$post_statuses = array_keys( bbp_get_topic_statuses() );
			if ( current_user_can( 'read_private_replies' ) ) {
				$post_statuses[] = bbp_get_private_status_id();
			}
			$args['post_status'] = $post_statuses;
		} else {
			$args['perm'] = 'readable';
		}

		$args = array_merge( $args, $extra_args );

		$args = bbp_parse_args( $args, array(), 'has_replies' );

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

		update_meta_cache( 'post', $ids );

		$parents = array();
		foreach ( $ids as $id ) {
			$parents[ $id ] = (int) get_post_meta( $id, '_bbp_reply_to', true );
		}

		return $parents;
	}


	public function get_topic_id(): int {
		return (int) bbp_get_topic_id();
	}


	public function get_topic_title( int $topic_id ): string {
		return (string) bbp_get_topic_title( $topic_id );
	}


	public function get_topic_author_name( int $topic_id ): string {
		return (string) bbp_get_topic_author_display_name( $topic_id );
	}


	public function get_topic_last_active_time( int $topic_id ): string {
		return (string) bbp_get_topic_last_active_time( $topic_id );
	}

	/** {@inheritdoc} */
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

	/** {@inheritdoc} */
	public function get_excluded_forum_ids(): array {
		return array_map( 'intval', bbp_get_excluded_forum_ids() );
	}

	/** {@inheritdoc} */
	public function get_topic_last_active_datetime( int $topic_id ): string {
		$stored = (string) get_post_meta( $topic_id, '_bbp_last_active_time', true );

		if ( '' !== $stored ) {
			return $stored;
		}

		return (string) get_post_field( 'post_date', $topic_id, 'raw' );
	}

	/** {@inheritdoc} */
	public function get_topic_last_active_id( int $topic_id ): int {
		$stored = (int) get_post_meta( $topic_id, '_bbp_last_active_id', true );

		if ( $stored > 0 ) {
			return $stored;
		}

		return (int) get_post_field( 'ID', $topic_id, 'raw' );
	}


	public function get_topic_reply_count( int $topic_id ): int {
		return (int) bbp_get_topic_reply_count( $topic_id, true );
	}


	public function get_topic_forum_id( int $topic_id ): int {
		return (int) bbp_get_topic_forum_id( $topic_id );
	}


	public function get_topic_post_date( int $topic_id, bool $humanize = true ): string {
		return (string) bbp_get_topic_post_date( $topic_id, $humanize );
	}


	public function get_topic_excerpt( int $topic_id, int $length ): string {
		return $this->plain_excerpt( $topic_id, static fn(): string => (string) bbp_get_topic_excerpt( $topic_id, $length ) );
	}

	/** {@inheritdoc} */
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
	 * Prevents password-protected excerpts from disclosing stored text.
	 *
	 * @param callable $excerpt Deferred excerpt getter, called only when readable.
	 */
	private function plain_excerpt( int $post_id, callable $excerpt ): string {
		if ( post_password_required( $post_id ) ) {
			return '';
		}

		return trim( html_entity_decode( (string) $excerpt(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}


	public function is_topic_closed( int $topic_id ): bool {
		return (bool) bbp_is_topic_closed( $topic_id );
	}

	/** {@inheritdoc} */
	public function user_can_view_forum( int $forum_id ): bool {
		return (bool) bbp_user_can_view_forum(
			array(
				'forum_id'        => $forum_id,
				'check_ancestors' => true,
			)
		);
	}

	/** {@inheritdoc} */
	public function is_password_required( int $post_id ): bool {
		return (bool) post_password_required( $post_id );
	}


	public function get_topic_post_type(): string {
		return (string) bbp_get_topic_post_type();
	}


	public function get_reply_post_type(): string {
		return (string) bbp_get_reply_post_type();
	}


	public function get_public_status_id(): string {
		return (string) bbp_get_public_status_id();
	}


	public function get_closed_status_id(): string {
		return (string) bbp_get_closed_status_id();
	}


	public function get_replies_per_page(): int {
		return (int) bbp_get_replies_per_page();
	}


	public function get_forum_post_type(): string {
		return (string) bbp_get_forum_post_type();
	}


	public function get_topics_per_page(): int {
		return (int) bbp_get_topics_per_page();
	}


	public function get_forums_per_page(): int {
		$per_page = (int) get_option( '_bbp_forums_per_page', 50 );

		return $per_page > 0 ? $per_page : 50;
	}


	public function get_paged(): int {
		return max( 1, (int) bbp_get_paged() );
	}


	public function get_post_type( int $post_id ): string {
		return (string) get_post_type( $post_id );
	}


	public function get_post_status( int $post_id ): string {
		return (string) get_post_status( $post_id );
	}

	/** {@inheritdoc} */
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
	 * Topic count invalidates membership changes; the TTL bounds ordering staleness.
	 *
	 * @param string[] $statuses Post statuses the rank covers.
	 */
	private function rank_cache_key( int $forum_id, int $topic_id, array $statuses ): string {
		$count = (string) get_post_meta( $forum_id, '_bbp_topic_count', true );

		return $forum_id . '_' . $topic_id . '_' . $count . '_' . md5( implode( ',', $statuses ) );
	}

	/**
	 * Uses prepared direct SQL for bounded rank and neighbour queries.
	 *
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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- interpolation is table names and a fixed fragment; values are prepared; $scope is rank_scope()'s fragment, which the sniff cannot follow.
		$stamp = $wpdb->get_var( $wpdb->prepare( "SELECT m.meta_value {$scope} AND p.ID = %d LIMIT 1", array_merge( $where, array( $topic_id ) ) ) );

		if ( null === $stamp ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- as above.
			$rank['total'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$scope}", $where ) );

			return $rank;
		}

		$counts_args = array_merge( array( $stamp, $stamp, $topic_id, $topic_id ), $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- as above.
		$counts = (array) $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM( CASE WHEN ( m.meta_value > %s OR ( m.meta_value = %s AND p.ID > %d ) ) AND p.ID <> %d THEN 1 ELSE 0 END ) AS ahead {$scope}", $counts_args ), ARRAY_A );

		$seek = array_merge( $where, array( $stamp, $stamp, $topic_id, $topic_id ) );

		$rank['total']    = (int) ( $counts['total'] ?? 0 );
		$rank['position'] = isset( $counts['ahead'] ) ? (int) $counts['ahead'] + 1 : 0;
		$rank['prev_id']  = $this->rank_neighbour( $scope, $seek, true );
		$rank['next_id']  = $this->rank_neighbour( $scope, $seek, false );

		return $rank;
	}

	/**
	 * Shared prepared-SQL scope for every rank query.
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
	 * Excludes the subject so activity changing between queries cannot return it as its own neighbour.
	 *
	 * @param array<int,scalar> $seek    Bound values: scope values, stamp, stamp, topic ID, topic ID.
	 */
	private function rank_neighbour( string $scope, array $seek, bool $fresher ): int {
		global $wpdb;

		$cmp = $fresher ? '>' : '<';
		$dir = $fresher ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- interpolation is table names and two fixed keywords; values are prepared; $scope is rank_scope()'s fragment, which the sniff cannot follow.
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
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return (int) $id;
	}

	/** {@inheritdoc} */
	public function get_sticky_topic_ids( int $forum_id ): array {

		$stickies = array_merge(
			(array) bbp_get_super_stickies(),
			(array) bbp_get_stickies( $forum_id )
		);

		return array_values( array_filter( array_unique( array_map( 'intval', $stickies ) ) ) );
	}

	/** {@inheritdoc} */
	public function get_super_sticky_ids(): array {
		return array_values( array_filter( array_unique( array_map( 'intval', (array) bbp_get_super_stickies() ) ) ) );
	}

	/** {@inheritdoc} */
	public function has_forums( array $args ): bool {
		return (bool) bbp_has_forums( $args );
	}

	/** {@inheritdoc} */
	public function has_forum_subscriptions( array $args ): bool {
		return (bool) bbp_get_user_forum_subscriptions( $args );
	}


	public function the_forums_loop(): bool {
		return (bool) bbp_forums();
	}


	public function the_forum(): void {
		bbp_the_forum();
	}


	public function get_max_forum_pages(): int {

		return (int) bbpress()->forum_query->max_num_pages;
	}


	public function render_forum_row(): void {
		bbp_get_template_part( 'loop', 'single-forum' );
	}

	/** {@inheritdoc} */
	public function get_subscribed_forum_ids( int $user_id ): array {
		return array_values(
			array_filter(
				array_unique( array_map( 'intval', (array) bbp_get_user_subscribed_forum_ids( $user_id ) ) )
			)
		);
	}


	public function is_subscriptions_active(): bool {
		return (bool) bbp_is_subscriptions_active();
	}

	/** {@inheritdoc} */
	public function reset_postdata(): void {
		wp_reset_postdata();
	}

	/** {@inheritdoc} */
	public function has_topics( array $args ): bool {
		return (bool) bbp_has_topics( $args );
	}


	public function the_topics_loop(): bool {
		return (bool) bbp_topics();
	}


	public function the_topic(): void {
		bbp_the_topic();
	}


	public function get_max_topic_pages(): int {

		return (int) bbpress()->topic_query->max_num_pages;
	}

	/** {@inheritdoc} */
	public function has_replies( array $args ): bool {
		return (bool) bbp_has_replies( $args );
	}


	public function the_replies_loop(): bool {
		return (bool) bbp_replies();
	}


	public function the_reply(): void {
		bbp_the_reply();
	}


	public function get_max_reply_pages(): int {

		return (int) bbpress()->reply_query->max_num_pages;
	}


	public function get_search_terms(): string {
		$terms = bbp_get_search_terms();

		return $this->normalize_search_terms( is_string( $terms ) ? $terms : '' );
	}


	public function sanitize_search_request( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public read-only endpoint; see Asset\AssetManager for why there is no nonce, and LoadSearchController for what does gate the request.
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Normalizes screen terms exactly as sanitize_search_request() normalizes
	 * continuation terms — the same sanitize_text_field(), which also turns an
	 * array value into ''. That method spells the call out on the line that reads
	 * the superglobal so the sanitization sniff can see it.
	 */
	private function normalize_search_terms( string $terms ): string {
		return sanitize_text_field( $terms );
	}

	/** {@inheritdoc} */
	public function has_search_results( array $args ): bool {
		return (bool) bbp_has_search_results( $args );
	}


	public function the_search_results_loop(): bool {
		return (bool) bbp_search_results();
	}


	public function the_search_result(): void {
		bbp_the_search_result();
	}


	public function get_search_result_post_type(): string {
		return (string) get_post_type();
	}


	public function get_search_result_id(): int {
		return (int) get_the_ID();
	}


	public function get_max_search_pages(): int {

		return (int) bbpress()->search_query->max_num_pages;
	}


	public function get_search_result_count(): int {
		return (int) bbpress()->search_query->found_posts;
	}

	/** {@inheritdoc} */
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Interpolation is table names and a prepared placeholder list.
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

	/** {@inheritdoc} */
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
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

	/** {@inheritdoc} */
	public function own_pending_where_clause(
		string $where,
		string $reply_post_type,
		string $pending_status,
		int $author_id,
		int $topic_id,
		array $ids
	): string {
		global $wpdb;

		if ( '' === $reply_post_type || '' === $pending_status || $author_id < 1 || $topic_id < 1 ) {
			return $where;
		}

		if ( 1 !== preg_match( '/^\s*AND\s/i', $where ) ) {
			return $where;
		}

		$mine = (string) $wpdb->prepare(
			"{$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status = %s AND {$wpdb->posts}.post_author = %d AND {$wpdb->posts}.post_parent = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$reply_post_type,
			$pending_status,
			$author_id,
			$topic_id
		);

		$mine .= $this->id_predicate( $ids );

		return " AND ( 1=1 {$where} OR ( {$mine} ) )";
	}

	/**
	 * An empty list is intentionally unconstrained; page-scoped callers must supply their slice.
	 *
	 * @param int[] $ids Reply IDs to narrow to.
	 */
	private function id_predicate( array $ids ): string {
		global $wpdb;

		if ( array() === $ids ) {
			return '';
		}

		$clean = array_map( 'absint', $ids );

		return (string) $wpdb->prepare(
			" AND {$wpdb->posts}.ID IN ( " . implode( ', ', array_fill( 0, count( $clean ), '%d' ) ) . ' )', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$clean
		);
	}

	/** {@inheritdoc} */
	public function get_query_arg( $query, string $key ) {
		return $query instanceof \WP_Query ? $query->get( $key ) : null;
	}

	/** {@inheritdoc} */
	public function set_query_arg( $query, string $key, $value ): void {
		if ( $query instanceof \WP_Query ) {
			$query->set( $key, $value );
		}
	}

	/** {@inheritdoc} */
	public function enqueue_style( string $handle, string $src, array $deps, string $version ): void {
		wp_enqueue_style( $handle, $src, $deps, $version );
	}

	/** {@inheritdoc} */
	public function enqueue_script( string $handle, string $src, array $deps, string $version, bool $in_footer ): void {
		wp_enqueue_script( $handle, $src, $deps, $version, $in_footer );
	}

	/** {@inheritdoc} */
	public function localize_script( string $handle, string $object_name, array $data ): void {
		wp_localize_script( $handle, $object_name, $data );
	}


	public function get_ajax_url(): string {
		return (string) bbp_get_ajax_url();
	}

	/** {@inheritdoc} */
	public function get_enqueued_script_handles(): array {
		$scripts = wp_scripts();
		return array_values( array_map( 'strval', (array) $scripts->queue ) );
	}


	public function dequeue_script( string $handle ): void {
		wp_dequeue_script( $handle );
	}


	public function get_script_src( string $handle ): string {
		$scripts = wp_scripts();
		if ( ! isset( $scripts->registered[ $handle ] ) ) {
			return '';
		}
		$src = $scripts->registered[ $handle ]->src;
		return is_string( $src ) ? $src : '';
	}

	/** {@inheritdoc} */
	public function get_enqueued_style_handles(): array {
		$styles = wp_styles();
		return array_values( array_map( 'strval', (array) $styles->queue ) );
	}


	public function dequeue_style( string $handle ): void {
		wp_dequeue_style( $handle );
	}


	public function get_style_src( string $handle ): string {
		$styles = wp_styles();
		if ( ! isset( $styles->registered[ $handle ] ) ) {
			return '';
		}
		$src = $styles->registered[ $handle ]->src;
		return is_string( $src ) ? $src : '';
	}


	public function get_template_directory_uri(): string {
		return (string) get_template_directory_uri();
	}


	public function get_stylesheet_directory_uri(): string {
		return (string) get_stylesheet_directory_uri();
	}


	public function safe_redirect( string $url, int $status ): void {
		wp_safe_redirect( $url, $status );
	}

	/** @codeCoverageIgnore */
	public function terminate(): void {
		exit;
	}

	/** {@inheritdoc} */
	public function send_json_error( array $data, int $status ): never {
		wp_send_json_error( $data, $status );
	}

	/** {@inheritdoc} */
	public function send_json_success( array $data ): never {
		wp_send_json_success( $data );
	}
}
