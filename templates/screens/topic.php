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
 * On a password-protected topic the opening post, replies, and thread Prev/Next
 * are withheld and replaced by WordPress's own password form, inside our shell
 * (issue #18). bbPress masks the opening-post body via bbp_topic_content(), but
 * the replies loop is not password-gated, so we branch on the whole content
 * rather than lean on that self-masking.
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
				/*
				 * Escaped rather than echoed through bbp_topic_title(), which prints a
				 * filtered get_the_title() with no contextual escaping (issue #51,
				 * item 4). bbPress's own create and edit flows sanitise a title, so
				 * this is defence in depth rather than a known hole: an import writes
				 * straight to wp_posts, and `bbp_get_topic_title` is a filter any
				 * plugin may answer. A heading is text, and text should not be able to
				 * become markup.
				 */
				?>
				<h1 class="bltn-protected__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_topic_title( $bltn_topic_id ) ); ?></h1>
				<?php
				/*
				 * WordPress's own password form, styled by our CSS. WordPress owns
				 * the auth: the form posts to wp-login.php?action=postpass, which
				 * checks the password and sets the wp-postpass cookie. We add no
				 * custom auth — we only wrap and style what core generates.
				 */
				echo get_the_password_form( $bltn_topic_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core markup.
				?>
			</div>
		</main>

	<?php else : ?>

		<main class="bltn-scroll" id="bltn-reading">
			<article class="bltn-thread">

				<?php
				/*
				 * The way up, and the only one on this screen: the app bar's control
				 * goes to the forums index now, so a reader wanting this thread's
				 * siblings needs a route that says which forum they are in. The kicker
				 * already said it and was painted in the accent — the exact colour
				 * every link on the tier uses — while being inert. See issue #86.
				 */
				?>
				<p class="bltn-thread__label">
					<a class="bltn-uplink" href="<?php echo esc_url( bbp_get_forum_permalink( $bltn_forum_id ) ); ?>"><?php echo esc_html( $bltn_forum ); ?></a>
				</p>
				<?php // Escaped for the reason the protected heading above gives. ?>
				<h1 class="bltn-thread__title" data-bltn-heading tabindex="-1"><?php echo esc_html( bbp_get_topic_title( $bltn_topic_id ) ); ?></h1>
				<p class="bltn-thread__sub">
					<?php
					/*
					 * In the header, not at the foot where bbPress puts its "closed to
					 * new replies" notice — that notice stands in for a reply form, and
					 * this app has none on any thread, so at the foot it would announce
					 * a restriction that is not one. Here it is what it actually is: a
					 * property of the thread you are about to read (issue #38).
					 */
					?>
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
						// Subscribe to this thread, mirroring forum.php's header treatment so
						// the control sits in the same place on both screens. bbPress owns the
						// toggle itself; before='' drops bbp_get_topic_subscription_link()'s
						// default " | " separator, which the forum link does not carry, so the
						// rendered pill matches the forum screen exactly.
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
						<?php // The only moderator chrome on the screen until it is tapped. ?>
						<?php $bltn_mod->render_toggle(); ?>
					<?php endif; ?>
				</p>

				<?php
				/*
				 * The thread's topic tags, in bbPress's own markup: the anchors are
				 * core's get_the_term_list() output, and only the wrapper is ours
				 * (bbPress's default before/after would print a "Tagged:" paragraph).
				 * They sit with the header because they describe the thread, not the
				 * post — the same reason bbPress prints them in content-single-topic.php
				 * and nowhere in its topic loop, which is also why the thread list
				 * carries none (issue #33).
				 *
				 * The wrapper is a labelled group, and that is what pays for dropping
				 * "Tagged:". A sighted reader is told these are tags by their shape —
				 * a row of small outlined pills under a heading — and a screen reader
				 * is told nothing by shape at all, so without a name the row is three
				 * bare links in the middle of a thread header. `role="group"` names the
				 * set without adding a landmark to enumerate, which is the same trade
				 * the moderation tray makes one element below.
				 *
				 * No guard is needed around this. bbp_get_topic_tag_list() returns its
				 * `none` string — empty — before it builds a wrapper, both when the
				 * topic has no terms and when the site has topic tags switched off, so
				 * there is no empty box to suppress.
				 *
				 * The separator is a space rather than '': the flex gap does the
				 * spacing on screen, but with no separator at all the terms would run
				 * together as one word for anything reading this markup without our
				 * stylesheet. A whitespace-only text node between flex items generates
				 * no anonymous flex item, so the space costs nothing visually.
				 *
				 * Unlike the title above, the term names are not escaped here, and the
				 * asymmetry is deliberate. The title is ours: we take a string and make
				 * an element out of it, so #51's rule applies — text should not be able
				 * to become markup. The terms are not: get_the_term_list() builds the
				 * anchors and interpolates the name itself, exactly as core's own
				 * the_tags() does on every WordPress theme. There is no argument that
				 * would change it, only the `term_links-topic-tag` filter, and taking
				 * that over would mean re-implementing core's markup to escape one
				 * value core chose not to.
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
				// The thread's own actions, under the header they act on. Revealed with
				// the per-reply rows rather than standing open, so a moderator reading a
				// thread sees the thread, not a control panel.
				if ( $bltn_moderates ) {
					$bltn_mod->render_for_topic( $bltn_topic_id );
				}
				?>

				<?php // --- Opening post (the topic itself) --- ?>
				<div class="bltn-post bltn-post--op" id="post-<?php echo esc_attr( (string) $bltn_topic_id ); ?>">
					<div class="bltn-byline">
						<span class="bltn-byline__name"><?php echo esc_html( bbp_get_topic_author_display_name( $bltn_topic_id ) ); ?></span>
						<span class="bltn-byline__time"><?php echo esc_html( bbp_get_topic_post_date( $bltn_topic_id, true ) ); ?></span>
						<?php
						// Before the chip, not after it: the chip is a label and the edit
						// control is an action, so the action takes the trailing slot the
						// flex row opens and the chip follows it at a plain gap.
						$bltn_author_edit->render_for_topic( $bltn_topic_id );
						?>
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
							$bltn_reply_view->render( $bltn_topic_id );
						endwhile;
					endif;
					?>
				</div>

				<?php
				// Inline load-more when the topic runs past one page of replies.
				// The query's own count, not bbPress's: on a threaded forum a page is a
				// slice of a reading order we computed, so WP_Query only ever sees one
				// page of IDs and would report exactly one page (issue #37).
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
				/*
				 * The end of the thread, and what a reader may do with it — the composer,
				 * a closed line, a sign-in control, or nothing (see View\ComposeSlot).
				 *
				 * Below the load-more deliberately. Both controls occupy this slot and
				 * they mean different things: load-more continues the thread you are
				 * reading, the composer ends it. Reading order is what separates them, so
				 * the one that adds to what is above comes first. They separate visually
				 * by fill rather than geometry — load-more keeps the secondary wash, the
				 * composer takes the filled teal — because two identical full-width
				 * buttons stacked read as one control repeated.
				 */
				$bltn_compose->render( $bltn_topic_id, $bltn_forum_id );
				?>

			</article>
		</main>

		<?php
		/*
		 * The bar stays LAST in the document, matching where it sits on the screen.
		 * The design review proposed hoisting it above the scroll region so a
		 * keyboard reader reaches Prev/Next without passing every reply's
		 * permalink. Declined: visual order here is app bar → content → bar, DOM
		 * order already matches it, and inverting that trades WCAG 2.4.3/1.3.2
		 * (meaningful sequence) for tab stops that landmark navigation already
		 * skips — the bar is a labelled <nav> and the scroll region is now <main>,
		 * so both are reachable directly. A footer's controls coming after the
		 * content they act on is the sequence, not a defect in it.
		 */
		$bltn_nav_model = $bltn_navigator->locate( $bltn_topic_id, $bltn_forum_id );
		$bltn_navbar->render( $bltn_nav_model );
		?>

	<?php endif; ?>

</section>
