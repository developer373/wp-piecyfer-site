<?php
/**
 * The `#sub-header` shell.
 *
 * Renders an empty title container on most pages and the page title itself on
 * the few where no Elementor template supplies one.
 *
 * The whole part is skipped when a Theme Builder document for the header,
 * page-title, archive or single location already contains a title widget, which
 * is why single posts, the category archives, the author archive and the 404
 * have no `#sub-header` at all, while ordinary pages and the search results do.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

if ( is_404() ) {
	return;
}

if ( piecyfer_title_present_for_post() ) {
	return;
}

?>

<div id="sub-header" class="layout-full elementor-page-title">
	<div class="meta-header">
		<?php do_action( 'piecyfer_meta_header_bg' ); ?>

		<?php get_template_part( 'template-parts/page-title' ); ?>
	</div>
</div>
