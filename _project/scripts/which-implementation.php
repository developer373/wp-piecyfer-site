#!/usr/bin/env php
<?php
/**
 * Report which class is actually registered for each Elementor widget and
 * dynamic tag.
 *
 * The whole side-by-side strategy rests on "the later registration wins", and
 * a rendered page looks identical whether Pro or PieCyfer produced it — which
 * is the point, but it also means a widget could silently fail to take over
 * and the comparison would still pass. This makes the takeover visible.
 *
 * Usage: php which-implementation.php [filter]
 */

define( 'WP_USE_THEMES', false );
require_once 'C:/xampp/htdocs/piecyfer/wp-load.php';

if ( ! did_action( 'elementor/loaded' ) ) {
	exit( "Elementor is not loaded.\n" );
}

$filter = $argv[1] ?? '';

// Force both managers to build their registries.
do_action( 'elementor/init' );

echo "=== WIDGETS ===\n";
$widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
$rows    = array();
foreach ( $widgets as $name => $instance ) {
	$class = get_class( $instance );
	$owner = str_starts_with( $class, 'PieCyfer\\' ) ? 'PIECYFER'
		: ( str_starts_with( $class, 'ElementorPro\\' ) ? 'elementor-pro'
		: ( str_starts_with( $class, 'Elementor\\' ) ? 'elementor-free' : 'other' ) );
	if ( '' !== $filter && false === stripos( $name, $filter ) ) {
		continue;
	}
	$rows[ $name ] = array( $owner, $class );
}
ksort( $rows );
foreach ( $rows as $name => list( $owner, $class ) ) {
	printf( "  %-26s %-15s %s\n", $name, $owner, $class );
}

echo "\n=== DYNAMIC TAGS ===\n";
$tags_manager = \Elementor\Plugin::$instance->dynamic_tags;

// `tags_info` is private and holds [ name => [ 'class' => …, 'instance' => … ] ].
// Read it reflectively rather than instantiating every tag, which would fire
// their control registration for no reason.
$tags = array();
try {
	$prop = new ReflectionProperty( $tags_manager, 'tags_info' );
	$prop->setAccessible( true );
	$tags_manager->get_tags(); // force registration
	$tags = $prop->getValue( $tags_manager );
} catch ( \Throwable $e ) {
	echo '  (could not read the tag registry: ' . $e->getMessage() . ")\n";
}

if ( ! $tags ) {
	echo "  (no tags registered)\n";
} else {
	$rows = array();
	foreach ( $tags as $name => $info ) {
		$class = is_array( $info ) ? ( $info['class'] ?? '?' ) : ( is_object( $info ) ? get_class( $info ) : '?' );
		$owner = str_starts_with( $class, 'PieCyfer\\' ) ? 'PIECYFER'
			: ( str_starts_with( $class, 'ElementorPro\\' ) ? 'elementor-pro'
			: ( str_starts_with( $class, 'Elementor\\' ) ? 'elementor-free' : 'other' ) );
		if ( '' !== $filter && false === stripos( (string) $name, $filter ) ) {
			continue;
		}
		$rows[ $name ] = array( $owner, $class );
	}
	ksort( $rows );
	foreach ( $rows as $name => list( $owner, $class ) ) {
		printf( "  %-26s %-15s %s\n", $name, $owner, $class );
	}
}

echo "\n=== SUMMARY ===\n";
$ours = 0;
foreach ( $widgets as $instance ) {
	if ( str_starts_with( get_class( $instance ), 'PieCyfer\\' ) ) {
		$ours++;
	}
}
printf( "  widgets owned by piecyfer-core: %d of %d registered\n", $ours, count( $widgets ) );
