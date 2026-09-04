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

$bltn_container = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar    = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
$bltn_ctx       = $bltn_container->get( \JTZL\Bulletin\WordPress\ContextInterface::class );
$bltn_results   = $bltn_container->get( \JTZL\Bulletin\View\SearchList::class );
$bltn_query     = $bltn_container->get( \JTZL\Bulletin\Query\SearchQuery::class );
$bltn_loadmore  = $bltn_container->get( \JTZL\Bulletin\View\LoadMore::class );

$bltn_terms = $bltn_ctx->get_search_terms();
$bltn_page  = $bltn_ctx->get_paged();

/*
 * Base the screen state on the query count. Unsupported result types may produce no
 * markup, but later pages must remain reachable.
 */
$bltn_rows  = '';
$bltn_found = 0;
$bltn_more  = false;

if ( $bltn_query->is_runnable( $bltn_terms ) ) {
	$bltn_rows  = $bltn_results->capture( $bltn_query->args( $bltn_terms, $bltn_page ) );
	$bltn_found = $bltn_ctx->get_search_result_count();
	$bltn_more  = $bltn_page < $bltn_query->max_pages();
	wp_reset_postdata();
}
?>
<section class="bltn-screen">

	<?php
	$bltn_appbar->render(
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
				value="<?php echo esc_attr( $bltn_terms ); ?>"
				placeholder="<?php esc_attr_e( 'Search forums', 'jtzl-bulletin' ); ?>"
				autocomplete="off"
				<?php
				// Avoid raising the keyboard over an existing results list.
				echo '' === $bltn_terms ? ' autofocus' : '';
				?>
			/>
			<button class="bltn-search__go" type="submit" aria-label="<?php esc_attr_e( 'Search', 'jtzl-bulletin' ); ?>">
				<?php
				echo \JTZL\Bulletin\Support\Icons::search(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
				?>
			</button>
		</form>

		<?php if ( $bltn_found > 0 ) : ?>

			<p class="bltn-section-label">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: formatted number of search results. */
						_n( '%s result', '%s results', $bltn_found, 'jtzl-bulletin' ),
						number_format_i18n( $bltn_found )
					)
				);
				?>
			</p>

			<?php
				// Rows are built by View\SearchRow, which escapes every field.
			?>
			<div id="bltn-search-list">
				<?php
				echo $bltn_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>

			<?php
			if ( $bltn_more ) {
				$bltn_loadmore->render(
					array(
						'action' => 'bulletin_load_search',
						'param'  => 'bbp_search',
						'id'     => $bltn_terms,
						'target' => 'bltn-search-list',
						'next'   => $bltn_page + 1,
						'label'  => __( 'Load more results', 'jtzl-bulletin' ),
					)
				);
			}
			?>

		<?php elseif ( '' !== $bltn_terms ) : ?>

			<div class="bltn-empty">
				<p class="bltn-empty__title"><?php esc_html_e( 'No results', 'jtzl-bulletin' ); ?></p>
				<p class="bltn-empty__body"><?php esc_html_e( 'Try fewer or different words.', 'jtzl-bulletin' ); ?></p>
			</div>

		<?php endif; ?>
	</main>

</section>
