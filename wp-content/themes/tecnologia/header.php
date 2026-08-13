<?php
/**
 * Header template
 *
 * @package vamtam/tecnologia
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">

<head>
	<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-WDKS9B2G');</script>
<!-- End Google Tag Manager -->
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<?php if ( is_singular() && pings_open() ) : ?>
		<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />
	<?php endif ?>

	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WDKS9B2G"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
	<div id="top"></div>
	<?php
		wp_body_open();
		do_action( 'vamtam_body' );
	?>

	<?php
		if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'header' ) ) {
			get_template_part( 'templates/header' );
		}
	?>

	<div id="page" class="main-container">
		<div id="main-content">
			<?php get_template_part( 'templates/header/sub-header' ); ?>

			<?php
				$classes = 'vamtam-main layout-' . VamtamTemplates::get_layout();
				if ( is_singular( 'give_forms' ) ) {
					$classes .= ' vamtam-give-container';
				}
			?>
			<div id="main" role="main" class="<?php echo esc_attr( $classes ); ?>" >
				<?php do_action( 'vamtam_inside_main' ) ?>

				<?php if ( VamtamTemplates::had_limit_wrapper() ) : ?>
					<div class="limit-wrapper vamtam-box-outer-padding">
				<?php endif; ?>
