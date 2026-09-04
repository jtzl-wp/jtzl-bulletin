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

$bltn_container  = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar     = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$bltn_ctx        = $bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$bltn_query      = $bltn_container->get( \JTZL\Bulletin\Query\ReplyQuery::class );
$bltn_reply_view = $bltn_container->get( \JTZL\Bulletin\View\ReplyView::class );
$bltn_navigator  = $bltn_container->get( \JTZL\Bulletin\Navigation\ThreadNavigator::class );
$bltn_navbar     = $bltn_container->get( \JTZL\Bulletin\View\ThreadNavBar::class );
$bltn_loadmore   = $bltn_container->get( \JTZL\Bulletin\View\LoadMore::class );
$bltn_mod        = $bltn_container->get( \JTZL\Bulletin\View\ModerationActions::class );
$bltn_compose    = $bltn_container->get( \JTZL\Bulletin\View\ComposeSlot::class );
$bltn_author_edit = $bltn_container->get( \JTZL\Bulletin\View\AuthorEdit::class );

$bltn_topic_id  = bbp_get_topic_id();
$bltn_forum_id  = bbp_get_topic_forum_id( $bltn_topic_id );
$bltn_forum     = bbp_get_forum_title( $bltn_forum_id );
$bltn_protected = $bltn_ctx->is_password_required( $bltn_topic_id );
$bltn_closed    = $bltn_ctx->is_topic_closed( $bltn_topic_id );
$bltn_replies   = (int) bbp_get_topic_reply_count( $bltn_topic_id, true );
$bltn_moderates = $bltn_mod->available( $bltn_topic_id );
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

	<?php if ( $bltn_protected ) : ?>

		<main class="bltn-scroll" id="bltn-reading">
			<div class="bltn-protected">
				<?php
				// bbPress's title helper echoes filtered text without contextual escaping.
				?>
				<h1 class="bltn-protected__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_topic_title( $bltn_topic_id ) ); ?></h1>
				<?php
				/*
				 * Core owns this form's authentication and markup; Bulletin only styles it.
				 */
				echo get_the_password_form( $bltn_topic_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core markup.
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
					<a class="bltn-uplink" href="<?php echo esc_url( bbp_get_forum_permalink( $bltn_forum_id ) ); ?>"><?php echo esc_html( $bltn_forum ); ?></a>
				</p>
				<?php // bbPress's title helper does not contextually escape its output. ?>
				<h1 class="bltn-thread__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_topic_title( $bltn_topic_id ) ); ?></h1>
				<p class="bltn-thread__sub">
					<?php if ( $bltn_closed ) : ?>
						<span class="bltn-closed"><?php esc_html_e( 'Closed', 'jtzl-bulletin' ); ?></span>
					<?php endif; ?>
					<span>
						<?php
						/* translators: %s: formatted reply count. */
						echo esc_html( sprintf( _n( '%s reply', '%s replies', $bltn_replies, 'jtzl-bulletin' ), number_format_i18n( $bltn_replies ) ) );
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
									'topic_id' => $bltn_topic_id,
									'before'   => '',
								)
							);
							?>
						</span>
					<?php endif; ?>
					<?php if ( $bltn_moderates ) : ?>
						<?php $bltn_mod->render_toggle(); ?>
					<?php endif; ?>
				</p>

				<?php
				/*
				 * bbPress owns the term-link markup and returns an empty string when tags
				 * are disabled or absent. The labelled group replaces its visible prefix;
				 * a space separator keeps the links readable without CSS.
				 */
				bbp_topic_tag_list(
					$bltn_topic_id,
					array(
						'before' => '<div class="bltn-tags" role="group" aria-label="' . esc_attr__( 'Thread tags', 'jtzl-bulletin' ) . '">',
						'sep'    => ' ',
						'after'  => '</div>',
					)
				);
				?>

				<?php
				if ( $bltn_moderates ) {
					$bltn_mod->render_for_topic( $bltn_topic_id );
				}
				?>

				<div class="bltn-post bltn-post--op" id="post-<?php echo esc_attr( (string) $bltn_topic_id ); ?>">
					<div class="bltn-byline">
						<span class="bltn-byline__name"><?php echo esc_html( bbp_get_topic_author_display_name( $bltn_topic_id ) ); ?></span>
						<span class="bltn-byline__time"><?php echo esc_html( bbp_get_topic_post_date( $bltn_topic_id, true ) ); ?></span>
						<?php $bltn_author_edit->render_for_topic( $bltn_topic_id ); ?>
						<span class="bltn-chip"><?php esc_html_e( 'Original post', 'jtzl-bulletin' ); ?></span>
					</div>
					<div class="bltn-post__body"><?php bbp_topic_content( $bltn_topic_id ); ?></div>
				</div>

				<div class="bltn-replies" id="bltn-replies">
					<?php
					if ( $bltn_ctx->has_replies( $bltn_query->args( $bltn_topic_id, 1 ) ) ) :
						while ( $bltn_ctx->the_replies_loop() ) :
							$bltn_ctx->the_reply();
							$bltn_reply_view->render( $bltn_topic_id );
						endwhile;
					endif;
					?>
				</div>

				<?php
				// Threaded paging uses a computed ID slice, so WP_Query cannot report
				// the full page count; ReplyQuery does.
				if ( $bltn_query->max_pages( $bltn_topic_id ) > 1 ) {
					$bltn_loadmore->render(
						array(
							'action' => 'bulletin_load_replies',
							'param'  => 'topic',
							'id'     => $bltn_topic_id,
							'target' => 'bltn-replies',
							'next'   => 2,
							'label'  => __( 'Load more replies', 'jtzl-bulletin' ),
						)
					);
				}
				?>

				<?php
				$bltn_compose->render( $bltn_topic_id, $bltn_forum_id );
				?>

			</article>
		</main>

		<?php
		/*
		 * Keep navigation after the content so DOM and visual reading order match.
		 * Its labelled nav landmark remains directly reachable.
		 */
		$bltn_nav_model = $bltn_navigator->locate( $bltn_topic_id, $bltn_forum_id );
		$bltn_navbar->render( $bltn_nav_model );
		?>

	<?php endif; ?>

</section>
