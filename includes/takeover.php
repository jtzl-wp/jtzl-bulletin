<?php
/**
 * Theme takeover.
 *
 * On the three reading screens we render our own minimal document instead of the
 * active theme. We do this through bbPress's own `bbp_template_include` filter,
 * which sits on WordPress core's `template_include` — so wp_head()/wp_footer()
 * still fire and core + other plugins keep working.
 *
 * We render the bbPress loops directly (see templates/screens/*.php) rather than
 * overriding template parts through the template stack. A full-document takeover
 * owns its own output end to end, so there is nothing for the stack to resolve.
 *
 * @package Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The screens Bulletin takes over. Everything else — non-bbPress pages, and the
 * bbPress pages we have no v1 design for (profiles, topic tags, edit forms,
 * search) — is left on the active theme.
 *
 * @return bool
 */
function bltn_is_reading_screen() {
	return is_bbpress() && (
		bbp_is_forum_archive()    // Forums index — home.
		|| bbp_is_single_forum()  // One forum's threads.
		|| bbp_is_single_topic()  // Reading view.
	);
}

/**
 * Send single-reply permalinks into the reading view.
 *
 * A raw /reply/{slug}/ URL isn't one of our three screens, so without this it
 * would render in the site's theme — a jarring seam, and reachable from old
 * links and bbPress email notifications. bbp_get_reply_url() resolves to the
 * parent topic with the correct page and #post-N anchor (not the reply
 * permalink), so there's no redirect loop, and our JS resolves the anchor even
 * when it lives past page 1.
 */
function bltn_redirect_single_reply() {
	if ( ! is_bbpress() || ! bbp_is_single_reply() ) {
		return;
	}
	$reply_id = bbp_get_reply_id();
	$url      = $reply_id ? bbp_get_reply_url( $reply_id ) : '';
	if ( ! empty( $url ) ) {
		wp_safe_redirect( $url, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'bltn_redirect_single_reply', 9 );

/**
 * Prime the takeover before the template is chosen.
 *
 * bbPress attaches its theme-compat wrapper to `bbp_template_include` at priority
 * 4, which fires before our priority-20 override — so a remove_filter() inside
 * our override would be too late. We strip it here, on template_redirect, which
 * runs before the template_include chain. This keeps theme-compat from setting up
 * its the_content injection on screens we render ourselves. (bbPress's own code
 * sanctions this — see includes/core/filters.php:104-106 in the clone.)
 */
function bltn_prime_takeover() {
	if ( ! bltn_is_reading_screen() ) {
		return;
	}

	remove_filter( 'bbp_template_include', 'bbp_template_include_theme_compat', 4 );

	// Title and duplicate-viewport handling both live in templates/app.php, where
	// we post-process the buffered wp_head() output: strip extra viewport metas,
	// and inject a <title> only if the theme didn't already emit one. (We avoid
	// add_theme_support('title-tag') here — on template_redirect that's after
	// wp_loaded and WordPress logs an "called incorrectly" notice.)
}
add_action( 'template_redirect', 'bltn_prime_takeover' );

/**
 * Swap in our own document on the reading screens.
 *
 * Runs at priority 20 — after bbPress theme-compat (4) and theme-supports (2) —
 * so whatever template they resolved, ours wins.
 *
 * @param string $template Template path WordPress/bbPress resolved.
 * @return string
 */
function bltn_template_include( $template ) {
	if ( ! bltn_is_reading_screen() ) {
		return $template;
	}

	return BLTN_DIR . 'templates/app.php';
}
add_filter( 'bbp_template_include', 'bltn_template_include', 20 );
