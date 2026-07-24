<?php
/**
 * Shared <head> contents for Bulletin's documents (takeover app.php + reskin.php).
 *
 * The explicit <meta charset> is the first thing emitted on purpose: a server
 * without a declared charset makes browsers fall back to Latin-1 and mojibake
 * every dash and glyph (see CLAUDE.md trap #2).
 *
 * wp_head() then prints the <title>, enqueued assets, and core/plugin head output.
 * It also lets the active theme (and core, for block themes) inject a second
 * viewport meta that would clobber ours — GeneratePress and block themes both do
 * this — so we buffer the output and strip any viewport meta, leaving ours (with
 * viewport-fit=cover for safe-area insets) the only one. And we guarantee exactly
 * one <title>: modern themes emit one through wp_head; if the active theme doesn't,
 * we add ours so the tab is labelled.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php
ob_start();
wp_head();
$bltn_head = preg_replace( '#[\t ]*<meta[^>]*name=(["\'])viewport\1[^>]*>\s*#i', '', (string) ob_get_clean() );

if ( false === stripos( (string) $bltn_head, '<title' ) ) {
	$bltn_head = '<title>' . esc_html( wp_get_document_title() ) . '</title>' . "\n" . $bltn_head;
}

echo $bltn_head; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_head() output; only viewport metas stripped, title added.
