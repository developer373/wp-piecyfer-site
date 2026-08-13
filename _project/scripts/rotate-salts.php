#!/usr/bin/env php
<?php
/**
 * Replace the eight authentication salts in wp-config.php with fresh ones.
 * Rotating these invalidates every existing login cookie, which is how you
 * evict an attacker who still holds a valid session.
 *
 * Usage: php rotate-salts.php <path-to-wp-config.php> <path-to-salts.txt>
 */
list( , $config_path, $salts_path ) = $argv + array( null, null, null );
if ( ! $config_path || ! is_file( $config_path ) ) { exit( "usage: rotate-salts.php <wp-config.php> <salts.txt>\n" ); }

$config = file_get_contents( $config_path );
$salts  = file_get_contents( $salts_path );

$keys = array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
               'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' );

// Pull each new value out of the api.wordpress.org response.
$new = array();
foreach ( $keys as $k ) {
	if ( preg_match( "/define\(\s*'" . $k . "'\s*,\s*'(.*)'\s*\);/", $salts, $m ) ) {
		$new[ $k ] = $m[1];
	}
}
if ( count( $new ) !== 8 ) { exit( 'ERROR: only ' . count( $new ) . "/8 salts parsed — aborting.\n" ); }

$replaced = 0;
foreach ( $new as $k => $v ) {
	// Match the existing define regardless of quote style or spacing.
	$pattern = "/define\(\s*(['\"])" . $k . "\\1\s*,\s*(['\"])(?:\\\\.|(?!\\2).)*\\2\s*\);/s";
	$value   = addcslashes( $v, "\\'" );
	$out     = preg_replace( $pattern, "define( '$k', '" . str_replace( '$', '\\$', $value ) . "' );", $config, 1, $n );
	if ( $n === 1 ) { $config = $out; $replaced++; }
	else { echo "  WARN: could not find $k\n"; }
}

if ( $replaced !== 8 ) { exit( "ERROR: replaced $replaced/8 — wp-config.php NOT written.\n" ); }

// Verify the result still parses as PHP before overwriting anything.
$tmp = $config_path . '.new';
file_put_contents( $tmp, $config );
exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $tmp ) . ' 2>&1', $lint, $code );
if ( $code !== 0 ) {
	unlink( $tmp );
	exit( "ERROR: rewritten config failed php -l:\n" . implode( "\n", $lint ) . "\n" );
}
rename( $tmp, $config_path );
echo "Rotated all 8 salts in $config_path (syntax verified).\n";
echo "Every existing login session is now invalid.\n";
