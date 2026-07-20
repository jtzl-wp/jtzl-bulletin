<?php
/**
 * Screen: one forum's threads.
 *
 * Forum header (name, description, meta, native Subscribe), then any sub-forums,
 * then the topics split into a "Pinned" section (stickies, page 1 only) and the
 * rest. bbPress prepends stickies to the same loop, so we capture each row's
 * data during the single loop and sort it into the two buckets afterwards.
 *
 * Both loops are drained into plain arrays up front, before any markup is
 * emitted, because they share bbPress globals: inside a forum loop
 * bbp_get_forum_id() resolves to the looped sub-forum rather than the forum
 * being viewed (it tests forum_query->in_the_loop before bbp_is_single_forum()).
 * Hence wp_reset_postdata() after the sub-forum loop AND an explicit post_parent
 * on the topics query — either alone leaves the other loop reading a stale
 * global on a forum that has both.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bltn_container = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar    = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$bltn_threadrow = $bltn_container->get( \JTZL\Bulletin\View\ThreadRow::class );
$bltn_forumrow  = $bltn_container->get( \JTZL\Bulletin\View\ForumRow::class );
$bltn_ctx       = $bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );

$bltn_forum_id = bbp_get_forum_id();

/*
 * Sub-forums. post_parent is passed explicitly rather than relying on
 * bbp_has_forums()'s default, which reads the same ambient forum ID this loop
 * is about to overwrite. Visibility is bbPress's own: the query is the one the
 * forums index uses, so a hidden or private sub-forum is filtered identically
 * in both places.
 */
$bltn_subforums = array();
if ( bbp_has_forums( array( 'post_parent' => $bltn_forum_id ) ) ) {
	while ( bbp_forums() ) {
		bbp_the_forum();

		$bltn_sub_id   = bbp_get_forum_id();
		$bltn_sub_desc = wp_strip_all_tags( bbp_get_forum_content( $bltn_sub_id ) );
		$bltn_sub_last = (int) bbp_get_forum_last_active_id( $bltn_sub_id );

		$bltn_subforums[] = array(
			'permalink'   => bbp_get_forum_permalink( $bltn_sub_id ),
			'title'       => bbp_get_forum_title( $bltn_sub_id ),
			'description' => '' !== $bltn_sub_desc ? wp_trim_words( $bltn_sub_desc, 22, '…' ) : '',
			'topics'      => (int) bbp_get_forum_topic_count( $bltn_sub_id, true, true ),
			'author'      => $bltn_ctx->get_author_name( $bltn_sub_last ),
			'active'      => $bltn_sub_last ? bbp_get_forum_last_active_time( $bltn_sub_id ) : '',
		);
	}
	wp_reset_postdata();
}

// Topics. Categories usually hold none; ordinary forums with children may hold both.
$bltn_pinned = array();
$bltn_rest   = array();
if ( bbp_has_topics( array( 'post_parent' => $bltn_forum_id ) ) ) {
	while ( bbp_topics() ) {
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
	}
	wp_reset_postdata();
}

/*
 * A sub-forum's back link returns to its parent, not the index — otherwise
 * stepping down two levels and back skips a level. Same single control either
 * way, so this costs no chrome.
 */
$bltn_parent_id = (int) bbp_get_forum_parent_id( $bltn_forum_id );
$bltn_back_url  = $bltn_parent_id ? bbp_get_forum_permalink( $bltn_parent_id ) : bbp_get_forums_url();
$bltn_back_text = $bltn_parent_id
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
			'back_label' => $bltn_back_text,
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

		<?php if ( ! empty( $bltn_subforums ) ) : ?>
			<p class="bltn-section-label"><?php esc_html_e( 'Forums', 'jtzl-bulletin' ); ?></p>
			<?php
			foreach ( $bltn_subforums as $bltn_sub ) {
				$bltn_forumrow->render( $bltn_sub );
			}
			?>
		<?php endif; ?>

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

		<?php
		/*
		 * Driven by what the forum actually holds, not by its type: a category
		 * with no children still reads as empty, and a category that does hold
		 * topics directly (legal in bbPress) still shows them. Suppressing the
		 * empty state whenever sub-forums exist is what closes the dead end
		 * where a populated category announced "No threads yet".
		 */
		?>
		<?php if ( empty( $bltn_subforums ) && empty( $bltn_pinned ) && empty( $bltn_rest ) ) : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No threads yet', 'jtzl-bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'Be the first to start one.', 'jtzl-bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</div>

</section>
