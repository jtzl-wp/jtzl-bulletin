<?php
/**
 * Screen: one forum's threads.
 *
 * Forum header (name, description, meta, native Subscribe), then any sub-forums,
 * then the topics as a "Pinned" section (stickies, first page only) and the rest,
 * which pages on beyond the first 15 through an inline "load more threads". The
 * sub-forums page the same way past the first 50, through a second control.
 *
 * One "Pinned" label over two queries: site-wide super stickies rank above the
 * forum's own, so they are fetched and rendered in that order. Two labels would be
 * chrome for a distinction only a moderator makes — bbPress renders them as one
 * contiguous run too.
 *
 * On a password-protected forum the whole content area is withheld and replaced
 * by WordPress's own password form, inside our shell (issue #18) — bbPress masks
 * the description via bbp_get_forum_content(), but the sub-forum and topic loops
 * are not password-gated, so we skip them entirely rather than lean on that.
 *
 * Every loop is drained before any markup is emitted, because they share bbPress
 * globals: inside a forum loop bbp_get_forum_id() resolves to the looped
 * sub-forum rather than the forum being viewed (it tests forum_query->in_the_loop
 * before bbp_is_single_forum()). Draining is what clears in_the_loop, so the
 * ambient forum recovers on its own once a loop finishes — and $bltn_forum_id is
 * resolved once, up front, so nothing below depends on that recovery. The topics
 * query still passes an explicit post_parent, which is what actually scopes it:
 * bbPress's default reads the ambient forum, and that is not the viewed forum in
 * the AJAX continuation of this same list.
 *
 * The global $post is restored by View\ForumList and by bbPress's own loop
 * teardown (bbp_forums()/bbp_topics() call wp_reset_postdata() when have_posts()
 * comes back false), which is why the calls below are belt rather than braces.
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

// Populated by the loops below only when the forum is not protected; on a
// protected forum they stay empty and the password form renders instead.
$bltn_subforums = '';
$bltn_sub_more  = false;
$bltn_pinned    = '';
// This forum's own pinned rows, as distinct from the site-wide super stickies that
// are pinned into every forum. Only the former says anything about whether this
// forum holds content — see the empty state at the foot of this template.
$bltn_own_pinned = '';
$bltn_rest       = '';
$bltn_more       = false;
$bltn_page       = $bltn_ctx->get_paged();

if ( ! $bltn_protected ) {
	/*
	 * Sub-forums. post_parent is passed explicitly rather than relying on
	 * bbp_has_forums()'s default, which reads the same ambient forum ID this loop
	 * is about to overwrite. Visibility is bbPress's own: the query is the one the
	 * forums index uses, so a hidden or private sub-forum is filtered identically
	 * in both places.
	 *
	 * Page 1 only, continued by its own inline "load more forums" — bbPress caps a
	 * forum list at 50 with no pagination, so a parent with more children than that
	 * used to hide the rest outright (issue #38). The page is deliberately not read
	 * from the URL: on this screen `paged` already means the thread list, so one
	 * ?paged=2 cannot answer for both lists.
	 */
	$bltn_subforums = $bltn_forums->capture( $bltn_subquery->args( $bltn_forum_id, 1 ) );
	$bltn_sub_more  = 1 < $bltn_ctx->get_max_forum_pages();

	/*
	 * Topics. Categories usually hold none; ordinary forums with children may hold
	 * both. Pinned and unpinned are two queries rather than one loop sorted
	 * afterwards, because paging demands it: bbPress prepends stickies to the first
	 * page alone and never excludes them from the page they naturally fall on, so a
	 * single query would serve a pinned thread again when that page loads. TopicQuery
	 * keeps stickies out of the paginated set entirely and lists them here instead.
	 *
	 * bbPress pins on the first page only, and so do we — a reader who arrived at
	 * ?paged=3 is past the top of the list.
	 */
	if ( 1 === $bltn_page ) {
		/*
		 * Site-wide super stickies lead the section, then this forum's own —
		 * bbPress's order, which one query cannot express (see Query\TopicQuery).
		 * Each is skipped when its set is empty rather than run and discarded:
		 * WP_Query ignores an empty post__in and would answer with every topic.
		 * The guard reads the args back, so it tests the exact list the query would
		 * use rather than a second derivation that could drift from it.
		 */
		$bltn_super_args = $bltn_query->super_pinned_args();
		if ( array() !== $bltn_super_args['post__in'] ) {
			$bltn_pinned .= $bltn_threads->capture( $bltn_super_args );
		}

		/*
		 * The forum's OWN pinned topics are kept in a second variable as well as
		 * appended, because the two sets answer different questions. A site-wide
		 * super sticky is pinned into every forum, so it says nothing about whether
		 * THIS forum holds anything — and the empty state below needs to know. With
		 * one combined variable a forum with no content of its own rendered the
		 * site's notice under "Pinned", reported "0 threads" in its header, and
		 * suppressed the empty state that would have explained the gap: a screen
		 * contradicting itself and then going quiet.
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
 * Whether a thread can be started here, resolved once and used twice: the empty
 * state's copy and the fixed bar have to agree, or an empty forum invites a reader
 * to post and then withholds the control (see View\StartThread for the two
 * questions behind it — bbPress's own test does not answer for categories).
 */
$bltn_may_start = $bltn_ctx->can_access_create_topic_form()
	&& ! $bltn_ctx->is_forum_category( $bltn_forum_id );

/*
 * A sub-forum's back control returns to its parent, not the index — otherwise
 * stepping down two levels and back skips a level. The index is the app bar's
 * home button's job, which is a different journey (issue #86).
 */
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
				/*
				 * Escaped rather than echoed through bbp_forum_title(), which prints a
				 * filtered get_the_title() with no contextual escaping (issue #51,
				 * item 4). bbPress's own create and edit flows sanitise a title, so
				 * this is defence in depth rather than a known hole: an import writes
				 * straight to wp_posts, and `bbp_get_forum_title` is a filter any
				 * plugin may answer. A heading is text, and text should not be able to
				 * become markup.
				 */
				?>
				<h1 class="bltn-protected__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_forum_title( $bltn_forum_id ) ); ?></h1>
				<?php
				/*
				 * WordPress's own password form, styled by our CSS. WordPress owns
				 * the auth: the form posts to wp-login.php?action=postpass, which
				 * checks the password and sets the wp-postpass cookie. We add no
				 * custom auth — we only wrap and style what core generates.
				 */
				echo get_the_password_form( $bltn_forum_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core markup.
				?>
			</div>

		<?php else : ?>

			<div class="bltn-fhead">
				<?php if ( $bltn_parent_id ) : ?>
					<?php
					/*
					 * A child forum named only itself: "Accessories" in the bar, in the
					 * h1, and Gear nowhere on the page. With the bar's control now going
					 * to the index, this is the only route up — and the first time the
					 * parent is named at all. Top-level forums have no parent and get no
					 * kicker, so the line appears exactly where it says something.
					 */
					?>
					<p class="bltn-fhead__parent">
						<a class="bltn-uplink" href="<?php echo esc_url( bbp_get_forum_permalink( $bltn_parent_id ) ); ?>"><?php echo esc_html( bbp_get_forum_title( $bltn_parent_id ) ); ?></a>
					</p>
				<?php endif; ?>
				<?php // Escaped for the reason the protected heading above gives. ?>
				<h1 class="bltn-fhead__name" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_forum_title( $bltn_forum_id ) ); ?></h1>

				<?php $bltn_fdesc = wp_strip_all_tags( bbp_get_forum_content( $bltn_forum_id ) ); ?>
				<?php if ( '' !== $bltn_fdesc ) : ?>
					<p class="bltn-fhead__desc"><?php echo esc_html( $bltn_fdesc ); ?></p>
				<?php endif; ?>

				<div class="bltn-fhead__meta">
					<?php
					/*
					 * Closed leads the line rather than trailing it: it qualifies
					 * everything after, and the field it contradicts — freshness —
					 * is at the other end. Stated, never a demotion: a closed forum
					 * is fully readable, so nothing here is dimmed (issue #38).
					 */
					?>
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
				<?php
				// Its own append target, so later sub-forums join this section rather
				// than landing among the threads below it.
				?>
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
				<?php
				/*
				 * The appended rows land inside this element, so it wraps the
				 * unpinned rows alone: threads loaded later belong under the same
				 * label, not among the pinned ones.
				 */
				?>
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
			 * Driven by what the forum actually holds, not by its type: a category
			 * with no children still reads as empty, and a category that does hold
			 * topics directly (legal in bbPress) still shows them. Suppressing the
			 * empty state whenever sub-forums exist is what closes the dead end
			 * where a populated category announced "No threads yet".
			 *
			 * $bltn_own_pinned, not $bltn_pinned, and that is the whole of a defect
			 * the design review found. A site-wide super sticky is pinned into every
			 * forum by design (see DESIGN.md — it is how one notice reaches every
			 * forum a reader navigates to), so testing the combined set meant a forum
			 * with nothing of its own was never "empty": it rendered the site's notice
			 * under "Pinned", reported "0 threads" in its header — both true — and
			 * then suppressed the one element that would have reconciled them. The
			 * empty state now speaks for the forum's own list, which is what it was
			 * always about, and sits below the pinned section so the two read in
			 * order: here is the site's notice; this forum has nothing yet.
			 */
			?>
			<?php if ( '' === $bltn_subforums && '' === $bltn_own_pinned && '' === $bltn_rest ) : ?>

				<div class="bltn-empty">
					<p class="bltn-empty__title"><?php esc_html_e( 'No threads yet', 'jtzl-bulletin' ); ?></p>
					<?php
					/*
					 * ⚠ The copy branches on whether the reader can actually start one,
					 * and 0.5.0 is where it started to. Until P4 this read "Nothing has
					 * been posted in this forum." for everybody, because the alternative
					 * — "Be the first to start one." — invited an action no reader could
					 * take, on the one screen whose job is to explain an absence.
					 *
					 * They can now, some of them. So the invitation returns to exactly
					 * the people the bar below is already offering it to, and everyone
					 * else keeps the statement of fact. Same test, so the two can never
					 * disagree: an empty forum cannot invite a reader to post and then
					 * withhold the control.
					 */
					?>
					<?php if ( $bltn_may_start ) : ?>
						<p class="bltn-empty__body"><?php esc_html_e( 'Be the first to start one.', 'jtzl-bulletin' ); ?></p>
					<?php else : ?>
						<p class="bltn-empty__body"><?php esc_html_e( 'Nothing has been posted in this forum.', 'jtzl-bulletin' ); ?></p>
					<?php endif; ?>
				</div>

			<?php endif; ?>

			<?php
			/*
			 * The form itself, at the foot of the list — or the one line that replaces
			 * it on a closed forum (see View\StartThread). Inside <main> because it is
			 * content the reader scrolls to; the control that opens it is fixed below.
			 */
			$bltn_start->render_form( $bltn_forum_id );
			?>

		<?php endif; ?>
	</main>

	<?php
	/*
	 * "Start a thread", in the fixed bar the prototype puts here and JT approved.
	 *
	 * ⚠ A different shape from the reading view's inline composer, deliberately. That
	 * screen's bottom is spoken for by thread Prev/Next — JT's own proposal — and this
	 * one's is free. The actions differ too, which is what makes the inconsistency
	 * bearable: starting a thread is a screen-level action, available wherever you are
	 * in a long list; replying belongs where the conversation ended.
	 *
	 * Outside <main>, after it, so the bar is the last thing in the document as it is
	 * the last thing on the screen — the same sequence argument the reading view's
	 * Prev/Next bar is placed by.
	 */
	$bltn_start->render_bar( $bltn_forum_id );
	?>

</section>
