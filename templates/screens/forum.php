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

$bltn_container = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar    = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$bltn_threads   = $bltn_container->get( \JTZL\Bulletin\View\ThreadList::class );
$bltn_loadmore  = $bltn_container->get( \JTZL\Bulletin\View\LoadMore::class );
$bltn_forums    = $bltn_container->get( \JTZL\Bulletin\View\ForumList::class );
$bltn_subquery  = $bltn_container->get( \JTZL\Bulletin\Query\ForumQuery::class );
$bltn_query     = $bltn_container->get( \JTZL\Bulletin\Query\TopicQuery::class );
$bltn_ctx       = $bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$bltn_start     = $bltn_container->get( \JTZL\Bulletin\View\StartThread::class );

$bltn_forum_id  = bbp_get_forum_id();
$bltn_protected = $bltn_ctx->is_password_required( $bltn_forum_id );
$bltn_closed    = $bltn_ctx->is_forum_closed( $bltn_forum_id );

$bltn_subforums = '';
$bltn_sub_more  = false;
$bltn_pinned    = '';
// Super stickies do not count as content belonging to this forum.
$bltn_own_pinned = '';
$bltn_rest       = '';
$bltn_more       = false;
$bltn_page       = $bltn_ctx->get_paged();

if ( ! $bltn_protected ) {
	/*
	 * Pass the parent explicitly because bbPress loops mutate the ambient forum ID.
	 * Sub-forums paginate independently; the URL page belongs to the topic list.
	 */
	$bltn_subforums = $bltn_forums->capture( $bltn_subquery->args( $bltn_forum_id, 1 ) );
	$bltn_sub_more  = 1 < $bltn_ctx->get_max_forum_pages();

	/*
	 * TopicQuery excludes pinned topics from the paginated set to prevent duplicates.
	 * Like bbPress, this template shows pinned topics only on the first page.
	 */
	if ( 1 === $bltn_page ) {
		/*
		 * Super stickies precede forum stickies. Guard empty post__in arrays because
		 * WP_Query would otherwise return every topic.
		 */
		$bltn_super_args = $bltn_query->super_pinned_args();
		if ( array() !== $bltn_super_args['post__in'] ) {
			$bltn_pinned .= $bltn_threads->capture( $bltn_super_args );
		}

		/*
		 * Keep forum stickies separate so a global sticky alone does not suppress the
		 * forum's empty state.
		 */
		$bltn_own_pinned_args = $bltn_query->forum_pinned_args( $bltn_forum_id );
		if ( array() !== $bltn_own_pinned_args['post__in'] ) {
			$bltn_own_pinned = $bltn_threads->capture( $bltn_own_pinned_args );
			$bltn_pinned    .= $bltn_own_pinned;
		}
		wp_reset_postdata();
	}

	$bltn_rest = $bltn_threads->capture( $bltn_query->args( $bltn_forum_id, $bltn_page ) );
	$bltn_more = $bltn_page < $bltn_ctx->get_max_topic_pages();
	wp_reset_postdata();
}

/*
 * Reuse this result so the empty-state copy and fixed action cannot disagree.
 */
$bltn_may_start = $bltn_ctx->can_access_create_topic_form()
	&& ! $bltn_ctx->is_forum_category( $bltn_forum_id );

// Child forums return to their parent; top-level forums return to the index.
$bltn_parent_id = (int) bbp_get_forum_parent_id( $bltn_forum_id );
$bltn_back_url  = $bltn_parent_id ? bbp_get_forum_permalink( $bltn_parent_id ) : bbp_get_forums_url();
$bltn_back_label = $bltn_parent_id
	/* translators: %s: parent forum name. */
	? sprintf( __( 'Back to %s', 'jtzl-bulletin' ), bbp_get_forum_title( $bltn_parent_id ) )
	: __( 'Back to forums', 'jtzl-bulletin' );
