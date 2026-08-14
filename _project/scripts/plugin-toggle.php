#!/usr/bin/env php
<?php
/**
 * Activate / deactivate / list plugins by editing the `active_plugins` option
 * directly. Used instead of WP-CLI because this environment has no WP-CLI, and
 * because it never boots WordPress — so a broken plugin cannot stop us from
 * disabling it.
 *
 * Usage:
 *   php plugin-toggle.php list
 *   php plugin-toggle.php off <slug> [<slug> ...]
 *   php plugin-toggle.php on  <slug> [<slug> ...]
 *
 * `slug` is the plugin directory name, e.g. `maintenance`.
 * Every write snapshots the previous value to `active_plugins_backup_<time>`
 * so any change can be reverted.
 */

$root = 'D:/laragon/www/piecyfer';

$config = file_get_contents( "$root/wp-config.php" );
function c( $config, $n, $d = null ) {
	// {$n} braces are required: "$n['\"]" would be parsed as array access.
	return preg_match( "/define\(\s*['\"]{$n}['\"]\s*,\s*['\"](.*?)['\"]\s*\)/s", $config, $m ) ? $m[1] : $d;
}
$prefix = preg_match( '/\$table_prefix\s*=\s*[\'"](.*?)[\'"]/', $config, $m ) ? $m[1] : 'wp_';

$db = new mysqli( c( $config, 'DB_HOST', 'localhost' ), c( $config, 'DB_USER' ), c( $config, 'DB_PASSWORD' ), c( $config, 'DB_NAME' ) );
if ( $db->connect_errno ) { exit( "DB error: {$db->connect_error}\n" ); }
$db->set_charset( 'utf8mb4' );

$cmd   = $argv[1] ?? 'list';
$slugs = array_slice( $argv, 2 );

$row    = $db->query( "SELECT option_value FROM {$prefix}options WHERE option_name='active_plugins'" )->fetch_row();
$active = unserialize( $row[0] );
if ( ! is_array( $active ) ) { exit( "could not read active_plugins\n" ); }

/** Map a directory slug to its "slug/main-file.php" entry as stored by WordPress. */
function entry_for( $root, $slug ) {
	foreach ( glob( "$root/wp-content/plugins/$slug/*.php" ) as $f ) {
		$head = file_get_contents( $f, false, null, 0, 8192 );
		if ( stripos( $head, 'Plugin Name:' ) !== false ) {
			return $slug . '/' . basename( $f );
		}
	}
	return null;
}

if ( $cmd === 'list' ) {
	echo count( $active ) . " active plugins:\n";
	foreach ( $active as $a ) { echo "  $a\n"; }
	$all = array_map( 'basename', glob( "$root/wp-content/plugins/*", GLOB_ONLYDIR ) );
	$on  = array_map( function ( $a ) { return explode( '/', $a )[0]; }, $active );
	$off = array_diff( $all, $on );
	echo "\n" . count( $off ) . " installed but inactive:\n";
	foreach ( $off as $o ) { echo "  $o\n"; }
	exit;
}

if ( ! in_array( $cmd, array( 'on', 'off' ), true ) || ! $slugs ) {
	exit( "usage: plugin-toggle.php list | on <slug>... | off <slug>...\n" );
}

$before = $active;
foreach ( $slugs as $slug ) {
	if ( $cmd === 'off' ) {
		$n      = count( $active );
		$active = array_values( array_filter( $active, function ( $a ) use ( $slug ) {
			return strpos( $a, "$slug/" ) !== 0;
		} ) );
		echo ( count( $active ) < $n ) ? "  deactivated: $slug\n" : "  (not active): $slug\n";
	} else {
		$entry = entry_for( $root, $slug );
		if ( ! $entry ) { echo "  ERROR: no plugin header found for $slug\n"; continue; }
		if ( in_array( $entry, $active, true ) ) { echo "  (already on): $slug\n"; continue; }
		$active[] = $entry;
		echo "  activated: $entry\n";
	}
}

// Snapshot before writing, so this is always revertible.
$stamp = date( 'YmdHis' );
$stmt  = $db->prepare( "INSERT INTO {$prefix}options (option_name, option_value, autoload) VALUES (?, ?, 'off')" );
$name  = "active_plugins_backup_$stamp";
$val   = serialize( $before );
$stmt->bind_param( 'ss', $name, $val );
$stmt->execute();
$stmt->close();

$stmt = $db->prepare( "UPDATE {$prefix}options SET option_value=? WHERE option_name='active_plugins'" );
$val  = serialize( array_values( $active ) );
$stmt->bind_param( 's', $val );
$stmt->execute();
$stmt->close();

echo "\n" . count( $before ) . ' -> ' . count( $active ) . " active plugins\n";
echo "revert with: option `$name`\n";
