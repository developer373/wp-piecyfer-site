<!-- Elementor `page-title` location -->
<?php
/**
 * The `page-title-location` slot, and the theme's own fallback title.
 *
 * The comment above is not decoration - it is in the captured markup of every
 * page that renders a sub-header, so it stays.
 *
 * The fallback below fires only when no Theme Builder document is assigned to
 * `page-title-location` AND the current document has not set Elementor's
 * "Hide Title" page setting. On this site every page sets `hide_title = yes`,
 * so the only route that reaches it is search - where it renders
 * `get_the_title()` of the *first search result*, because Elementor Pro takes
 * over search.php through `template_include` and the theme never gets to set a
 * page title. That is a pre-existing bug and it is reproduced here on purpose:
 * fixing it changes a visible `<h1>` on the search page, which is a separate,
 * separately-verified change.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

$piecyfer_document = class_exists( '\Elementor\Plugin' )
	? \Elementor\Plugin::instance()->documents->get( get_the_ID() )
	: false;

$piecyfer_hide_title = $piecyfer_document && 'yes' === $piecyfer_document->get_settings( 'hide_title' );

if ( piecyfer_do_location( 'page-title-location' ) || $piecyfer_hide_title ) {
	return;
}

$piecyfer_title = get_the_title();

if ( '' === $piecyfer_title ) {
	$piecyfer_title = esc_html__( 'Untitled', 'piecyfer-theme' );
}

$piecyfer_description = is_archive() ? get_the_archive_description() : '';

?>
	<div class="limit-wrapper vamtam-box-outer-padding">
		<div class="meta-header-inside">
			<header class="page-header" data-progressive-animation="page-title">
									<h1><?php echo wp_kses_post( $piecyfer_title ); ?></h1>
				
				<?php if ( ! empty( $piecyfer_description ) ) : ?>
					<div class="page-header-line"></div>

					<div class="desc">
						<?php echo wp_kses_post( $piecyfer_description ); ?>
					</div>
				<?php endif ?>
			</header>
			</div>
	</div>
