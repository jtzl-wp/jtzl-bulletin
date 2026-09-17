<?php
/**
 * Screen: one forum's threads.
 *
 * Loops are captured before rendering because they share bbPress global state.
 * Password-protected forums use WordPress's password form and skip the loops,
 * which bbPress does not password-gate.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jtzl_bltn_container = \JTZL\Bulletin\Plugin::get_container();
$jtzl_bltn_appbar    = $jtzl_bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$jtzl_bltn_threads   = $jtzl_bltn_container->get( \JTZL\Bulletin\View\ThreadList::class );
$jtzl_bltn_loadmore  = $jtzl_bltn_container->get( \JTZL\Bulletin\View\LoadMore::class );
$jtzl_bltn_forums    = $jtzl_bltn_container->get( \JTZL\Bulletin\View\ForumList::class );
$jtzl_bltn_subquery  = $jtzl_bltn_container->get( \JTZL\Bulletin\Query\ForumQuery::class );
$jtzl_bltn_query     = $jtzl_bltn_container->get( \JTZL\Bulletin\Query\TopicQuery::class );
$jtzl_bltn_ctx       = $jtzl_bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$jtzl_bltn_start     = $jtzl_bltn_container->get( \JTZL\Bulletin\View\StartThread::class );

$jtzl_bltn_forum_id  = bbp_get_forum_id();
$jtzl_bltn_protected = $jtzl_bltn_ctx->is_password_required( $jtzl_bltn_forum_id );
$jtzl_bltn_closed    = $jtzl_bltn_ctx->is_forum_closed( $jtzl_bltn_forum_id );

$jtzl_bltn_subforums = '';
$jtzl_bltn_sub_more  = false;
$jtzl_bltn_pinned    = '';
// Super stickies do not count as content belonging to this forum.
$jtzl_bltn_own_pinned = '';
$jtzl_bltn_rest       = '';
$jtzl_bltn_more       = false;
$jtzl_bltn_page       = $jtzl_bltn_ctx->get_paged();

if ( ! $jtzl_bltn_protected ) {
	/*
	 * Pass the parent explicitly because bbPress loops mutate the ambient forum ID.
	 * Sub-forums paginate independently; the URL page belongs to the topic list.
	 */
	$jtzl_bltn_subforums = $jtzl_bltn_forums->capture( $jtzl_bltn_subquery->args( $jtzl_bltn_forum_id, 1 ) );
	$jtzl_bltn_sub_more  = 1 < $jtzl_bltn_ctx->get_max_forum_pages();

	/*
	 * TopicQuery excludes pinned topics from the paginated set to prevent duplicates.
	 * Like bbPress, this template shows pinned topics only on the first page.
	 */
	if ( 1 === $jtzl_bltn_page ) {
		/*
		 * Super stickies precede forum stickies. Guard empty post__in arrays because
		 * WP_Query would otherwise return every topic.
		 */
		$jtzl_bltn_super_args = $jtzl_bltn_query->super_pinned_args();
		if ( array() !== $jtzl_bltn_super_args['post__in'] ) {
			$jtzl_bltn_pinned .= $jtzl_bltn_threads->capture( $jtzl_bltn_super_args );
		}

		/*
		 * Keep forum stickies separate so a global sticky alone does not suppress the
		 * forum's empty state.
		 */
		$jtzl_bltn_own_pinned_args = $jtzl_bltn_query->forum_pinned_args( $jtzl_bltn_forum_id );
		if ( array() !== $jtzl_bltn_own_pinned_args['post__in'] ) {
			$jtzl_bltn_own_pinned = $jtzl_bltn_threads->capture( $jtzl_bltn_own_pinned_args );
			$jtzl_bltn_pinned    .= $jtzl_bltn_own_pinned;
		}
		wp_reset_postdata();
	}

	$jtzl_bltn_rest = $jtzl_bltn_threads->capture( $jtzl_bltn_query->args( $jtzl_bltn_forum_id, $jtzl_bltn_page ) );
	$jtzl_bltn_more = $jtzl_bltn_page < $jtzl_bltn_ctx->get_max_topic_pages();
	wp_reset_postdata();
}

