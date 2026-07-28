<?php
/**
 * Screen: search results.
 *
 * The fourth takeover, and the only one that renders more than one kind of thing:
 * bbPress searches forums, topics AND replies at once and interleaves them by date
 * (`bbp_get_post_types()`), which is why its own loop-search.php picks a partial per
 * result. Ours picks a row per result instead — see View\SearchList.
 *
 * What bbPress's screen does that this one does not: it renders the *entire*
 * content of every hit, between a header and a footer row that each repeat
 * "Author | Search Results", under a breadcrumb, with the author's IP for
 * keymasters. That is what earned search the takeover rather than the reskin — no
 * stylesheet takes a wall of full post bodies back out on a phone.
 *
 * One screen, two states, and the field is the constant. With terms it carries them
 * back so they can be edited; without them it is the whole screen, focused, and
 * bbPress's "Please enter some search terms." is left out — an empty focused field
 * under a heading reading "Search" has already said it.
 *
 * The results continue with the same inline load-more the other three screens use,
 * rather than bbPress's numbered pager. Its subject is the terms rather than an id,
 * which is the only thing about this list the control had not already been asked
 * for (see View\LoadMore).
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
 * Populated only when there is something to search for. Query\SearchQuery explains
 * why an empty term never reaches bbPress's query.
 *
 * The screen's shape follows the COUNT, not the markup. View\SearchList drops a row
 * it cannot render — a post type a plugin added to bbPress's search, or a reply with
 * neither words nor a name — while bbPress's own count and page bounds still describe
 * the unfiltered query. Branching on the markup meant a page whose every result was
 * dropped reported "No results" and withheld the continuation, stranding the reader
 * short of pages that do have results (raised by Qodo on #69). So the empty state now
 * means one thing only: the query matched nothing.
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

	<div class="bltn-scroll bltn-list" id="bltn-search">

		<?php
		/*
		 * bbPress's own contract, in our markup: the hidden `action` is what
		 * bbp_search_results_redirect() looks for, and `bbp_search` is the registered
		 * query var. We add no search backend; this form drives bbPress's.
		 *
		 * No action attribute, so a submission posts back to the current URL — which is
		 * the one case worth spelling out, because editing the terms on a results page
		 * means two values are in play. Under pretty permalinks the path still carries
		 * the old terms and the query string carries the new: WordPress lets a $_GET
		 * value win over one the permalink matched, so the redirect lands on the new
		 * search (measured in a browser, not inferred). Under plain permalinks there is
		 * no conflict to resolve at all — a GET form replaces the query string rather
		 * than merging into it, so the old value is simply gone.
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
				// Focused only on the empty screen, which exists to be typed into.
				// On a results screen it would raise the keyboard over the results
				// the reader came to read.
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
			/*
			 * Appended results land inside this element, so it holds the rows alone —
			 * otherwise a later page would arrive below the button that fetched it.
			 */
			?>
			<div id="bltn-search-list">
				<?php
				// Rows are built by View\SearchRow, which escapes every field.
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
	</div>

</section>
