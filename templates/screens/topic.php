<?php
/**
 * Screen: topic reading view.
 *
 * Password-protected topics use WordPress's password form and skip the reply
 * loop, which bbPress does not password-gate.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jtzl_bltn_container  = \JTZL\Bulletin\Plugin::get_container();
$jtzl_bltn_appbar     = $jtzl_bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$jtzl_bltn_ctx        = $jtzl_bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$jtzl_bltn_query      = $jtzl_bltn_container->get( \JTZL\Bulletin\Query\ReplyQuery::class );
$jtzl_bltn_reply_view = $jtzl_bltn_container->get( \JTZL\Bulletin\View\ReplyView::class );
$jtzl_bltn_navigator  = $jtzl_bltn_container->get( \JTZL\Bulletin\Navigation\ThreadNavigator::class );
$jtzl_bltn_navbar     = $jtzl_bltn_container->get( \JTZL\Bulletin\View\ThreadNavBar::class );
$jtzl_bltn_loadmore   = $jtzl_bltn_container->get( \JTZL\Bulletin\View\LoadMore::class );
$jtzl_bltn_mod        = $jtzl_bltn_container->get( \JTZL\Bulletin\View\ModerationActions::class );
$jtzl_bltn_compose    = $jtzl_bltn_container->get( \JTZL\Bulletin\View\ComposeSlot::class );
$jtzl_bltn_author_edit = $jtzl_bltn_container->get( \JTZL\Bulletin\View\AuthorEdit::class );

$jtzl_bltn_topic_id  = bbp_get_topic_id();
$jtzl_bltn_forum_id  = bbp_get_topic_forum_id( $jtzl_bltn_topic_id );
$jtzl_bltn_forum     = bbp_get_forum_title( $jtzl_bltn_forum_id );
$jtzl_bltn_protected = $jtzl_bltn_ctx->is_password_required( $jtzl_bltn_topic_id );
$jtzl_bltn_closed    = $jtzl_bltn_ctx->is_topic_closed( $jtzl_bltn_topic_id );
$jtzl_bltn_replies   = (int) bbp_get_topic_reply_count( $jtzl_bltn_topic_id, true );
$jtzl_bltn_moderates = $jtzl_bltn_mod->available( $jtzl_bltn_topic_id );
?>
<section class="bltn-screen">

	<?php
	$jtzl_bltn_appbar->render(
		array(
			'title'      => $jtzl_bltn_forum,
			'subtitle'   => __( 'Thread', 'jtzl-bulletin' ),
			'back_url'   => bbp_get_forum_permalink( $jtzl_bltn_forum_id ),
			'back_label' => __( 'Back to threads', 'jtzl-bulletin' ),
			'heading'    => false, // The thread title below carries the h1.
		)
	);
	?>

	<?php if ( $jtzl_bltn_protected ) : ?>

		<main class="bltn-scroll" id="bltn-reading">
			<div class="bltn-protected">
				<?php
				// bbPress's title helper echoes filtered text without contextual escaping.
				?>
				<h1 class="bltn-protected__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_topic_title( $jtzl_bltn_topic_id ) ); ?></h1>
				<?php
				/*
				 * Core owns this form's authentication and markup; Bulletin only styles it.
				 */
				echo get_the_password_form( $jtzl_bltn_topic_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core markup.
				?>
			</div>
		</main>

	<?php else : ?>

		<main class="bltn-scroll" id="bltn-reading">
			<article class="bltn-thread">

				<?php
				// The app bar links to the index; this link returns to the current forum.
				?>
				<p class="bltn-thread__label">
					<a class="bltn-uplink" href="<?php echo esc_url( bbp_get_forum_permalink( $jtzl_bltn_forum_id ) ); ?>"><?php echo esc_html( $jtzl_bltn_forum ); ?></a>
				</p>
				<?php // bbPress's title helper does not contextually escape its output. ?>
				<h1 class="bltn-thread__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_topic_title( $jtzl_bltn_topic_id ) ); ?></h1>
				<p class="bltn-thread__sub">
					<?php if ( $jtzl_bltn_closed ) : ?>
						<span class="bltn-closed"><?php esc_html_e( 'Closed', 'jtzl-bulletin' ); ?></span>
					<?php endif; ?>
					<span>
						<?php
						/* translators: %s: formatted reply count. */
						echo esc_html( sprintf( _n( '%s reply', '%s replies', $jtzl_bltn_replies, 'jtzl-bulletin' ), number_format_i18n( $jtzl_bltn_replies ) ) );
						?>
					</span>
					<?php if ( bbp_is_subscriptions_active() && is_user_logged_in() ) : ?>
						<?php
						// Drop bbPress's default " | " separator to match the forum control.
						?>
						<span class="bltn-thread__subscribe">
							<?php
							bbp_topic_subscription_link(
								array(
									'topic_id' => $jtzl_bltn_topic_id,
									'before'   => '',
								)
							);
							?>
						</span>
					<?php endif; ?>
					<?php if ( $jtzl_bltn_moderates ) : ?>
						<?php $jtzl_bltn_mod->render_toggle(); ?>
					<?php endif; ?>
				</p>

				<?php
				/*
				 * bbPress owns the term-link markup and returns an empty string when tags
				 * are disabled or absent. The labelled group replaces its visible prefix;
				 * a space separator keeps the links readable without CSS.
				 */
				bbp_topic_tag_list(
					$jtzl_bltn_topic_id,
					array(
						'before' => '<div class="bltn-tags" role="group" aria-label="' . esc_attr__( 'Thread tags', 'jtzl-bulletin' ) . '">',
						'sep'    => ' ',
						'after'  => '</div>',
					)
				);
				?>

				<?php
				if ( $jtzl_bltn_moderates ) {
					$jtzl_bltn_mod->render_for_topic( $jtzl_bltn_topic_id );
				}
				?>

				<div class="bltn-post bltn-post--op" id="post-<?php echo esc_attr( (string) $jtzl_bltn_topic_id ); ?>">
					<div class="bltn-byline">
						<span class="bltn-byline__name"><?php echo esc_html( bbp_get_topic_author_display_name( $jtzl_bltn_topic_id ) ); ?></span>
						<span class="bltn-byline__time"><?php echo esc_html( bbp_get_topic_post_date( $jtzl_bltn_topic_id, true ) ); ?></span>
						<?php $jtzl_bltn_author_edit->render_for_topic( $jtzl_bltn_topic_id ); ?>
						<span class="bltn-chip"><?php esc_html_e( 'Original post', 'jtzl-bulletin' ); ?></span>
					</div>
					<div class="bltn-post__body"><?php bbp_topic_content( $jtzl_bltn_topic_id ); ?></div>
				</div>

				<div class="bltn-replies" id="bltn-replies">
					<?php
					if ( $jtzl_bltn_ctx->has_replies( $jtzl_bltn_query->args( $jtzl_bltn_topic_id, 1 ) ) ) :
						while ( $jtzl_bltn_ctx->the_replies_loop() ) :
							$jtzl_bltn_ctx->the_reply();
							$jtzl_bltn_reply_view->render( $jtzl_bltn_topic_id );
						endwhile;
					endif;
					?>
				</div>

				<?php
				// Threaded paging uses a computed ID slice, so WP_Query cannot report
				// the full page count; ReplyQuery does.
				if ( $jtzl_bltn_query->max_pages( $jtzl_bltn_topic_id ) > 1 ) {
					$jtzl_bltn_loadmore->render(
						array(
							'action' => 'bulletin_load_replies',
							'param'  => 'topic',
							'id'     => $jtzl_bltn_topic_id,
							'target' => 'bltn-replies',
							'next'   => 2,
							'label'  => __( 'Load more replies', 'jtzl-bulletin' ),
						)
					);
				}
				?>

				<?php
				$jtzl_bltn_compose->render( $jtzl_bltn_topic_id, $jtzl_bltn_forum_id );
				?>

			</article>
		</main>

		<?php
		/*
		 * Keep navigation after the content so DOM and visual reading order match.
		 * Its labelled nav landmark remains directly reachable.
		 */
		$jtzl_bltn_nav_model = $jtzl_bltn_navigator->locate( $jtzl_bltn_topic_id, $jtzl_bltn_forum_id );
		$jtzl_bltn_navbar->render( $jtzl_bltn_nav_model );
		?>

	<?php endif; ?>

</section>