/*
 * Reuse this result so the empty-state copy and fixed action cannot disagree.
 */
$jtzl_bltn_may_start = $jtzl_bltn_ctx->can_access_create_topic_form()
	&& ! $jtzl_bltn_ctx->is_forum_category( $jtzl_bltn_forum_id );

// Child forums return to their parent; top-level forums return to the index.
$jtzl_bltn_parent_id = (int) bbp_get_forum_parent_id( $jtzl_bltn_forum_id );
$jtzl_bltn_back_url  = $jtzl_bltn_parent_id ? bbp_get_forum_permalink( $jtzl_bltn_parent_id ) : bbp_get_forums_url();
$jtzl_bltn_back_label = $jtzl_bltn_parent_id
	/* translators: %s: parent forum name. */
	? sprintf( __( 'Back to %s', 'jtzl-bulletin' ), bbp_get_forum_title( $jtzl_bltn_parent_id ) )
	: __( 'Back to forums', 'jtzl-bulletin' );
?>
<section class="bltn-screen">

	<?php
	$jtzl_bltn_appbar->render(
		array(
			'title'      => bbp_get_forum_title( $jtzl_bltn_forum_id ),
			'subtitle'   => __( 'Forum', 'jtzl-bulletin' ),
			'back_url'   => $jtzl_bltn_back_url,
			'back_label' => $jtzl_bltn_back_label,
			'heading'    => false, // The forum header (or the protected heading) below carries the h1.
		)
	);
	?>

	<main class="bltn-scroll" id="bltn-threads">

		<?php if ( $jtzl_bltn_protected ) : ?>

			<div class="bltn-protected">
				<?php
				// bbPress's title helper echoes filtered text without contextual escaping.
				?>
				<h1 class="bltn-protected__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_forum_title( $jtzl_bltn_forum_id ) ); ?></h1>
				<?php
				/*
				 * Core owns this form's authentication and markup; Bulletin only styles it.
				 */
				echo get_the_password_form( $jtzl_bltn_forum_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core markup.
				?>
			</div>

		<?php else : ?>

			<div class="bltn-fhead">
				<?php if ( $jtzl_bltn_parent_id ) : ?>
					<p class="bltn-fhead__parent">
						<a class="bltn-uplink" href="<?php echo esc_url( bbp_get_forum_permalink( $jtzl_bltn_parent_id ) ); ?>"><?php echo esc_html( bbp_get_forum_title( $jtzl_bltn_parent_id ) ); ?></a>
					</p>
				<?php endif; ?>
				<?php // bbPress's title helper does not contextually escape its output. ?>
				<h1 class="bltn-fhead__name" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_forum_title( $jtzl_bltn_forum_id ) ); ?></h1>

				<?php $jtzl_bltn_fdesc = wp_strip_all_tags( bbp_get_forum_content( $jtzl_bltn_forum_id ) ); ?>
				<?php if ( '' !== $jtzl_bltn_fdesc ) : ?>
					<p class="bltn-fhead__desc"><?php echo esc_html( $jtzl_bltn_fdesc ); ?></p>
				<?php endif; ?>

				<div class="bltn-fhead__meta">
					<?php if ( $jtzl_bltn_closed ) : ?>
						<span class="bltn-closed"><?php esc_html_e( 'Closed', 'jtzl-bulletin' ); ?></span>
					<?php endif; ?>
					<?php $jtzl_bltn_tcount = (int) bbp_get_forum_topic_count( $jtzl_bltn_forum_id, true, true ); ?>
					<span>
						<?php
						/* translators: %s: formatted thread count. */
						echo esc_html( sprintf( _n( '%s thread', '%s threads', $jtzl_bltn_tcount, 'jtzl-bulletin' ), number_format_i18n( $jtzl_bltn_tcount ) ) );
						?>
					</span>
					<?php $jtzl_bltn_factive = bbp_get_forum_last_active_time( $jtzl_bltn_forum_id ); ?>
					<?php if ( '' !== $jtzl_bltn_factive ) : ?>
						<?php /* translators: %s: human time, e.g. "2 days ago". */ ?>
						<span><?php echo esc_html( sprintf( __( 'Active %s', 'jtzl-bulletin' ), $jtzl_bltn_factive ) ); ?></span>
					<?php endif; ?>

					<?php if ( bbp_is_subscriptions_active() && is_user_logged_in() ) : ?>
						<span class="bltn-fhead__sub"><?php bbp_forum_subscription_link( array( 'forum_id' => $jtzl_bltn_forum_id ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( '' !== $jtzl_bltn_subforums ) : ?>
				<p class="bltn-section-label"><?php esc_html_e( 'Forums', 'jtzl-bulletin' ); ?></p>
				<div id="bltn-subforums-list">
					<?php
					// Rows are built by View\ForumRow, which escapes every field.
					echo $jtzl_bltn_subforums; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>

				<?php
				if ( $jtzl_bltn_sub_more ) {
					$jtzl_bltn_loadmore->render(
						array(
							'action' => 'bulletin_load_forums',
							'param'  => 'forum',
							'id'     => $jtzl_bltn_forum_id,
							'target' => 'bltn-subforums-list',
							'next'   => 2,
							'label'  => __( 'Load more forums', 'jtzl-bulletin' ),
						)
					);
				}
				?>
			<?php endif; ?>

			<?php if ( '' !== $jtzl_bltn_pinned ) : ?>
				<p class="bltn-section-label"><?php esc_html_e( 'Pinned', 'jtzl-bulletin' ); ?></p>
				<?php
				// Rows are built by View\ThreadRow, which escapes every field.
				echo $jtzl_bltn_pinned; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php endif; ?>

			<?php if ( '' !== $jtzl_bltn_rest ) : ?>
				<p class="bltn-section-label">
					<?php echo '' === $jtzl_bltn_pinned ? esc_html__( 'Threads', 'jtzl-bulletin' ) : esc_html__( 'All threads', 'jtzl-bulletin' ); ?>
				</p>
				<div id="bltn-threads-list">
					<?php
					// Rows are built by View\ThreadRow, which escapes every field.
					echo $jtzl_bltn_rest; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>

				<?php
				if ( $jtzl_bltn_more ) {
					$jtzl_bltn_loadmore->render(
						array(
							'action' => 'bulletin_load_topics',
							'param'  => 'forum',
							'id'     => $jtzl_bltn_forum_id,
							'target' => 'bltn-threads-list',
							'next'   => $jtzl_bltn_page + 1,
							'label'  => __( 'Load more threads', 'jtzl-bulletin' ),
						)
					);
				}
				?>
			<?php endif; ?>

			<?php
			/*
			 * Empty means no child forums and no topics owned by this forum. Global
			 * super stickies are excluded because they do not belong to this forum.
			 */
			?>
			<?php if ( '' === $jtzl_bltn_subforums && '' === $jtzl_bltn_own_pinned && '' === $jtzl_bltn_rest ) : ?>

				<div class="bltn-empty">
					<p class="bltn-empty__title"><?php esc_html_e( 'No threads yet', 'jtzl-bulletin' ); ?></p>
					<?php if ( $jtzl_bltn_may_start ) : ?>
						<p class="bltn-empty__body"><?php esc_html_e( 'Be the first to start one.', 'jtzl-bulletin' ); ?></p>
					<?php else : ?>
						<p class="bltn-empty__body"><?php esc_html_e( 'Nothing has been posted in this forum.', 'jtzl-bulletin' ); ?></p>
					<?php endif; ?>
				</div>

			<?php endif; ?>

			<?php
			$jtzl_bltn_start->render_form( $jtzl_bltn_forum_id );
			?>

		<?php endif; ?>
	</main>

	<?php
	/*
	 * Starting a thread is a fixed screen-level action; replying remains inline with
	 * the conversation. Keep this bar after main so DOM and visual order match.
	 */
	$jtzl_bltn_start->render_bar( $jtzl_bltn_forum_id );
	?>

</section>
