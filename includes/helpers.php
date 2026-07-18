<?php
/**
 * Shared render helpers.
 *
 * @package Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL for the account button.
 *
 * v1 forums are public-read, so there is no account screen: logged-out users go
 * to wp-login.php; logged-in users go to their bbPress profile. Both land on the
 * theme (we don't take those pages over), which is an accepted v1 seam.
 *
 * @return string
 */
function bltn_account_url() {
	if ( is_user_logged_in() ) {
		$profile = bbp_get_user_profile_url( get_current_user_id() );
		if ( ! empty( $profile ) ) {
			return $profile;
		}
	}
	return wp_login_url( bbp_get_forums_url() );
}

/**
 * Accessible label for the account button, reflecting auth state.
 *
 * @return string
 */
function bltn_account_label() {
	return is_user_logged_in()
		? __( 'Your account', 'bulletin' )
		: __( 'Sign in', 'bulletin' );
}

/**
 * The minimal top strip that replaces the theme header.
 *
 * @param array $args {
 *     @type string $title    Main heading text (already-unescaped plain text).
 *     @type string $subtitle Small line under the title (e.g. "Forum").
 *     @type string $back_url Optional URL for the leading back control. Omit for the home screen.
 *     @type string $back_label Accessible label for the back control.
 *     @type bool   $heading  Whether the title is the screen's focus heading (h1). Default true.
 * }
 */
function bltn_app_bar( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'      => get_bloginfo( 'name' ),
			'subtitle'   => '',
			'back_url'   => '',
			'back_label' => __( 'Back', 'bulletin' ),
			'heading'    => true,
		)
	);

	echo '<header class="bltn-appbar">';

	// Leading: back control, or a spacer to keep the title centered.
	if ( ! empty( $args['back_url'] ) ) {
		printf(
			'<a class="bltn-iconbtn bltn-iconbtn--back" href="%s" aria-label="%s">%s</a>',
			esc_url( $args['back_url'] ),
			esc_attr( $args['back_label'] ),
			bltn_icon_chevron_left() // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
		);
	} else {
		echo '<span class="bltn-appbar__spacer" aria-hidden="true"></span>';
	}

	// Title (+ optional subtitle). The title doubles as the screen's focus target.
	$tag        = $args['heading'] ? 'h1' : 'span';
	$focus_attr = $args['heading'] ? ' data-bltn-heading tabindex="-1"' : '';
	printf( '<%1$s class="bltn-appbar__title"%2$s>%3$s', $tag, $focus_attr, esc_html( $args['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- tag + attr are internal literals.
	if ( '' !== $args['subtitle'] ) {
		printf( '<small>%s</small>', esc_html( $args['subtitle'] ) );
	}
	printf( '</%s>', $tag ); // phpcs:ignore WordPress.Security.EscapeOutput -- internal literal.

	// Trailing: account.
	printf(
		'<a class="bltn-iconbtn" href="%s" aria-label="%s">%s</a>',
		esc_url( bltn_account_url() ),
		esc_attr( bltn_account_label() ),
		bltn_icon_account() // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
	);

	echo '</header>';
}

/**
 * Plain display name of a post's author.
 *
 * bbPress's freshness "last active id" can point at a topic or a reply, so we
 * resolve the author through bbp_get_author_link() (which handles both) and then
 * strip its profile link — we deliberately don't send readers into the themed
 * profile pages from the reading UI.
 *
 * @param int $post_id Topic or reply ID.
 * @return string Plain-text name, or '' if none.
 */
function bltn_author_name( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return '';
	}
	$link = bbp_get_author_link(
		array(
			'post_id'   => $post_id,
			'type'      => 'name',
			'show_role' => false,
		)
	);
	return trim( wp_strip_all_tags( $link ) );
}

/**
 * Render one thread row (used for both the pinned and the all-threads sections).
 *
 * @param array $row {
 *     @type string $permalink Topic URL.
 *     @type string $title     Topic title.
 *     @type string $author    Plain author name.
 *     @type string $active    Human "last active" time.
 *     @type int    $replies   Reply count.
 * }
 */
function bltn_render_thread_row( $row ) {
	?>
	<a class="bltn-row" href="<?php echo esc_url( $row['permalink'] ); ?>">
		<h2 class="bltn-row__title"><?php echo esc_html( $row['title'] ); ?></h2>
		<p class="bltn-row__meta">
			<?php if ( '' !== $row['author'] ) : ?>
				<b><?php echo esc_html( $row['author'] ); ?></b> &middot;
			<?php endif; ?>
			<?php if ( '' !== $row['active'] ) : ?>
				<?php echo esc_html( $row['active'] ); ?> &middot;
			<?php endif; ?>
			<?php
			/* translators: %s: formatted reply count. */
			echo esc_html( sprintf( _n( '%s reply', '%s replies', $row['replies'], 'bulletin' ), number_format_i18n( $row['replies'] ) ) );
			?>
		</p>
	</a>
	<?php
}

/**
 * Canonical reply-query args for the reading view.
 *
 * Used by BOTH the initial page render and the load-more AJAX handler so the two
 * paths paginate identically. We pass an explicit reply-only post type rather
 * than leaning on bbp_show_lead_topic(): that helper only excludes the topic
 * when bbp_is_single_topic() is also true, which is false in an AJAX request —
 * so relying on it pulls the topic post into the replies loop off-page and
 * shifts every page boundary by one. Explicit args can't drift between contexts.
 *
 * @param int $topic_id Topic to load replies for.
 * @param int $page     1-based page number.
 * @return array bbp_has_replies() args.
 */
function bltn_reply_query_args( $topic_id, $page ) {
	return array(
		'post_parent'    => (int) $topic_id,
		'post_type'      => bbp_get_reply_post_type(),
		'posts_per_page' => bbp_get_replies_per_page(),
		'paged'          => max( 1, (int) $page ),
		// Stable order. Ordering by date ALONE is unstable when replies share a
		// timestamp (imports with coarse dates, or two posts in the same second):
		// MySQL returns tied rows in an undefined order, so LIMIT/OFFSET paging
		// shuffles rows across page boundaries — duplicating some replies and
		// dropping others. The ID tiebreak makes paging deterministic.
		'orderby'        => array(
			'date' => 'ASC',
			'ID'   => 'ASC',
		),
	);
}

/**
 * Render the current reply in the replies loop as a Bulletin post.
 *
 * Shared by the reading view and the load-more AJAX handler so the markup is
 * byte-identical whether a reply arrives with the page or is appended later.
 * The id="post-{reply_id}" anchor matches bbp_get_reply_url(), which is what
 * makes deep-links to a specific reply resolvable.
 */
function bltn_render_reply() {
	$reply_id = bbp_get_reply_id();
	?>
	<article class="bltn-post" id="post-<?php echo esc_attr( $reply_id ); ?>">
		<div class="bltn-byline">
			<span class="bltn-byline__name"><?php echo esc_html( bbp_get_reply_author_display_name( $reply_id ) ); ?></span>
			<span class="bltn-byline__time"><?php echo esc_html( bbp_get_reply_post_date( $reply_id, true ) ); ?></span>
		</div>
		<div class="bltn-post__body"><?php bbp_reply_content( $reply_id ); ?></div>
	</article>
	<?php
}

/**
 * Account glyph. Uses currentColor (which resolves fine in SVG presentation
 * attributes) — never a var() stroke, which iOS Safari silently drops.
 *
 * @return string
 */
function bltn_icon_account() {
	return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.4"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>';
}

/**
 * Back chevron.
 *
 * @return string
 */
function bltn_icon_chevron_left() {
	return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
}
