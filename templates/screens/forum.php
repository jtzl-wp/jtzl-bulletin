<?php
/**
 * Screen: one forum's threads.
 *
 * Forum header (name, description, meta, native Subscribe), then the topics
 * split into a "Pinned" section (stickies, page 1 only) and the rest. bbPress
 * prepends stickies to the same loop, so we capture each row's data during the
 * single loop and sort it into the two buckets afterwards.
 *
 * @package Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forum_id = bbp_get_forum_id();
?>
<section class="bltn-screen">

	<?php
	bltn_app_bar(
		array(
			'title'    => bbp_get_forum_title( $forum_id ),
			'subtitle' => __( 'Forum', 'bulletin' ),
			'back_url' => bbp_get_forums_url(),
			'back_label' => __( 'Back to forums', 'bulletin' ),
			'heading'  => false, // The forum header below carries the h1.
		)
	);
	?>

	<div class="bltn-scroll" id="bltn-threads">

		<div class="bltn-fhead">
			<h1 class="bltn-fhead__name" data-bltn-heading tabindex="-1"><?php bbp_forum_title( $forum_id ); ?></h1>

			<?php $fdesc = wp_strip_all_tags( bbp_get_forum_content( $forum_id ) ); ?>
			<?php if ( '' !== $fdesc ) : ?>
				<p class="bltn-fhead__desc"><?php echo esc_html( $fdesc ); ?></p>
			<?php endif; ?>

			<div class="bltn-fhead__meta">
				<?php $tcount = (int) bbp_get_forum_topic_count( $forum_id, true, true ); ?>
				<span>
					<?php
					/* translators: %s: formatted thread count. */
					echo esc_html( sprintf( _n( '%s thread', '%s threads', $tcount, 'bulletin' ), number_format_i18n( $tcount ) ) );
					?>
				</span>
				<?php $factive = bbp_get_forum_last_active_time( $forum_id ); ?>
				<?php if ( '' !== $factive ) : ?>
					<?php /* translators: %s: human time, e.g. "2 days ago". */ ?>
					<span><?php echo esc_html( sprintf( __( 'Active %s', 'bulletin' ), $factive ) ); ?></span>
				<?php endif; ?>

				<?php if ( bbp_is_subscriptions_active() && is_user_logged_in() ) : ?>
					<span class="bltn-fhead__sub"><?php bbp_forum_subscription_link( array( 'forum_id' => $forum_id ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( bbp_has_topics() ) : ?>

			<?php
			$pinned = array();
			$rest   = array();
			while ( bbp_topics() ) :
				bbp_the_topic();
				$topic_id = bbp_get_topic_id();
				$row      = array(
					'permalink' => bbp_get_topic_permalink( $topic_id ),
					'title'     => bbp_get_topic_title( $topic_id ),
					'author'    => bbp_get_topic_author_display_name( $topic_id ),
					'active'    => bbp_get_topic_last_active_time( $topic_id ),
					'replies'   => (int) bbp_get_topic_reply_count( $topic_id, true ),
				);
				if ( bbp_is_topic_sticky( $topic_id, false ) ) {
					$pinned[] = $row;
				} else {
					$rest[] = $row;
				}
			endwhile;
			?>

			<?php if ( ! empty( $pinned ) ) : ?>
				<p class="bltn-section-label"><?php esc_html_e( 'Pinned', 'bulletin' ); ?></p>
				<?php array_walk( $pinned, 'bltn_render_thread_row' ); ?>
			<?php endif; ?>

			<?php if ( ! empty( $rest ) ) : ?>
				<p class="bltn-section-label">
					<?php echo empty( $pinned ) ? esc_html__( 'Threads', 'bulletin' ) : esc_html__( 'All threads', 'bulletin' ); ?>
				</p>
				<?php array_walk( $rest, 'bltn_render_thread_row' ); ?>
			<?php endif; ?>

		<?php else : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No threads yet', 'bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'Be the first to start one.', 'bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</div>

</section>
