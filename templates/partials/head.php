<?php
/**
 * Shared head markup for Bulletin's takeover and reskin documents.
 *
 * Emit charset first. Buffer `wp_head()` to remove competing viewport tags and
 * guarantee one viewport and one title across classic and block themes.
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
