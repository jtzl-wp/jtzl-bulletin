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
$bltn_forumrow  = $bltn_container->get( \JTZL\Bulletin\View\ForumRow::class );
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
				$bltn_last_id = (int) bbp_get_forum_last_active_id();

				$bltn_forumrow->render(
					array(
						'permalink'   => bbp_get_forum_permalink(),
						'title'       => bbp_get_forum_title(),
						'description' => '' !== $bltn_desc ? wp_trim_words( $bltn_desc, 22, '…' ) : '',
						'topics'      => (int) bbp_get_forum_topic_count( 0, true, true ),
						'author'      => $bltn_ctx->get_author_name( $bltn_last_id ),
						'active'      => $bltn_last_id ? bbp_get_forum_last_active_time() : '',
					)
				);
			endwhile;
			?>

		<?php else : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No forums yet', 'jtzl-bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'When forums are added, they will appear here.', 'jtzl-bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</div>

</section>
