<?php
/**
 * The document shell.
 *
 * A deliberately minimal HTML document that replaces the theme's on the three
 * reading screens. It still calls wp_head()/wp_footer() so WordPress core, the
 * admin bar, and other plugins keep working — the theme's *chrome and styles*
 * are what we leave out (styles are dequeued in Asset\AssetManager).
 *
 * The explicit <meta charset> is line 1 of the document on purpose: a server
 * without a declared charset makes browsers fall back to Latin-1 and mojibake
 * every dash and glyph. (See CLAUDE.md — this bit us once already.)
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
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php
// wp_head() prints the <title>, enqueued assets, and core/plugin head output.
// It also lets the active theme (and core, for block themes) inject a second
// viewport meta that would clobber ours — GeneratePress and block themes both do this.
// Buffer the output and strip any viewport meta so ours, with viewport-fit=cover
// for safe-area insets, stays the only one.
ob_start();
wp_head();
$bltn_head = preg_replace( '#[\t ]*<meta[^>]*name=(["\'])viewport\1[^>]*>\s*#i', '', (string) ob_get_clean() );

// Guarantee exactly one <title>: modern themes (block themes, GeneratePress) emit one
// through wp_head; if the active theme doesn't, add ours so the tab is labelled.
if ( false === stripos( (string) $bltn_head, '<title' ) ) {
	$bltn_head = '<title>' . esc_html( wp_get_document_title() ) . '</title>' . "\n" . $bltn_head;
}

echo $bltn_head; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_head() output; only viewport metas stripped, title added.
?>
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