?>
<section class="bltn-screen">

	<?php
	$bltn_appbar->render(
		array(
			'title'      => bbp_get_forum_title( $bltn_forum_id ),
			'subtitle'   => __( 'Forum', 'jtzl-bulletin' ),
			'back_url'   => $bltn_back_url,
			'back_label' => $bltn_back_label,
			'heading'    => false, // The forum header (or the protected heading) below carries the h1.
		)
	);
	?>

	<main class="bltn-scroll" id="bltn-threads">

		<?php if ( $bltn_protected ) : ?>

			<div class="bltn-protected">
				<?php
				// bbPress's title helper echoes filtered text without contextual escaping.
				?>
				<h1 class="bltn-protected__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_forum_title( $bltn_forum_id ) ); ?></h1>
				<?php
				/*
				 * Core owns this form's authentication and markup; Bulletin only styles it.
				 */
				echo get_the_password_form( $bltn_forum_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core markup.
				?>
			</div>

		<?php else : ?>

			<div class="bltn-fhead">
				<?php if ( $bltn_parent_id ) : ?>
					<p class="bltn-fhead__parent">
						<a class="bltn-uplink" href="<?php echo esc_url( bbp_get_forum_permalink( $bltn_parent_id ) ); ?>"><?php echo esc_html( bbp_get_forum_title( $bltn_parent_id ) ); ?></a>
					</p>
				<?php endif; ?>
				<?php // bbPress's title helper does not contextually escape its output. ?>
				<h1 class="bltn-fhead__name" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_forum_title( $bltn_forum_id ) ); ?></h1>

				<?php $bltn_fdesc = wp_strip_all_tags( bbp_get_forum_content( $bltn_forum_id ) ); ?>
				<?php if ( '' !== $bltn_fdesc ) : ?>
					<p class="bltn-fhead__desc"><?php echo esc_html( $bltn_fdesc ); ?></p>
				<?php endif; ?>

				<div class="bltn-fhead__meta">
					<?php if ( $bltn_closed ) : ?>
						<span class="bltn-closed"><?php esc_html_e( 'Closed', 'jtzl-bulletin' ); ?></span>
					<?php endif; ?>
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

			<?php if ( '' !== $bltn_subforums ) : ?>
				<p class="bltn-section-label"><?php esc_html_e( 'Forums', 'jtzl-bulletin' ); ?></p>
				<div id="bltn-subforums-list">
					<?php
					// Rows are built by View\ForumRow, which escapes every field.
					echo $bltn_subforums; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>

				<?php
				if ( $bltn_sub_more ) {
					$bltn_loadmore->render(
						array(
							'action' => 'bulletin_load_forums',
							'param'  => 'forum',
							'id'     => $bltn_forum_id,
							'target' => 'bltn-subforums-list',
							'next'   => 2,
							'label'  => __( 'Load more forums', 'jtzl-bulletin' ),
						)
					);
				}
				?>
			<?php endif; ?>

			<?php if ( '' !== $bltn_pinned ) : ?>
				<p class="bltn-section-label"><?php esc_html_e( 'Pinned', 'jtzl-bulletin' ); ?></p>
				<?php
				// Rows are built by View\ThreadRow, which escapes every field.
				echo $bltn_pinned; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php endif; ?>

			<?php if ( '' !== $bltn_rest ) : ?>
				<p class="bltn-section-label">
					<?php echo '' === $bltn_pinned ? esc_html__( 'Threads', 'jtzl-bulletin' ) : esc_html__( 'All threads', 'jtzl-bulletin' ); ?>
				</p>
				<div id="bltn-threads-list">
					<?php
					// Rows are built by View\ThreadRow, which escapes every field.
					echo $bltn_rest; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>

				<?php
				if ( $bltn_more ) {
					$bltn_loadmore->render(
						array(
							'action' => 'bulletin_load_topics',
							'param'  => 'forum',
							'id'     => $bltn_forum_id,
							'target' => 'bltn-threads-list',
							'next'   => $bltn_page + 1,
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
			<?php if ( '' === $bltn_subforums && '' === $bltn_own_pinned && '' === $bltn_rest ) : ?>

				<div class="bltn-empty">
					<p class="bltn-empty__title"><?php esc_html_e( 'No threads yet', 'jtzl-bulletin' ); ?></p>
					<?php if ( $bltn_may_start ) : ?>
						<p class="bltn-empty__body"><?php esc_html_e( 'Be the first to start one.', 'jtzl-bulletin' ); ?></p>
					<?php else : ?>
						<p class="bltn-empty__body"><?php esc_html_e( 'Nothing has been posted in this forum.', 'jtzl-bulletin' ); ?></p>
					<?php endif; ?>
				</div>

			<?php endif; ?>

			<?php
			$bltn_start->render_form( $bltn_forum_id );
			?>

		<?php endif; ?>
	</main>

	<?php
	/*
	 * Starting a thread is a fixed screen-level action; replying remains inline with
	 * the conversation. Keep this bar after main so DOM and visual order match.
	 */
	$bltn_start->render_bar( $bltn_forum_id );
	?>

</section>
