<?php
/**
 * Screen: Forums index (home).
 *
 * A flat list of forums. Each row is an ordinary link to the forum's URL — no
 * client-side routing; tapping is a normal navigation.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bltn_container = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar    = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$bltn_ctx       = $bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
?>
<section class="bltn-screen">

	<?php
	$bltn_appbar->render(
		array(
			'title'    => get_bloginfo( 'name' ),
			'subtitle' => __( 'Forums', 'jtzl-bulletin' ),
		)
	);
	?>

	<div class="bltn-scroll bltn-list" id="bltn-forums">
		<?php if ( bbp_has_forums() ) : ?>

			<?php
			while ( bbp_forums() ) :
				bbp_the_forum();

				$bltn_desc    = wp_strip_all_tags( bbp_get_forum_content() );
				$bltn_count   = (int) bbp_get_forum_topic_count( 0, true, true );
				$bltn_last_id = (int) bbp_get_forum_last_active_id();
				$bltn_author  = $bltn_ctx->get_author_name( $bltn_last_id );
				$bltn_active  = $bltn_last_id ? bbp_get_forum_last_active_time() : '';
				?>
				<a class="bltn-row" href="<?php bbp_forum_permalink(); ?>">
					<div class="bltn-row__top">
						<h2 class="bltn-forum__name"><?php bbp_forum_title(); ?></h2>
						<?php // Unread dot is wired in P2; placeholder markup lives in the CSS namespace. ?>
					</div>

					<?php if ( '' !== $bltn_desc ) : ?>
						<p class="bltn-row__desc"><?php echo esc_html( wp_trim_words( $bltn_desc, 22, '…' ) ); ?></p>
					<?php endif; ?>

					<p class="bltn-row__meta">
						<?php
						/* translators: %s: formatted thread count. */
						echo esc_html( sprintf( _n( '%s thread', '%s threads', $bltn_count, 'jtzl-bulletin' ), number_format_i18n( $bltn_count ) ) );
						?>
						<?php if ( '' !== $bltn_author ) : ?>
							&middot; <b><?php echo esc_html( $bltn_author ); ?></b>
						<?php endif; ?>
						<?php if ( '' !== $bltn_active ) : ?>
							&middot; <?php echo esc_html( $bltn_active ); ?>
						<?php endif; ?>
					</p>
				</a>
			<?php endwhile; ?>

		<?php else : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No forums yet', 'jtzl-bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'When forums are added, they will appear here.', 'jtzl-bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</div>

</section>
