<?php
/**
 * Screen: Forums index (home).
 *
 * A flat list of root forums. Each row is an ordinary link to the forum's URL — no
 * client-side routing; tapping is a normal navigation.
 *
 * The inline continuation exposes forums beyond bbPress's first query page.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jtzl_bltn_container = \JTZL\Bulletin\Plugin::get_container();
$jtzl_bltn_appbar    = $jtzl_bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$jtzl_bltn_ctx       = $jtzl_bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$jtzl_bltn_forums    = $jtzl_bltn_container->get( \JTZL\Bulletin\View\ForumList::class );
$jtzl_bltn_query     = $jtzl_bltn_container->get( \JTZL\Bulletin\Query\ForumQuery::class );
$jtzl_bltn_loadmore  = $jtzl_bltn_container->get( \JTZL\Bulletin\View\LoadMore::class );

/*
 * Root forums, page 1. The page is not read from the URL: /forums/page/2/ is a live
 * URL that WordPress answers 200 for, and serving the tail of the list there would
 * strand a reader on a screen with no way back to its head — the index is home, so
 * it has no back control. Load-more grows page 1 instead, which is also how the
 * thread and reply lists continue.
 */
$jtzl_bltn_rows = $jtzl_bltn_forums->capture( $jtzl_bltn_query->args( 0, 1 ) );
$jtzl_bltn_more = 1 < $jtzl_bltn_ctx->get_max_forum_pages();
?>
<section class="bltn-screen">

	<?php
	$jtzl_bltn_appbar->render(
		array(
			'title'    => get_bloginfo( 'name' ),
			'subtitle' => __( 'Forums', 'jtzl-bulletin' ),
		)
	);
	?>

	<main class="bltn-scroll bltn-list" id="bltn-forums">
		<?php if ( '' !== $jtzl_bltn_rows ) : ?>

			<?php
			/*
			 * Appended rows land inside this element, so it holds the rows alone —
			 * otherwise a later page would arrive below the button that fetched it.
			 */
			?>
			<div id="bltn-forums-list">
				<?php
				// Rows are built by View\ForumRow, which escapes every field.
				echo $jtzl_bltn_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>

			<?php
			if ( $jtzl_bltn_more ) {
				$jtzl_bltn_loadmore->render(
					array(
						'action' => 'bulletin_load_forums',
						'param'  => 'forum',
						'id'     => 0,
						'target' => 'bltn-forums-list',
						'next'   => 2,
						'label'  => __( 'Load more forums', 'jtzl-bulletin' ),
					)
				);
			}
			?>

		<?php else : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No forums yet', 'jtzl-bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'When forums are added, they will appear here.', 'jtzl-bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</main>

</section>
