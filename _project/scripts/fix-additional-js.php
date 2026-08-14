#!/usr/bin/env php
<?php
/**
 * Make the theme's Additional JS head snippet null-safe and DOM-ready.
 *
 * The snippet does:
 *     const svgWrapper = document.querySelector('.select-caret-down-wrapper');
 *     svgWrapper.innerHTML = newSvg;
 *
 * ...in <head>, before <body> exists — so svgWrapper is always null there and
 * the assignment throws "Cannot set properties of null". Every page load hits
 * it, and because the exception aborts the whole inline script, nothing after
 * it in that block runs either.
 *
 * It has been invisible until now only because wp-meteor deferred all
 * JavaScript until after DOM ready, which happened to make the selector match.
 * Removing wp-meteor exposes the latent bug, so this must be fixed to keep the
 * caret replacement working — i.e. to preserve current rendering, not change it.
 *
 * The rewrite also handles *all* matching wrappers rather than only the first;
 * Elementor Pro emits one per <select>, and a page with two selects previously
 * got only one styled.
 *
 * Usage:  php fix-additional-js.php [--dry-run]
 */

$root    = 'D:/laragon/www/piecyfer';
$dry_run = in_array( '--dry-run', $argv, true );

$config = file_get_contents( "$root/wp-config.php" );
function c( $config, $n, $d = null ) {
	return preg_match( "/define\(\s*['\"]{$n}['\"]\s*,\s*['\"](.*?)['\"]\s*\)/s", $config, $m ) ? $m[1] : $d;
}
$prefix = preg_match( '/\$table_prefix\s*=\s*[\'"](.*?)[\'"]/', $config, $m ) ? $m[1] : 'wp_';

$db = new mysqli( c( $config, 'DB_HOST', 'localhost' ), c( $config, 'DB_USER' ), c( $config, 'DB_PASSWORD' ), c( $config, 'DB_NAME' ) );
if ( $db->connect_errno ) { exit( "DB error: {$db->connect_error}\n" ); }
$db->set_charset( 'utf8mb4' );

$row = $db->query( "SELECT option_value FROM {$prefix}options WHERE option_name='vamtam_additional_js'" )->fetch_row();
if ( ! $row ) { exit( "option vamtam_additional_js not found\n" ); }

$data = unserialize( $row[0] );
if ( ! is_array( $data ) ) { exit( "could not unserialize option\n" ); }

echo "slots: " . implode( ', ', array_map( fn( $k, $v ) => "$k (" . strlen( (string) $v ) . " bytes)", array_keys( $data ), $data ) ) . "\n\n";

$target = null;
foreach ( $data as $slot => $code ) {
	if ( is_string( $code ) && strpos( $code, 'select-caret-down-wrapper' ) !== false ) { $target = $slot; break; }
}
if ( null === $target ) { exit( "no slot contains the caret snippet — nothing to do\n" ); }
echo "caret snippet lives in slot: $target\n";

$old = $data[ $target ];

if ( strpos( $old, 'PIECYFER-FIX' ) !== false ) { exit( "already patched — nothing to do\n" ); }

// Replace the two fragile lines. Everything else in the slot is left untouched.
$patched = $old;

// 1. Drop the head-time lookup entirely. It runs before <body> exists, so it
//    always returned null; the query now happens inside the DOM-ready handler.
$patched = preg_replace(
	'/const\s+svgWrapper\s*=\s*document\.querySelector\(\s*([\'"])\.select-caret-down-wrapper\1\s*\)\s*;/',
	'// PIECYFER-FIX: lookup moved into the DOM-ready handler below.',
	$patched,
	1,
	$n1
);

// 2. The unguarded assignment -> run on DOM ready, over every match.
$patched = preg_replace(
	'/svgWrapper\.innerHTML\s*=\s*newSvg\s*;/',
	"function piecyferApplyCaret() {\n"
	. "    document.querySelectorAll('.select-caret-down-wrapper').forEach(function (w) { w.innerHTML = newSvg; });\n"
	. "}\n"
	. "if (document.readyState === 'loading') {\n"
	. "    document.addEventListener('DOMContentLoaded', piecyferApplyCaret);\n"
	. "} else {\n"
	. "    piecyferApplyCaret();\n"
	. "}",
	$patched,
	1,
	$n2
);

if ( 1 !== $n1 || 1 !== $n2 ) {
	exit( "ERROR: expected 1 replacement each, got querySelector=$n1 assignment=$n2 — aborting so nothing is half-patched.\n" );
}

echo "\n--- patched region ---\n";
$i = strpos( $patched, 'PIECYFER-FIX' );
echo substr( $patched, max( 0, $i - 120 ), 900 ) . "\n";

if ( $dry_run ) { exit( "\nDRY RUN — nothing written.\n" ); }

$data[ $target ] = $patched;

// Keep the previous value so this is revertible without touching a backup file.
$stamp = date( 'YmdHis' );
$stmt  = $db->prepare( "INSERT INTO {$prefix}options (option_name, option_value, autoload) VALUES (?, ?, 'off')" );
$name  = "vamtam_additional_js_backup_$stamp";
$stmt->bind_param( 'ss', $name, $row[0] );
$stmt->execute();
$stmt->close();

$stmt = $db->prepare( "UPDATE {$prefix}options SET option_value=? WHERE option_name='vamtam_additional_js'" );
$new  = serialize( $data );
$stmt->bind_param( 's', $new );
$stmt->execute();
$stmt->close();

echo "\nwritten. previous value saved as option `$name`.\n";
