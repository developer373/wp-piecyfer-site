<?php
/**
 * Search results template.
 *
 * Theme Builder document 8711 ("Search Results") is conditioned on
 * `archive/search`, and search is an archive route, so Elementor's `overwrite`
 * takeover renders 8711 and this file does not execute. Verified against
 * `_project/snapshots/ref2-a/html/s-software.html`, which carries
 * `data-elementor-type="search-results"` and `data-elementor-id="8711"`.
 *
 * Search is also the one route on the site that still shows the theme's
 * `#sub-header`, because document 8711 - unlike the two archive documents -
 * contains no title widget. That comes from `template-parts/sub-header.php` via
 * `get_header()`, not from here.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<?php if ( ! piecyfer_do_location( 'archive' ) ) : ?>

	<div class="page-wrapper">
		<?php if ( have_posts() ) : ?>

			<article class="full">
				<div class="page-content clearfix">
					<?php
					rewind_posts();

					get_template_part( 'template-parts/loop' );
					?>
				</div>
			</article>

		<?php else : ?>

			<article id="piecyfer-no-search-results">
				<h3><?php esc_html_e( 'Sorry, nothing found', 'piecyfer-theme' ); ?></h3>

				<div><?php esc_html_e( 'Maybe you should check your spelling...', 'piecyfer-theme' ); ?></div>

				<div class="page-404">
					<?php get_search_form(); ?>
				</div>
			</article>

		<?php endif ?>
	</div>

<?php endif ?>

<?php get_footer(); ?>
