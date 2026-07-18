<?php
/**
 * Screen: reading view (a single topic).
 *
 * The opening post is the topic itself, rendered on its own with an "Original
 * post" chip; the replies follow in a continuous scroll. We force lead-topic
 * separation so the replies loop is replies-only — which also means the inline
 * "load more replies" control refers to replies and nothing else, keeping it
 * clear of the thread Prev/Next bar at the bottom.
 *
 * @package Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The OP is rendered separately below; the replies loop is kept replies-only by
// the explicit post_type in bltn_reply_query_args() (not by bbp_show_lead_topic,
// which only takes effect on a single-topic page and would miss the AJAX path).
$topic_id = bbp_get_topic_id();
$forum_id = bbp_get_topic_forum_id( $topic_id );
$forum    = bbp_get_forum_title( $forum_id );
$replies  = (int) bbp_get_topic_reply_count( $topic_id, true );
?>
<section class="bltn-screen">

	<?php
	bltn_app_bar(
		array(
			'title'      => $forum,
			'subtitle'   => __( 'Thread', 'bulletin' ),
			'back_url'   => bbp_get_forum_permalink( $forum_id ),
			'back_label' => __( 'Back to threads', 'bulletin' ),
			'heading'    => false, // The thread title below carries the h1.
		)
	);
	?>

	<div class="bltn-scroll" id="bltn-reading">
		<article class="bltn-thread">

			<p class="bltn-thread__label"><?php echo esc_html( $forum ); ?></p>
			<h1 class="bltn-thread__title" data-bltn-heading tabindex="-1"><?php bbp_topic_title( $topic_id ); ?></h1>
			<p class="bltn-thread__sub">
				<?php
				/* translators: %s: formatted reply count. */
				echo esc_html( sprintf( _n( '%s reply', '%s replies', $replies, 'bulletin' ), number_format_i18n( $replies ) ) );
				?>
			</p>

			<?php // --- Opening post (the topic itself) --- ?>
			<div class="bltn-post bltn-post--op" id="post-<?php echo esc_attr( $topic_id ); ?>">
				<div class="bltn-byline">
					<span class="bltn-byline__name"><?php echo esc_html( bbp_get_topic_author_display_name( $topic_id ) ); ?></span>
					<span class="bltn-byline__time"><?php echo esc_html( bbp_get_topic_post_date( $topic_id, true ) ); ?></span>
					<span class="bltn-chip"><?php esc_html_e( 'Original post', 'bulletin' ); ?></span>
				</div>
				<div class="bltn-post__body"><?php bbp_topic_content( $topic_id ); ?></div>
			</div>

			<?php // --- Replies (continuous scroll; page 1, more loaded inline) --- ?>
			<div class="bltn-replies" id="bltn-replies">
				<?php
				if ( bbp_has_replies( bltn_reply_query_args( $topic_id, 1 ) ) ) :
					while ( bbp_replies() ) :
						bbp_the_reply();
						bltn_render_reply();
					endwhile;
				endif;
				?>
			</div>

			<?php
			// Inline load-more when the topic runs past one page of replies.
			$max_pages = (int) bbpress()->reply_query->max_num_pages;
			if ( $max_pages > 1 ) :
				?>
				<div class="bltn-loadmore" data-topic="<?php echo esc_attr( $topic_id ); ?>" data-next="2" data-max="<?php echo esc_attr( $max_pages ); ?>">
					<button type="button" class="bltn-loadmore__btn"><?php esc_html_e( 'Load more replies', 'bulletin' ); ?></button>
				</div>
			<?php endif; ?>

		</article>
	</div>

	<?php bltn_thread_nav( $topic_id, $forum_id ); ?>

</section>
