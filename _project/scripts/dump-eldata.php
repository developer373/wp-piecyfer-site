#!/usr/bin/env php
<?php
/**
 * Export every live `_elementor_data` document as JSON, for analyse-widgets.js.
 * Revisions and auto-drafts are excluded — we only care about what renders.
 *
 * Usage: php dump-eldata.php > eldata.json
 */
$root   = 'C:/xampp/htdocs/piecyfer';
$config = file_get_contents( "$root/wp-config.php" );
function c( $config, $n, $d = null ) {
	return preg_match( "/define\(\s*['\"]{$n}['\"]\s*,\s*['\"](.*?)['\"]\s*\)/s", $config, $m ) ? $m[1] : $d;
}
$prefix = preg_match( '/\$table_prefix\s*=\s*[\'"](.*?)[\'"]/', $config, $m ) ? $m[1] : 'wp_';

$db = new mysqli( c( $config, 'DB_HOST', 'localhost' ), c( $config, 'DB_USER' ), c( $config, 'DB_PASSWORD' ), c( $config, 'DB_NAME' ) );
if ( $db->connect_errno ) { fwrite( STDERR, "DB error: {$db->connect_error}\n" ); exit( 1 ); }
$db->set_charset( 'utf8mb4' );

$sql = "
	SELECT p.ID, p.post_type, p.post_title, p.post_status, pm.meta_value AS data
	  FROM {$prefix}postmeta pm
	  JOIN {$prefix}posts p ON p.ID = pm.post_id
	 WHERE pm.meta_key = '_elementor_data'
	   AND p.post_type <> 'revision'
	   AND p.post_status IN ('publish','draft','private')
	 ORDER BY p.post_type, p.ID";

$out = array();
$res = $db->query( $sql );
while ( $row = $res->fetch_assoc() ) {
	if ( $row['data'] === '' || $row['data'] === '[]' ) { continue; }
	$out[] = array(
		'id'     => (int) $row['ID'],
		'type'   => $row['post_type'],
		'title'  => $row['post_title'],
		'status' => $row['post_status'],
		'data'   => $row['data'],
	);
}
fwrite( STDERR, 'exported ' . count( $out ) . " documents\n" );
echo json_encode( $out );
