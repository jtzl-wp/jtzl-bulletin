<?php
/**
 * Screen: one forum's threads.
 *
 * Forum header (name, description, meta, native Subscribe), then the topics
 * split into a "Pinned" section (stickies, page 1 only) and the rest. bbPress
 * prepends stickies to the same loop, so we capture each row's data during the
 * single loop and sort it into the two buckets afterwards.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bltn_container = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar    = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$bltn_threadrow = $bltn_container->get( \JTZL\Bulletin\View\ThreadRow::class );

$bltn_forum_id = bbp_get_forum_id();
?>
<section class="bltn-screen">

	<?php
	$bltn_appbar->render(
		array(
			'title'      => bbp_get_forum_title( $bltn_forum_id ),
			'subtitle'   => __( 'Forum', 'jtzl-bulletin' ),
			'back_url'   => bbp_get_forums_url(),
			'back_label' => __( 'Back to forums', 'jtzl-bulletin' ),
			'heading'    => false, // The forum header below carries the h1.
		)
	);
	?>

	<div class="bltn-scroll" id="bltn-threads">

		<div class="bltn-fhead">
			<h1 class="bltn-fhead__name" data-bltn-heading tabindex="-1"><?php bbp_forum_title( $bltn_forum_id ); ?></h1>

			<?php $bltn_fdesc = wp_strip_all_tags( bbp_get_forum_content( $bltn_forum_id ) ); ?>
			<?php if ( '' !== $bltn_fdesc ) : ?>
				<p class="bltn-fhead__desc"><?php echo esc_html( $bltn_fdesc ); ?></p>
			<?php endif; ?>

			<div class="bltn-fhead__meta">
				<?php $bltn_tcount = (int) bbp_get_forum_topic_count( $bltn_forum_id, true, true ); ?>
				<span>
					<?php
					/* translators: %s: formatted thread count. */
					echo esc_html( sprintf( _n( '%s thread', '%s threads', $bltn_tcount, 'jtzl-bulletin' ), number_format_i18n( $bltn_tcount ) ) );
					?>
				</span>
				<?php $bltn_factive = bbp_get_forum_last_active_time( $bltn_forum_id ); ?>
				<?php if ( '' !== $bltn_factive ) : ?>
					<?php /* translators: %s: human time, e.g. "2 days ago". */ ?>
					<span><?php echo esc_html( sprintf( __( 'Active %s', 'jtzl-bulletin' ), $bltn_factive ) ); ?></span>
				<?php endif; ?>

				<?php if ( bbp_is_subscriptions_active() && is_user_logged_in() ) : ?>
					<span class="bltn-fhead__sub"><?php bbp_forum_subscription_link( array( 'forum_id' => $bltn_forum_id ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( bbp_has_topics() ) : ?>

			<?php
			$bltn_pinned = array();
			$bltn_rest   = array();
			while ( bbp_topics() ) :
				bbp_the_topic();
				$bltn_topic_id = bbp_get_topic_id();
				$bltn_row      = array(
					'permalink' => bbp_get_topic_permalink( $bltn_topic_id ),
					'title'     => bbp_get_topic_title( $bltn_topic_id ),
					'author'    => bbp_get_topic_author_display_name( $bltn_topic_id ),
					'active'    => bbp_get_topic_last_active_time( $bltn_topic_id ),
					'replies'   => (int) bbp_get_topic_reply_count( $bltn_topic_id, true ),
				);
				if ( bbp_is_topic_sticky( $bltn_topic_id, false ) ) {
					$bltn_pinned[] = $bltn_row;
				} else {
					$bltn_rest[] = $bltn_row;
				}
			endwhile;
			?>

			<?php if ( ! empty( $bltn_pinned ) ) : ?>
				<p class="bltn-section-label"><?php esc_html_e( 'Pinned', 'jtzl-bulletin' ); ?></p>
				<?php
				foreach ( $bltn_pinned as $bltn_row ) {
					$bltn_threadrow->render( $bltn_row );
				}
				?>
			<?php endif; ?>

			<?php if ( ! empty( $bltn_rest ) ) : ?>
				<p class="bltn-section-label">
					<?php echo empty( $bltn_pinned ) ? esc_html__( 'Threads', 'jtzl-bulletin' ) : esc_html__( 'All threads', 'jtzl-bulletin' ); ?>
				</p>
				<?php
				foreach ( $bltn_rest as $bltn_row ) {
					$bltn_threadrow->render( $bltn_row );
				}
				?>
			<?php endif; ?>

		<?php else : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No threads yet', 'jtzl-bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'Be the first to start one.', 'jtzl-bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</div>

</section>
