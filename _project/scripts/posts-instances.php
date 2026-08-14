<?php
/**
 * Enumerate every saved `posts` / `archive-posts` element instance, and probe
 * the render contexts the markup diff will need.
 *
 * Usage: php posts-instances.php
 */

define( 'WP_USE_THEMES', false );
require_once 'D:/laragon/www/piecyfer/wp-load.php';

global $wpdb;

$rows = $wpdb->get_results(
	"SELECT p.ID, p.post_type, p.post_title, pm.meta_value AS data
	   FROM {$wpdb->postmeta} pm
	   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	  WHERE pm.meta_key = '_elementor_data'
	    AND p.post_type <> 'revision'
	    AND p.post_status IN ('publish','draft','private')
	  ORDER BY p.ID"
);

$found = array();

function walk( array $els, callable $cb ): void {
	foreach ( $els as $el ) {
		if ( ( $el['elType'] ?? '' ) === 'widget' && in_array( $el['widgetType'] ?? '', array( 'posts', 'archive-posts' ), true ) ) {
			$cb( $el );
		}
		if ( ! empty( $el['elements'] ) ) {
			walk( $el['elements'], $cb );
		}
	}
}

foreach ( $rows as $r ) {
	$data = json_decode( $r->data, true );
	if ( ! is_array( $data ) ) {
		continue;
	}
	walk(
		$data,
		function ( $el ) use ( $r, &$found ) {
			$found[] = array(
				'doc'   => (int) $r->ID,
				'type'  => $r->post_type,
				'title' => $r->post_title,
				'wtype' => $el['widgetType'],
				'el'    => $el['id'],
				'skin'  => $el['settings']['_skin'] ?? '(unset)',
				'keys'  => count( $el['settings'] ?? array() ),
			);
		}
	);
}

printf( "%-6s %-18s %-24s %-14s %-10s %-16s %s\n", 'DOC', 'POST TYPE', 'TITLE', 'WIDGET', 'EL', 'SKIN', 'KEYS' );
foreach ( $found as $f ) {
	printf( "%-6d %-18s %-24s %-14s %-10s %-16s %d\n", $f['doc'], $f['type'], substr( $f['title'], 0, 23 ), $f['wtype'], $f['el'], $f['skin'], $f['keys'] );
}
printf( "\ntotal: %d instances\n", count( $found ) );

echo "\n=== theme-builder conditions ===\n";
foreach ( array( 6126, 8502, 8559, 8711 ) as $doc ) {
	$cond = get_post_meta( $doc, '_elementor_conditions', true );
	printf( "  %-6d %-24s %s\n", $doc, get_the_title( $doc ), wp_json_encode( $cond ) );
}

echo "\n=== public post types ===\n";
foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
	printf( "  %-20s has_archive=%s count=%d\n", $pt->name, var_export( $pt->has_archive, true ), (int) wp_count_posts( $pt->name )->publish );
}

echo "\n=== a few categories ===\n";
foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'number' => 12 ) ) as $t ) {
	printf( "  %-24s id=%-5d tt=%-5d count=%d\n", $t->slug, $t->term_id, $t->term_taxonomy_id, $t->count );
}

echo "\n=== newest posts ===\n";
foreach ( get_posts( array( 'numberposts' => 5 ) ) as $p ) {
	printf( "  %-8d %s\n", $p->ID, get_permalink( $p ) );
}

echo "\n=== relevant options / experiments ===\n";
printf( "  posts_per_page       = %s\n", get_option( 'posts_per_page' ) );
printf( "  page_for_posts       = %s\n", get_option( 'page_for_posts' ) );
printf( "  show_on_front        = %s\n", get_option( 'show_on_front' ) );
printf( "  page_on_front        = %s\n", get_option( 'page_on_front' ) );
printf( "  permalink_structure  = %s\n", get_option( 'permalink_structure' ) );
foreach ( array( 'e_element_cache', 'e_optimized_markup', 'e_optimized_css_loading' ) as $e ) {
	printf( "  experiment %-24s = %s\n", $e, var_export( get_option( 'elementor_experiment-' . $e ), true ) );
}
