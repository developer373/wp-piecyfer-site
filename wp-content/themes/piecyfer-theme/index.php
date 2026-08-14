<?php
/**
 * Catch-all template.
 *
 * WordPress requires this file to exist for the directory to count as a theme;
 * on this site nothing routes to it. The posts index is a normal Elementor page
 * (id 93, "Blogs") reached at `/blogs/` through `page.php`, `show_on_front` is
 * a page, and every remaining archive route is claimed either by a Theme
 * Builder archive document or by Elementor's `overwrite` takeover.
 *
 * It is a real template rather than a stub so that a mis-routed request renders
 * a usable page instead of a blank one.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<?php if ( ! piecyfer_do_location( 'archive' ) ) : ?>

	<div class="page-wrapper">

		<article class="full">
			<div class="page-content clearfix">
				<?php get_template_part( 'template-parts/loop' ); ?>
			</div>
		</article>

	</div>

<?php endif ?>

<?php get_footer(); ?>
