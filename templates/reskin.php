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
$bltn_heading   = $bltn_container->get( \JTZL\Bulletin\View\ScreenHeading::class );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<?php require JTZL_BLTN_DIR . 'templates/partials/head.php'; ?>
</head>
<body <?php body_class( 'bltn bltn-reskin' ); ?>>
<div class="bltn-app">
	<?php
	/*
	 * Chrome only: a back control and the account button. The title stays the site
	 * name and is not a heading — the screen's h1 is rendered below, in the content
	 * region, exactly as the four takeover templates do it.
	 *
	 * ⚠ **The destination used to be the forums index for every screen in this tier,
	 * and on an edit form that was the whole defect** (Yoren, 2026-08-16). It was a
	 * fair default when the tier held archives and profiles, whose parent really is
	 * the index; topic and reply edit joined in 0.5.0 and inherited it, so a member
	 * who opened Edit forty replies deep and changed their mind was dropped at the top
	 * of the site. Chrome\EditExit answers per screen now and still says "the forums
	 * index" for everything that is not an edit form.
	 */
	$bltn_exit = $bltn_container->get( \JTZL\Bulletin\Chrome\EditExit::class )->destination();
	$bltn_appbar->render(
		array(
			'back_url'   => $bltn_exit['url'],
			'back_label' => $bltn_exit['label'],
			'heading'    => false,
		)
	);
	?>
	<main class="bltn-reskin__body bltn-scroll">
		<?php
		/*
		 * The screen's heading, announced and not shown. This template used to leave it
		 * out on the grounds that bbPress's own content carries an h1 — measured across
		 * eight reskin routes, not one of them does (see View\ScreenHeading). Before the
		 * loop deliberately: it is the document's first heading, and CLAUDE.md trap #5
		 * is a `the_title` re-entrancy hang reachable from inside a running loop.
		 */
		$bltn_heading->render();

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
