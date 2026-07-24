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
if ( bbp_is_forum_archive() ) {
	require JTZL_BLTN_DIR . 'templates/screens/forums.php';
} elseif ( bbp_is_single_forum() ) {
	require JTZL_BLTN_DIR . 'templates/screens/forum.php';
} elseif ( bbp_is_single_topic() ) {
	require JTZL_BLTN_DIR . 'templates/screens/topic.php';
}
?>
</div>
<?php wp_footer(); ?>
</body>
</html>
