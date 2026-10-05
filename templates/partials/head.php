<?php
/**
 * Shared head markup for Bulletin's takeover and reskin documents.
 *
 * Charset comes first and Bulletin's viewport last: a browser applies the last
 * viewport meta it reads, so `viewport-fit=cover` holds even under a theme that
 * hooks its own into `wp_head()`. The two printers known to add one, core's for
 * block themes and GeneratePress's, are unhooked so the usual page has one tag.
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

remove_action( 'wp_head', '_block_template_viewport_meta_tag', 0 );
remove_action( 'wp_head', 'generate_add_viewport', 1 );
?>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<?php
// Without title-tag support core prints no title, and the theme header that would is never loaded here.
if ( ! current_theme_supports( 'title-tag' ) ) :
	?>
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php endif; ?>
<?php wp_head(); ?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
