<?php
/**
 * Screen: reading view (a single topic).
 *
 * The opening post is the topic itself, rendered on its own with an "Original
 * post" chip; the replies follow in a continuous scroll. The replies loop is
 * kept replies-only by the explicit post_type in Query\ReplyQuery — which also
 * means the inline "load more replies" control refers to replies and nothing
 * else, keeping it clear of the thread Prev/Next bar at the bottom.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bltn_container  = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar     = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$bltn_ctx        = $bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$bltn_query      = $bltn_container->get( \JTZL\Bulletin\Query\ReplyQuery::class );
$bltn_reply_view = $bltn_container->get( \JTZL\Bulletin\View\ReplyView::class );
$bltn_navigator  = $bltn_container->get( \JTZL\Bulletin\Navigation\ThreadNavigator::class );
$bltn_navbar     = $bltn_container->get( \JTZL\Bulletin\View\ThreadNavBar::class );

$bltn_topic_id = bbp_get_topic_id();
$bltn_forum_id = bbp_get_topic_forum_id( $bltn_topic_id );
$bltn_forum    = bbp_get_forum_title( $bltn_forum_id );
$bltn_replies  = (int) bbp_get_topic_reply_count( $bltn_topic_id, true );
?>
<section class="bltn-screen">

	<?php
	$bltn_appbar->render(
		array(
			'title'      => $bltn_forum,
			'subtitle'   => __( 'Thread', 'jtzl-bulletin' ),
			'back_url'   => bbp_get_forum_permalink( $bltn_forum_id ),
			'back_label' => __( 'Back to threads', 'jtzl-bulletin' ),
			'heading'    => false, // The thread title below carries the h1.
		)
	);
	?>

	<div class="bltn-scroll" id="bltn-reading">
		<article class="bltn-thread">

			<p class="bltn-thread__label"><?php echo esc_html( $bltn_forum ); ?></p>
			<h1 class="bltn-thread__title" data-bltn-heading tabindex="-1"><?php bbp_topic_title( $bltn_topic_id ); ?></h1>
			<p class="bltn-thread__sub">
				<?php
				/* translators: %s: formatted reply count. */
				echo esc_html( sprintf( _n( '%s reply', '%s replies', $bltn_replies, 'jtzl-bulletin' ), number_format_i18n( $bltn_replies ) ) );
				?>
			</p>

			<?php // --- Opening post (the topic itself) --- ?>
			<div class="bltn-post bltn-post--op" id="post-<?php echo esc_attr( (string) $bltn_topic_id ); ?>">
				<div class="bltn-byline">
					<span class="bltn-byline__name"><?php echo esc_html( bbp_get_topic_author_display_name( $bltn_topic_id ) ); ?></span>
					<span class="bltn-byline__time"><?php echo esc_html( bbp_get_topic_post_date( $bltn_topic_id, true ) ); ?></span>
					<span class="bltn-chip"><?php esc_html_e( 'Original post', 'jtzl-bulletin' ); ?></span>
				</div>
				<div class="bltn-post__body"><?php bbp_topic_content( $bltn_topic_id ); ?></div>
			</div>

			<?php // --- Replies (continuous scroll; page 1, more loaded inline) --- ?>
			<div class="bltn-replies" id="bltn-replies">
				<?php
				if ( $bltn_ctx->has_replies( $bltn_query->args( $bltn_topic_id, 1 ) ) ) :
					while ( $bltn_ctx->the_replies_loop() ) :
						$bltn_ctx->the_reply();
						$bltn_reply_view->render();
					endwhile;
				endif;
				?>
			</div>

			<?php
			// Inline load-more when the topic runs past one page of replies.
			$bltn_max_pages = $bltn_ctx->get_max_reply_pages();
			if ( $bltn_max_pages > 1 ) :
				?>
				<div class="bltn-loadmore" data-topic="<?php echo esc_attr( (string) $bltn_topic_id ); ?>" data-next="2" data-max="<?php echo esc_attr( (string) $bltn_max_pages ); ?>">
					<button type="button" class="bltn-loadmore__btn"><?php esc_html_e( 'Load more replies', 'jtzl-bulletin' ); ?></button>
				</div>
			<?php endif; ?>

		</article>
	</div>

	<?php
	$bltn_nav_model = $bltn_navigator->locate( $bltn_topic_id, $bltn_forum_id );
	$bltn_navbar->render( $bltn_nav_model );
	?>

</section>
