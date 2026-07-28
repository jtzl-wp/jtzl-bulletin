<?php
/**
 * The document shell.
 *
 * A deliberately minimal HTML document that replaces the theme's on the three
 * reading screens. It still calls wp_head()/wp_footer() so WordPress core, the
 * admin bar, and other plugins keep working — the theme's *chrome and styles*
 * are what we leave out (styles are dequeued in Asset\AssetManager).
 *
 * The document <head> — charset, viewport, and the buffered wp_head() — is shared
 * with the reskin document; see templates/partials/head.php.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<?php require JTZL_BLTN_DIR . 'templates/partials/head.php'; ?>
</head>
<body <?php body_class( 'bltn' ); ?>>
<div class="bltn-app">
<?php
/*
 * Search is asked last, and deliberately. bbPress registers `bbp_search` as a
 * public query var, so any of the three screens above can be requested with one
 * riding along in the URL — and on such a request `bbp_is_search()` is true
 * alongside them. Asking it last means the screen the reader actually navigated to
 * is the screen they get; asking it first would have a stray query string replace
 * a thread with a search.
 */
if ( bbp_is_forum_archive() ) {
	require JTZL_BLTN_DIR . 'templates/screens/forums.php';
} elseif ( bbp_is_single_forum() ) {
	require JTZL_BLTN_DIR . 'templates/screens/forum.php';
} elseif ( bbp_is_single_topic() ) {
	require JTZL_BLTN_DIR . 'templates/screens/topic.php';
} elseif ( bbp_is_search() ) {
	require JTZL_BLTN_DIR . 'templates/screens/search.php';
}
?>
</div>
<?php wp_footer(); ?>
</body>
</html>
