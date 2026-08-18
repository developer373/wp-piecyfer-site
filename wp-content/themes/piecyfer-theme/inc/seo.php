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
		'service'                         => home_url( '/#services' ),
		'services'                        => home_url( '/#services' ),
		'hire-developers'                 => home_url( '/hire-an-expert/' ),
		'staff-augmentation'              => home_url( '/hire-an-expert/' ),
		'dedicated-developers'            => home_url( '/hire-an-expert/' ),
		'custom-software-development'     => home_url( '/enterprise-software-development/' ),
		'qa-testing'                      => home_url( '/software-quality-testing/' ),
		'quality-assurance'               => home_url( '/software-quality-testing/' ),
		'cloud-consulting'                => home_url( '/cloud-services/' ),
		'careers'                         => home_url( '/about-piecyfer/' ),
	);

	if ( isset( $redirect_map[ $path ] ) ) {
		wp_safe_redirect( $redirect_map[ $path ], 301 );
		exit;
	}
}
add_action( 'template_redirect', 'piecyfer_legacy_301_redirects', 1 );
