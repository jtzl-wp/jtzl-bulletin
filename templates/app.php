<?php
/**
 * Minimal document shell for reading screens.
 *
 * Calls `wp_head()` and `wp_footer()` for core and plugin integration while
 * excluding the theme's chrome and styles. Head markup is shared with the reskin.
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
 * Check search last because its public query variable may coexist with the other
 * screen flags. This preserves the screen the reader navigated to.
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
