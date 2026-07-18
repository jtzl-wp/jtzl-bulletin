<?php
/**
 * Screen: Forums index (home).
 *
 * A flat list of forums. Each row is an ordinary link to the forum's URL — no
 * client-side routing; tapping is a normal navigation.
 *
 * @package Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="bltn-screen">

	<?php
	bltn_app_bar(
		array(
			'title'    => get_bloginfo( 'name' ),
			'subtitle' => __( 'Forums', 'bulletin' ),
		)
	);
	?>

	<div class="bltn-scroll bltn-list" id="bltn-forums">
		<?php if ( bbp_has_forums() ) : ?>

			<?php
			while ( bbp_forums() ) :
				bbp_the_forum();

				$desc      = wp_strip_all_tags( bbp_get_forum_content() );
				$count     = (int) bbp_get_forum_topic_count( 0, true, true );
				$last_id   = bbp_get_forum_last_active_id();
				$author    = bltn_author_name( $last_id );
				$active    = $last_id ? bbp_get_forum_last_active_time() : '';
				?>
				<a class="bltn-row" href="<?php bbp_forum_permalink(); ?>">
					<div class="bltn-row__top">
						<h2 class="bltn-forum__name"><?php bbp_forum_title(); ?></h2>
						<?php // Unread dot is wired in P2; placeholder markup lives in the CSS namespace. ?>
					</div>

					<?php if ( '' !== $desc ) : ?>
						<p class="bltn-row__desc"><?php echo esc_html( wp_trim_words( $desc, 22, '…' ) ); ?></p>
					<?php endif; ?>

					<p class="bltn-row__meta">
						<?php
						/* translators: %s: formatted thread count. */
						echo esc_html( sprintf( _n( '%s thread', '%s threads', $count, 'bulletin' ), number_format_i18n( $count ) ) );
						?>
						<?php if ( '' !== $author ) : ?>
							&middot; <b><?php echo esc_html( $author ); ?></b>
						<?php endif; ?>
						<?php if ( '' !== $active ) : ?>
							&middot; <?php echo esc_html( $active ); ?>
						<?php endif; ?>
					</p>
				</a>
			<?php endwhile; ?>

		<?php else : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No forums yet', 'bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'When forums are added, they will appear here.', 'bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</div>

</section>
