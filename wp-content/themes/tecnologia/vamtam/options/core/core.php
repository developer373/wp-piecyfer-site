<?php

/**
 * Controls attached to core sections
 *
 * @package vamtam/tecnologia
 */


return array(
	array(
		'label'     => esc_html__( 'Header Logo Type', 'tecnologia' ),
		'id'        => 'header-logo-type',
		'type'      => 'switch',
		'transport' => 'postMessage',
		'section'   => 'title_tagline',
		'choices'   => array(
			'image'      => esc_html__( 'Image', 'tecnologia' ),
			'site-title' => esc_html__( 'Site Title', 'tecnologia' ),
		),
		'priority' => 8,
	),

	array(
		'label'     => esc_html__( 'Single Product Image Zoom', 'tecnologia' ),
		'id'        => 'wc-product-gallery-zoom',
		'type'      => 'switch',
		'transport' => 'postMessage',
		'section'   => 'woocommerce_product_images',
		'choices'   => array(
			'enabled'  => esc_html__( 'Enabled', 'tecnologia' ),
			'disabled' => esc_html__( 'Disabled', 'tecnologia' ),
		),
		// 'active_callback' => 'vamtam_extra_features',
	),
);


