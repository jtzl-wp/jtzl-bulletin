<?php
/**
 * Forum-scoped thread Prev/Next.
 *
 * bbPress strips WordPress's chronological adjacent-post links (they're global,
 * not forum-scoped), so this is custom. We order every topic in the current
 * forum by freshness and step one whole thread at a time, stopping hard at the
 * forum's first and last thread — no silent wrap.
 *
 * @package Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordered list of a forum's topic IDs, freshest first.
 *
 * Cached in a transient keyed on the forum's own last-active time, so a new post
 * anywhere in the forum changes the key and the list rebuilds — no manual bust.
 *
 * Scope note: immediate forum only. Topics in sub-forums are not folded in.
 *
 * @param int $forum_id Forum ID.
 * @return int[] Topic IDs, freshest first.
 */
function bltn_forum_topic_order( $forum_id ) {
	$forum_id = (int) $forum_id;
	if ( ! $forum_id ) {
		return array();
	}

	$stamp = (string) get_post_meta( $forum_id, '_bbp_last_active_time', true );
	$key   = 'bltn_nav_' . $forum_id . '_' . md5( $stamp );

	$ids = get_transient( $key );
	if ( false !== $ids ) {
		return $ids;
	}

	$query = new WP_Query(
		array(
			'post_type'        => bbp_get_topic_post_type(),
			'post_parent'      => $forum_id,
			'post_status'      => array( bbp_get_public_status_id(), bbp_get_closed_status_id() ),
			'posts_per_page'   => -1,
			'meta_key'         => '_bbp_last_active_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'          => 'meta_value',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);

	$ids = array_map( 'intval', $query->posts );
	set_transient( $key, $ids, HOUR_IN_SECONDS );

	return $ids;
}

/**
 * Render the fixed bottom bar with forum-scoped thread Prev/Next.
 *
 * @param int $topic_id Current topic.
 * @param int $forum_id Its forum.
 */
function bltn_thread_nav( $topic_id, $forum_id ) {
	$order = bltn_forum_topic_order( $forum_id );
	$pos   = array_search( (int) $topic_id, $order, true );

	$prev_url = '';
	$next_url = '';
	$count    = '';

	if ( false !== $pos ) {
		$total = count( $order );
		if ( $pos > 0 ) {
			$prev_url = bbp_get_topic_permalink( $order[ $pos - 1 ] );
		}
		if ( $pos < $total - 1 ) {
			$next_url = bbp_get_topic_permalink( $order[ $pos + 1 ] );
		}
		/* translators: 1: current thread number, 2: total threads in the forum. */
		$count = sprintf( __( 'Thread %1$d of %2$d', 'bulletin' ), $pos + 1, $total );
	}

	$chevron_left  = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
	$chevron_right = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';
	?>
	<nav class="bltn-navbar" aria-label="<?php esc_attr_e( 'Threads in this forum', 'bulletin' ); ?>">
		<?php if ( '' !== $prev_url ) : ?>
			<a class="bltn-navbtn bltn-navbtn--prev" href="<?php echo esc_url( $prev_url ); ?>" rel="prev">
				<?php echo $chevron_left; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<?php esc_html_e( 'Prev', 'bulletin' ); ?>
			</a>
		<?php else : ?>
			<span class="bltn-navbtn bltn-navbtn--prev" aria-disabled="true">
				<?php echo $chevron_left; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
				<?php esc_html_e( 'Prev', 'bulletin' ); ?>
			</span>
		<?php endif; ?>

		<span class="bltn-navbar__count"><?php echo esc_html( $count ); ?></span>

		<?php if ( '' !== $next_url ) : ?>
			<a class="bltn-navbtn bltn-navbtn--next" href="<?php echo esc_url( $next_url ); ?>" rel="next">
				<?php esc_html_e( 'Next', 'bulletin' ); ?>
				<?php echo $chevron_right; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			</a>
		<?php else : ?>
			<span class="bltn-navbtn bltn-navbtn--next" aria-disabled="true">
				<?php esc_html_e( 'Next', 'bulletin' ); ?>
				<?php echo $chevron_right; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
			</span>
		<?php endif; ?>
	</nav>
	<?php
}
