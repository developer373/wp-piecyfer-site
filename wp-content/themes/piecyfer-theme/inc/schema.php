<?php
/**
 * Structured Data (Schema.org JSON-LD) Generator.
 *
 * Implements high-impact JSON-LD structured data for PieCyfer:
 * - Organization Schema (Company info, Logo, Social Profiles, Contact Points)
 * - WebSite Schema with Sitelinks SearchBox Action
 * - Service & ProfessionalService Schema on Service Landing Pages
 * - BreadcrumbList Schema for Rich Search Snippets
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output JSON-LD schema in the document head.
 *
 * @return void
 */
function piecyfer_output_schema_json_ld() {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$site_url  = home_url( '/' );
	$site_name = get_bloginfo( 'name' );
	$schemas   = array();

	// 1. Organization Schema
	$schemas[] = array(
		'@context'     => 'https://schema.org',
		'@type'        => 'Organization',
		'@id'          => $site_url . '#organization',
		'name'         => 'PieCyfer',
		'legalName'    => 'PieCyfer Software Development Company',
		'url'          => $site_url,
		'logo'         => array(
			'@type'      => 'ImageObject',
			'@id'        => $site_url . '#logo',
			'url'        => $site_url . 'wp-content/uploads/2024/08/cropped-logo-1.png',
			'caption'    => 'PieCyfer Logo',
		),
		'sameAs'       => array(
			'https://www.linkedin.com/company/piecyfer',
			'https://www.facebook.com/piecyfer',
			'https://www.instagram.com/piecyfer/',
			'https://twitter.com/piecyfer',
			'https://clutch.co/profile/piecyfer',
		),
		'contactPoint' => array(
			array(
				'@type'             => 'ContactPoint',
				'contactType'       => 'sales and technical inquiries',
				'email'             => 'info@piecyfer.com',
				'availableLanguage' => array( 'English' ),
			),
		),
	);

	// 2. WebSite Schema (with Sitelinks Searchbox)
	$schemas[] = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'WebSite',
		'@id'             => $site_url . '#website',
		'url'             => $site_url,
		'name'            => $site_name,
		'publisher'       => array(
			'@id' => $site_url . '#organization',
		),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => $site_url . '?s={search_term_string}',
			'query-input' => 'required name=search_term_string',
		),
	);

	// 3. Service / ProfessionalService Schema (on singular landing pages)
	if ( is_singular() && ! is_front_page() ) {
		$post_id    = get_queried_object_id();
		$post_title = get_the_title( $post_id );
		$post_url   = get_permalink( $post_id );
		$meta_desc  = get_post_meta( $post_id, '_aioseo_description', true );

		if ( empty( $meta_desc ) ) {
			$meta_desc = wp_strip_all_tags( get_the_excerpt( $post_id ) );
		}

		if ( is_page() ) {
			$schemas[] = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'Service',
				'@id'         => $post_url . '#service',
				'name'        => $post_title,
				'serviceType' => $post_title,
				'provider'    => array(
					'@id' => $site_url . '#organization',
				),
				'url'         => $post_url,
				'description' => $meta_desc,
				'areaServed'  => array(
					'@type' => 'Country',
					'name'  => 'Worldwide',
				),
			);
		}

		// 4. BreadcrumbList Schema
		$schemas[] = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'@id'             => $post_url . '#breadcrumb',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'Home',
					'item'     => $site_url,
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => $post_title,
					'item'     => $post_url,
				),
			),
		);
	}

	// Output scripts
	foreach ( $schemas as $schema ) {
		echo '<script type="application/ld+json" class="piecyfer-schema-graph">' . "\n";
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";
		echo '</script>' . "\n";
	}
}
add_action( 'wp_head', 'piecyfer_output_schema_json_ld', 5 );
