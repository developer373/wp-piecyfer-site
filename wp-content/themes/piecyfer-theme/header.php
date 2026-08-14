<?php
/**
 * Header template.
 *
 * Everything from `<div id="page">` down to `<div id="main">` is the wrapper the
 * site's CSS is written against, and it is reproduced from the previous theme
 * byte for byte, including the indentation - the pixel harness diffs the DOM,
 * so whitespace inside the body is content.
 *
 * Two things the previous header.php did are deliberately gone:
 *
 *   - the `.limit-wrapper` opener. Its condition
 *     (`VamtamOverrides::limit_wrapper()`) returns false for every request on
 *     this site, because it is false whenever the document is built with
 *     Elementor and false again on 404s. It never appeared inside `#main` on any
 *     of the 39 captured pages. It was also opened here and closed in
 *     footer.php *after* `</div><!-- / #page -->`, i.e. mis-nested.
 *
 *   - `do_action( 'vamtam_body' )`. Nothing hooks it.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">

<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1">
<?php piecyfer_google_tag_manager_head(); ?>
	<?php if ( is_singular() && pings_open() ) : ?>
		<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />
	<?php endif ?>

	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php piecyfer_google_tag_manager_body(); ?>
	<div id="top"></div>
	<?php
		wp_body_open();
	?>

	<?php
		piecyfer_do_location( 'header' );
	?>

	<div id="page" class="main-container">
		<div id="main-content">
			<?php get_template_part( 'template-parts/sub-header' ); ?>

			<?php
				$piecyfer_main_class = 'vamtam-main layout-full';
			?>
			<div id="main" role="main" class="<?php echo esc_attr( $piecyfer_main_class ); ?>">
				<?php do_action( 'piecyfer_inside_main' ); ?>
