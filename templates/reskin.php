<?php
/**
 * The reskin (shell-wrap) document.
 *
 * For every reader-reachable bbPress screen we don't take over (profiles, tags,
 * views, search, …) we render bbPress's OWN markup inside Bulletin's chrome rather
 * than the active theme's. bbPress's theme-compat — which, unlike on takeover
 * screens, we deliberately leave in place — has already buffered the screen's
 * content-*.php part into the post and filtered the_content, so the standard loop
 * below prints bbPress's own output. There is no per-screen template to author or
 * keep in parity with bbPress.
 *
 * The active theme's stylesheets are suppressed (Asset\AssetManager) but bbPress's
 * own bbp-default CSS survives, so the content stays legible; the .bbp-* reskin
 * stylesheet layers on top per screen family (issue #32+). This template ships
 * unstyled by us on purpose — it proves the shell-wrap mechanism in isolation
 * (issue #31).
 *
 * @package JTZL\Bulletin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bltn_container = \JTZL\Bulletin\Plugin::get_container();
$bltn_appbar    = $bltn_container->get( \JTZL\Bulletin\View\AppBar::class );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<?php require JTZL_BLTN_DIR . 'templates/partials/head.php'; ?>
</head>
<body <?php body_class( 'bltn bltn-reskin' ); ?>>
<div class="bltn-app">
	<?php
	// Chrome only: a back control to the forums index and the account button. The
	// title stays the site name and is not a heading — bbPress's own content
	// carries the screen's h1 — so we don't compete with it or double up.
	$bltn_appbar->render(
		array(
			'back_url'   => bbp_get_forums_url(),
			'back_label' => __( 'Back to forums', 'jtzl-bulletin' ),
			'heading'    => false,
		)
	);
	?>
	<main class="bltn-reskin__body bltn-scroll">
		<?php
		while ( have_posts() ) {
			the_post();
			the_content();
		}
		?>
	</main>
</div>
<?php wp_footer(); ?>
</body>
</html>
