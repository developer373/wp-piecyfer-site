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

/*
 * The gate. Everything above is a report; this is the part that fails.
 *
 * A widget can be listed in Plugin::WIDGETS, load without error, and still not
 * be the class that renders — the registry is a plain array keyed on widget
 * name, so the last registration wins. VamTam's companion plugin calls
 * unregister()/register() at priority 100 for six names, three of which are
 * ours. If we lose that race the page looks exactly the same, because Pro's or
 * VamTam's widget draws it, and the pixel comparison passes. That is the most
 * dangerous failure this project has: a green result for a takeover that never
 * happened.
 *
 * So: every name in Plugin::WIDGETS must actually resolve to a PieCyfer class.
 * Non-zero exit otherwise, so it can gate a verification run.
 */
echo "\n=== TAKEOVER GATE ===\n";

if ( ! class_exists( '\PieCyfer\Core\Plugin' ) || ! method_exists( '\PieCyfer\Core\Plugin', 'widget_classes' ) ) {
	echo "  (piecyfer-core is not loaded, or Plugin::widget_classes() is gone — gate cannot run)\n";
	exit( 2 );
}

// Which class is serving each registered widget name, keyed by class.
$serving = array();
foreach ( $widgets as $name => $instance ) {
	$serving[ get_class( $instance ) ] = $name;
}

$expected = \PieCyfer\Core\Plugin::widget_classes();
$failures = array();

foreach ( $expected as $class ) {
	if ( isset( $serving[ $class ] ) ) {
		printf( "  %-40s ok (serving '%s')\n", $class, $serving[ $class ] );
		continue;
	}

	// Registered but displaced: find who holds the name we wanted.
	$name = 'unknown';
	if ( class_exists( $class ) ) {
		try {
			$name = ( new $class() )->get_name();
		} catch ( \Throwable $e ) {
			$name = 'unknown';
		}
	}
	$holder = isset( $widgets[ $name ] ) ? get_class( $widgets[ $name ] ) : 'nothing — not registered';

	$failures[ $class ] = sprintf( "'%s' is served by %s", $name, $holder );
	printf( "  %-40s FAIL — %s\n", $class, $failures[ $class ] );
}

/*
 * Skins are a second, independent way to lose the takeover.
 *
 * Owning the `posts` widget is not the same as owning what draws a post. Its
 * output comes from a Skin, selected by the saved `_skin` setting, and skins are
 * registered onto the widget by whoever asks — VamTam registers `vamtam_classic`
 * onto `posts` and `archive-posts`, and all 15 saved instances select it. So we
 * could win the widget race, render through someone else's skin, and see a
 * perfect pixel comparison. Exactly the failure the widget gate exists for, one
 * level down.
 *
 * Anything not ours must be listed here deliberately, so that keeping a
 * third-party skin is a decision on the record rather than an oversight.
 */
$allowed_foreign_skins = array(
	/*
	 * Empty, and it must stay that way unless someone deliberately adds an
	 * entry. The two VamTam skins that used to sit here came out the moment
	 * posts/archive-posts were registered — leaving them would have meant the
	 * gate happily approving a run where VamTam's skin, not ours, was drawing
	 * every post.
	 */
);

echo "\n=== SKIN GATE ===\n";

$skin_failures = array();
foreach ( $expected as $class ) {
	$name = $serving[ $class ] ?? null;
	if ( null === $name ) {
		continue; // already reported by the widget gate
	}

	$skins = $widgets[ $name ]->get_skins();
	if ( ! $skins ) {
		continue;
	}

	foreach ( $skins as $skin_id => $skin ) {
		$skin_class = get_class( $skin );
		$key        = $name . ':' . $skin_id;

		if ( str_starts_with( $skin_class, 'PieCyfer\\' ) ) {
			printf( "  %-34s ok        %s\n", $key, $skin_class );
			continue;
		}

		if ( in_array( $key, $allowed_foreign_skins, true ) ) {
			printf( "  %-34s allowed   %s\n", $key, $skin_class );
			continue;
		}

		$skin_failures[ $key ] = $skin_class;
		printf( "  %-34s FAIL      %s\n", $key, $skin_class );
	}
}

if ( ! $skin_failures ) {
	echo "  (no unexpected third-party skins)\n";
}

if ( $failures || $skin_failures ) {
	printf(
		"\n  %d widget(s) and %d skin(s) are not ours and not on the allow-list.\n" .
		"  Do not trust any comparison run in this state: the page will look correct\n" .
		"  because someone else's code is drawing it.\n",
		count( $failures ),
		count( $skin_failures )
	);
	exit( 1 );
}

printf( "\n  all %d declared widget(s) served by piecyfer-core; every skin accounted for\n", count( $expected ) );
exit( 0 );
