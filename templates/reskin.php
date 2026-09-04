<?php
/**
 * Renders bbPress theme-compat output inside Bulletin's shell.
 *
 * The screen markup remains owned by bbPress; Bulletin suppresses theme styles and layers its
 * reskin styles over bbPress defaults.
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
	 * The content region owns the screen heading. Edit screens return to the edited
	 * object; other reskin screens return to the forums index.
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
		 * Render the accessible heading before the loop. Calling the title seam inside
		 * a running loop can re-enter `the_title`.
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
