<?php
/**
 * Switch the active WordPress theme.
 *
 * Usage:  php switch-theme.php <theme-slug>
 * Example: php switch-theme.php piecyfer-theme
 *
 * Prints the old and new active theme for verification.
 */

if ( empty( $argv[1] ) ) {
	fwrite( STDERR, "usage: php switch-theme.php <theme-slug>\n" );
	exit( 1 );
}

$target = $argv[1];

// Bootstrap WordPress.
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'wp-load.php';

$old = get_option( 'stylesheet' );
echo "current theme: {$old}\n";

$theme = wp_get_theme( $target );
if ( ! $theme->exists() ) {
	fwrite( STDERR, "error: theme '{$target}' does not exist\n" );
	exit( 1 );
}

switch_theme( $target );

$new = get_option( 'stylesheet' );
echo "switched to:   {$new}\n";

if ( $new !== $target ) {
	fwrite( STDERR, "error: switch_theme() did not take effect\n" );
	exit( 1 );
}

echo "ok\n";
