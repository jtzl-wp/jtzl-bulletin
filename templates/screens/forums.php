<?php
/**
 * Screen: Forums index (home).
 *
 * A flat list of root forums. Each row is an ordinary link to the forum's URL — no
 * client-side routing; tapping is a normal navigation.
 *
 * The list pages beyond the first `_bbp_forums_per_page` (50) through an inline
 * "load more forums", because bbPress caps a forum list at that number and ships no
 * pagination for one — so without this, forum 51 and everything after it is
 * unreachable (issue #38; see Query\ForumQuery for the upstream detail).
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bltn_container = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar    = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$bltn_ctx       = $bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$bltn_forums    = $bltn_container->get( \JTZL\Bulletin\View\ForumList::class );
$bltn_query     = $bltn_container->get( \JTZL\Bulletin\Query\ForumQuery::class );
$bltn_loadmore  = $bltn_container->get( \JTZL\Bulletin\View\LoadMore::class );

/*
 * Root forums, page 1. The page is not read from the URL: /forums/page/2/ is a live
 * URL that WordPress answers 200 for, and serving the tail of the list there would
 * strand a reader on a screen with no way back to its head — the index is home, so
 * it has no back control. Load-more grows page 1 instead, which is also how the
 * thread and reply lists continue.
 */
$bltn_rows = $bltn_forums->capture( $bltn_query->args( 0, 1 ) );
$bltn_more = 1 < $bltn_ctx->get_max_forum_pages();
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

	<main class="bltn-scroll bltn-list" id="bltn-forums">
		<?php if ( '' !== $bltn_rows ) : ?>

			<?php
			/*
			 * Appended rows land inside this element, so it holds the rows alone —
			 * otherwise a later page would arrive below the button that fetched it.
			 */
			?>
			<div id="bltn-forums-list">
				<?php
				// Rows are built by View\ForumRow, which escapes every field.
				echo $bltn_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>

			<?php
			if ( $bltn_more ) {
				$bltn_loadmore->render(
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
