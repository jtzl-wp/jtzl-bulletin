<?php
/**
 * Renders mixed bbPress forum, topic, and reply search results.
 *
 * The search terms remain the continuation cursor for the shared load-more control.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jtzl_bltn_container = \JTZL\Bulletin\Plugin::get_container();
$jtzl_bltn_appbar    = $jtzl_bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$jtzl_bltn_ctx       = $jtzl_bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$jtzl_bltn_results   = $jtzl_bltn_container->get( \JTZL\Bulletin\View\SearchList::class );
$jtzl_bltn_query     = $jtzl_bltn_container->get( \JTZL\Bulletin\Query\SearchQuery::class );
$jtzl_bltn_loadmore  = $jtzl_bltn_container->get( \JTZL\Bulletin\View\LoadMore::class );

$jtzl_bltn_terms = $jtzl_bltn_ctx->get_search_terms();
$jtzl_bltn_page  = $jtzl_bltn_ctx->get_paged();

/*
 * Base the screen state on the query count. Unsupported result types may produce no
 * markup, but later pages must remain reachable.
 */
$jtzl_bltn_rows  = '';
$jtzl_bltn_found = 0;
$jtzl_bltn_more  = false;

if ( $jtzl_bltn_query->is_runnable( $jtzl_bltn_terms ) ) {
	$jtzl_bltn_rows  = $jtzl_bltn_results->capture( $jtzl_bltn_query->args( $jtzl_bltn_terms, $jtzl_bltn_page ) );
	$jtzl_bltn_found = $jtzl_bltn_ctx->get_search_result_count();
	$jtzl_bltn_more  = $jtzl_bltn_page < $jtzl_bltn_query->max_pages();
	wp_reset_postdata();
}
?>
<section class="bltn-screen">

	<?php
	$jtzl_bltn_appbar->render(
		array(
			'title'      => __( 'Search', 'jtzl-bulletin' ),
			'back_url'   => bbp_get_forums_url(),
			'back_label' => __( 'Back to forums', 'jtzl-bulletin' ),
		)
	);
	?>

	<main class="bltn-scroll bltn-list" id="bltn-search">

		<?php
		/*
		 * The hidden action and `bbp_search` query variable are bbPress's search
		 * contract. Submitting to the current URL lets new query terms replace terms in
		 * either a pretty permalink or a plain query string.
		 */
		?>
		<form class="bltn-search" role="search" method="get">
			<label class="bltn-sr-only" for="bltn-search-field"><?php esc_html_e( 'Search forums', 'jtzl-bulletin' ); ?></label>
			<input type="hidden" name="action" value="bbp-search-request" />
			<input
				class="bltn-search__field"
				type="search"
				id="bltn-search-field"
				name="bbp_search"
				value="<?php echo esc_attr( $jtzl_bltn_terms ); ?>"
				placeholder="<?php esc_attr_e( 'Search forums', 'jtzl-bulletin' ); ?>"
				autocomplete="off"
				<?php
				// Avoid raising the keyboard over an existing results list.
				echo '' === $jtzl_bltn_terms ? ' autofocus' : '';
				?>
			/>
			<button class="bltn-search__go" type="submit" aria-label="<?php esc_attr_e( 'Search', 'jtzl-bulletin' ); ?>">
				<?php
				echo \JTZL\Bulletin\Support\Icons::search(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
				?>
			</button>
		</form>

		<?php if ( $jtzl_bltn_found > 0 ) : ?>

			<p class="bltn-section-label">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: formatted number of search results. */
						_n( '%s result', '%s results', $jtzl_bltn_found, 'jtzl-bulletin' ),
						number_format_i18n( $jtzl_bltn_found )
					)
				);
				?>
			</p>

			<?php
				// Rows are built by View\SearchRow, which escapes every field.
			?>
			<div id="bltn-search-list">
				<?php
				echo $jtzl_bltn_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>

			<?php
			if ( $jtzl_bltn_more ) {
				$jtzl_bltn_loadmore->render(
					array(
						'action' => 'bulletin_load_search',
						'param'  => 'bbp_search',
						'id'     => $jtzl_bltn_terms,
						'target' => 'bltn-search-list',
						'next'   => $jtzl_bltn_page + 1,
						'label'  => __( 'Load more results', 'jtzl-bulletin' ),
					)
				);
			}
			?>

		<?php elseif ( '' !== $jtzl_bltn_terms ) : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No results', 'jtzl-bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'Try fewer or different words.', 'jtzl-bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</main>

</section>
