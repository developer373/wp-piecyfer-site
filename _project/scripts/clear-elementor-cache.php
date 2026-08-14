#!/usr/bin/env php
<?php
/**
 * Clear Elementor's generated CSS and per-page asset caches.
 *
 * Needed during development whenever a control's *default* or `selectors`
 * change. piecyfer-core clears the cache automatically when its widget or
 * stylesheet list changes, but a default is invisible to that signature — and a
 * stale cache silently serves the old generated CSS, which looks exactly like
 * the fix not working.
 *
 * Usage: php clear-elementor-cache.php
 */

define( 'WP_USE_THEMES', false );
require_once 'C:/xampp/htdocs/piecyfer/wp-load.php';

if ( ! did_action( 'elementor/loaded' ) ) {
	exit( "Elementor is not loaded.\n" );
}

global $wpdb;

$before_css    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_css'" );
$before_assets = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_page_assets'" );

\Elementor\Plugin::$instance->files_manager->clear_cache();

$after_css    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_css'" );
$after_assets = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_page_assets'" );

printf( "_elementor_css         %d -> %d\n", $before_css, $after_css );
printf( "_elementor_page_assets %d -> %d\n", $before_assets, $after_assets );

$files = glob( WP_CONTENT_DIR . '/uploads/elementor/css/*.css' );
printf( "generated css files remaining: %d\n", is_array( $files ) ? count( $files ) : 0 );
echo "done — the next page view regenerates from the live widgets.\n";
