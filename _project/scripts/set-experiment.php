#!/usr/bin/env php
<?php
/**
 * Turn an Elementor experiment on or off, and clear the caches it owns.
 *
 * Elementor stores experiment state in a plain option, `elementor_experiment-<name>`,
 * with the values `active`, `inactive` or `default`. Setting it directly is the
 * same thing the Experiments admin screen does.
 *
 * Usage:
 *   php set-experiment.php e_element_cache inactive
 *   php set-experiment.php e_element_cache            # report only
 */

define( 'WP_USE_THEMES', false );
require_once 'C:/xampp/htdocs/piecyfer/wp-load.php';

if ( ! did_action( 'elementor/loaded' ) ) {
	exit( "Elementor is not loaded.\n" );
}

$name  = $argv[1] ?? '';
$state = $argv[2] ?? '';

if ( '' === $name ) {
	exit( "usage: php set-experiment.php <experiment> [active|inactive|default]\n" );
}

$option = 'elementor_experiment-' . $name;
$before = get_option( $option, '(unset)' );

printf( "%s: %s\n", $option, $before );

if ( '' === $state ) {
	exit( 0 );
}

if ( ! in_array( $state, array( 'active', 'inactive', 'default' ), true ) ) {
	exit( "state must be one of: active, inactive, default\n" );
}

update_option( $option, $state );
printf( "%s -> %s\n", $option, get_option( $option ) );

/*
 * Clearing is not optional here.
 *
 * `_elementor_element_cache` postmeta written while the experiment was on stays
 * valid-looking for its full 24-hour TTL, and `print_elements()` reads it before
 * it checks anything else. Without this, turning the experiment off appears to
 * change nothing at all for a day.
 */
\Elementor\Plugin::$instance->files_manager->clear_cache();

global $wpdb;
$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache'" );
printf( "caches cleared; _elementor_element_cache rows remaining: %d\n", $left );
