<?php
/**
 * 404 template.
 *
 * `is_404()` resolves to Elementor's `single` location, and document 8716
 * ("Elementor Error 404") is conditioned on `singular/not_found404`, so
 * `piecyfer_do_location( 'single' )` renders it and the block below never runs.
 * The capture confirms it: the 404 page carries `data-elementor-type="error-404"`.
 *
 * The `single` location has no `overwrite` flag in Elementor Pro's core
 * location table, so - unlike every archive route - this template file really
 * is the one WordPress includes. It is the 404 fallback that is dead, not the
 * file.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

piecyfer_print_pending_styles();

?>
	<?php if ( ! piecyfer_do_location( 'single' ) ) : ?>
		<div class="clearfix">
			<div id="header-404">
				<div class="line-1"><?php echo esc_html_x( '404', 'page not found error', 'piecyfer-theme' ); ?></div>
				<div class="line-2"><?php esc_html_e( 'Page not found', 'piecyfer-theme' ); ?></div>
				<div class="line-3"><?php esc_html_e( 'That page is not here. It may have moved, or the address may be wrong.', 'piecyfer-theme' ); ?></div>
				<div class="line-4"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '&larr; Go to the home page or just search...', 'piecyfer-theme' ); ?></a></div>
			</div>
			<div class="page-404">
				<?php get_search_form(); ?>
			</div>
		</div>
	<?php endif ?>

<?php get_footer(); ?>
