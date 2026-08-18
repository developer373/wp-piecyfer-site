<?php
/**
 * Technical SEO & Redirection Engine.
 *
 * Implements:
 * - High-performance dynamic robots.txt generation
 * - 301 Permanent Redirect mapping for legacy / obsolete URLs
 * - Clean HTTP headers for bots
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filter robots.txt to ensure optimal crawler directives and sitemap discovery.
 *
 * @param string $output Default robots.txt content.
 * @param bool   $public Whether the site is considered public.
 * @return string
 */
function piecyfer_custom_robots_txt( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}

	$site_url = home_url( '/' );

	$rules = array(
		'User-agent: *',
		'Disallow: /wp-admin/',
		'Allow: /wp-admin/admin-ajax.php',
		'Disallow: /wp-login.php',
		'Disallow: /*?s=*',
		'Disallow: /search/',
		'Disallow: /feed/',
		'Disallow: /trackback/',
		'Allow: /wp-content/uploads/',
		'Allow: /wp-content/plugins/',
		'Allow: /wp-content/themes/',
		'',
		'# Sitemaps',
		'Sitemap: ' . esc_url( $site_url . 'wp-sitemap.xml' ),
	);

	return implode( "\n", $rules ) . "\n";
}
add_filter( 'robots_txt', 'piecyfer_custom_robots_txt', 99, 2 );

/**
 * Handle legacy URL pattern redirects (301 Permanent Redirects).
 *
 * Automatically captures legacy paths (like /services/web-app-development)
 * and permanently redirects them to active routes (/web-app-development/).
 *
 * @return void
 */
function piecyfer_legacy_301_redirects() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$path        = trim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

	if ( empty( $path ) ) {
		return;
	}

	// 1. Service route aliases (/services/slug -> /slug/)
	if ( preg_match( '#^services/(.+)$#i', $path, $matches ) ) {
		$target_slug = $matches[1];
		$target_page = get_page_by_path( $target_slug );

		if ( $target_page && 'publish' === $target_page->post_status ) {
			wp_safe_redirect( get_permalink( $target_page->ID ), 301 );
			exit;
		}
	}

	// 2. Direct legacy URL mappings
	$redirect_map = array(
		'service'                           => home_url( '/#services' ),
		'services'                          => home_url( '/#services' ),
		'hire-developers'                   => home_url( '/hire-an-expert/' ),
		'hire-dedicated-developers'         => home_url( '/hire-an-expert/' ),
		'staff-augmentation'                => home_url( '/hire-an-expert/' ),
		'dedicated-developers'              => home_url( '/hire-an-expert/' ),
		'custom-software-development'       => home_url( '/enterprise-software-development/' ),
		'qa-and-testing'                    => home_url( '/software-quality-testing/' ),
		'qa-testing'                        => home_url( '/software-quality-testing/' ),
		'quality-assurance'                 => home_url( '/software-quality-testing/' ),
		'software-quality-assurance'        => home_url( '/software-quality-testing/' ),
		'maintenance-and-support'           => home_url( '/software-maintenance-and-support/' ),
		'maintenance'                       => home_url( '/software-maintenance-and-support/' ),
		'software-maintenance'              => home_url( '/software-maintenance-and-support/' ),
		'software-re-engineering-services'  => home_url( '/software-re-engineering/' ),
		'data-migration-services'           => home_url( '/software-migration/' ),
		'data-migration'                    => home_url( '/software-migration/' ),
		'digital-marketing-services'        => home_url( '/digital-marketing/' ),
		'crm-development'                   => home_url( '/crm-solutions/' ),
		'cms-development'                   => home_url( '/cms-solutions/' ),
		'cloud-consulting'                  => home_url( '/cloud-services/' ),
		'careers'                           => home_url( '/about-piecyfer/' ),
	);

	if ( isset( $redirect_map[ $path ] ) ) {
		wp_safe_redirect( $redirect_map[ $path ], 301 );
		exit;
	}
}
add_action( 'template_redirect', 'piecyfer_legacy_301_redirects', 1 );

/**
 * Exclude internal / builder post types from XML Sitemaps.
 *
 * Prevents elementskit_content, elementor_library, and builder templates from appearing in sitemaps.
 *
 * @param array $post_types List of post types in sitemap.
 * @return array
 */
function piecyfer_clean_sitemaps_post_types( $post_types ) {
	unset( $post_types['elementskit_content'] );
	unset( $post_types['elementor_library'] );
	unset( $post_types['e-landing-page'] );
	unset( $post_types['elementor_font'] );
	unset( $post_types['elementor_icons'] );
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'piecyfer_clean_sitemaps_post_types' );

