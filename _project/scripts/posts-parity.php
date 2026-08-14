<?php
/**
 * Control-stack parity for the `posts` / `archive-posts` port.
 *
 * Builds the control stack of the widget the site registers TODAY (Elementor
 * Pro's Posts_Base + Posts/Archive_Posts, every skin in the bucket, plus every
 * VamTam injection), snapshots it, then swaps in the piecyfer-core widgets,
 * deletes the cached stack — Controls_Manager keys stacks by widget NAME, so
 * without delete_stack() the second build silently returns the first — and
 * diffs the two.
 *
 * Order is not negotiable: PostsBaseWidget::register_skins() detaches foreign
 * skins from the shared Skins_Manager bucket and unhooks their control
 * callbacks, so once our type instance exists Pro's stack can never be rebuilt.
 *
 * Usage: php posts-parity.php [--verbose] [--json <path>]
 */

define( 'WP_USE_THEMES', false );
require_once 'C:/xampp/htdocs/piecyfer/wp-load.php';

if ( ! did_action( 'elementor/loaded' ) ) {
	exit( "Elementor is not loaded.\n" );
}

$verbose   = in_array( '--verbose', $argv, true );
$json_path = null;
$json_idx  = array_search( '--json', $argv, true );
if ( false !== $json_idx && isset( $argv[ $json_idx + 1 ] ) ) {
	$json_path = $argv[ $json_idx + 1 ];
}

do_action( 'elementor/init' );

/*
 * Proof that the second stack is really rebuilt.
 *
 * Controls_Manager caches stacks under the widget NAME, so if delete_stack()
 * silently failed the second snapshot would be the FIRST one handed back and
 * every comparison below would pass for the wrong reason — the single most
 * plausible way this script could lie. So count how many times each widget's
 * first section is actually opened: it must be exactly 2 (reference, then
 * ours), and the class building it must change between them.
 */
$GLOBALS['pcf_builds'] = array();
foreach ( array( 'posts', 'archive-posts' ) as $probe ) {
	add_action(
		"elementor/element/{$probe}/section_layout/before_section_end",
		function ( $widget ) use ( $probe ) {
			$GLOBALS['pcf_builds'][ $probe ][] = get_class( $widget );
		},
		1
	);
}

$widgets_manager  = \Elementor\Plugin::$instance->widgets_manager;
$controls_manager = \Elementor\Plugin::$instance->controls_manager;
$skins_manager    = \Elementor\Plugin::$instance->skins_manager;

$names = array( 'posts', 'archive-posts' );

/**
 * Fields worth comparing on every control. Anything not in this list is still
 * caught by the "unfiltered" pass below; this list only drives the summary.
 */
const INTERESTING = array( 'type', 'section', 'tab', 'default', 'condition', 'conditions', 'selectors', 'selector', 'prefix_class', 'frontend_available', 'responsive', 'options', 'label_block', 'render_type', 'separator', 'dynamic', 'global', 'size_units', 'range', 'multiple', 'exclude', 'ai', 'fa4compatibility', 'recommended', 'skin', 'content_classes', 'raw', 'tablet_default', 'mobile_default', 'exclude_inline_options', 'return_value', 'label_on', 'label_off', 'min', 'max', 'step', 'placeholder', 'title', 'classes', 'device_args', 'groups', 'fields_options', 'popover_toggle', 'starter_name', 'starter_value', 'starter_title' );

function snapshot( \Elementor\Widget_Base $w ): array {
	$stack = $w->get_stack();

	$controls = $stack['controls'];
	$order    = array_keys( $controls );

	$sections = array();
	$tabs     = array();
	foreach ( $controls as $id => $c ) {
		if ( \Elementor\Controls_Manager::SECTION === ( $c['type'] ?? '' ) ) {
			$sections[] = $id;
		}
		if ( \Elementor\Controls_Manager::TAB === ( $c['type'] ?? '' ) ) {
			$tabs[] = $id;
		}
	}

	$skins = array();
	foreach ( $w->get_skins() as $skin_id => $skin ) {
		$skins[ $skin_id ] = get_class( $skin );
	}

	return array(
		'class'    => get_class( $w ),
		'controls' => $controls,
		'order'    => $order,
		'sections' => $sections,
		'tabs'     => $tabs,
		'tabgroups' => array_keys( $stack['tabs'] ?? array() ),
		'skins'    => $skins,
		'dynamic'  => call_protected( $w, 'is_dynamic_content' ),
		'inner_wrapper' => method_exists( $w, 'has_widget_inner_wrapper' ) ? call_protected( $w, 'has_widget_inner_wrapper' ) : null,
		'styles'   => $w->get_style_depends(),
		'scripts'  => $w->get_script_depends(),
		'categories' => $w->get_categories(),
		'title'    => $w->get_title(),
		'icon'     => $w->get_icon(),
	);
}

/* ---------------------------------------------------------------- reference */

$reference = array();
foreach ( $names as $n ) {
	$w = $widgets_manager->get_widget_types( $n );
	if ( ! $w ) {
		exit( "No widget registered for '$n' — is Elementor Pro active?\n" );
	}
	$reference[ $n ] = snapshot( $w );
}

/* ------------------------------------------------------------------- ours */

$ours_classes = array(
	'posts'         => \PieCyfer\Core\Widgets\PostsWidget::class,
	'archive-posts' => \PieCyfer\Core\Widgets\ArchivePostsWidget::class,
);

$mine = array();
foreach ( $names as $n ) {
	$class = $ours_classes[ $n ];
	$inst  = new $class();
	$widgets_manager->register( $inst );

	// Stacks are keyed by widget name; the reference build already populated it.
	$controls_manager->delete_stack( $inst );

	$mine[ $n ] = snapshot( $inst );
}

/* ------------------------------------------------------------------- diff */

$exit = 0;
$report = array();

foreach ( $names as $n ) {
	$r = $reference[ $n ];
	$m = $mine[ $n ];

	echo str_repeat( '=', 78 ) . "\n";
	printf( "%s\n  reference : %s\n  ours      : %s\n", strtoupper( $n ), $r['class'], $m['class'] );
	echo str_repeat( '=', 78 ) . "\n";

	$ref_ids = array_keys( $r['controls'] );
	$our_ids = array_keys( $m['controls'] );

	$missing = array_values( array_diff( $ref_ids, $our_ids ) );
	$extra   = array_values( array_diff( $our_ids, $ref_ids ) );

	printf( "controls  reference=%d  ours=%d  missing=%d  extra=%d\n", count( $ref_ids ), count( $our_ids ), count( $missing ), count( $extra ) );

	if ( $missing ) {
		$exit = 1;
		echo "  MISSING (in the live stack, absent from ours):\n";
		foreach ( $missing as $id ) {
			printf( "    - %-52s [%s]\n", $id, $r['controls'][ $id ]['type'] ?? '?' );
		}
	}
	if ( $extra ) {
		$exit = 1;
		echo "  EXTRA (ours only — Elementor would write keys nothing reads):\n";
		foreach ( $extra as $id ) {
			printf( "    + %-52s [%s]\n", $id, $m['controls'][ $id ]['type'] ?? '?' );
		}
	}

	// Sections, in order.
	if ( $r['sections'] !== $m['sections'] ) {
		$exit = 1;
		echo "  SECTION IDS DIFFER (order-sensitive):\n";
		echo '    reference: ' . implode( ', ', $r['sections'] ) . "\n";
		echo '    ours     : ' . implode( ', ', $m['sections'] ) . "\n";
		$sm = array_diff( $r['sections'], $m['sections'] );
		$se = array_diff( $m['sections'], $r['sections'] );
		if ( $sm ) { echo '    missing  : ' . implode( ', ', $sm ) . "\n"; }
		if ( $se ) { echo '    extra    : ' . implode( ', ', $se ) . "\n"; }
	} else {
		printf( "sections  %d, identical and in order\n", count( $r['sections'] ) );
	}

	if ( $r['tabs'] !== $m['tabs'] ) {
		$exit = 1;
		echo "  TAB IDS DIFFER (order-sensitive):\n";
		echo '    reference: ' . implode( ', ', $r['tabs'] ) . "\n";
		echo '    ours     : ' . implode( ', ', $m['tabs'] ) . "\n";
	} else {
		printf( "tabs      %d, identical and in order\n", count( $r['tabs'] ) );
	}

	if ( $r['tabgroups'] !== $m['tabgroups'] ) {
		$exit = 1;
		echo "  TAB GROUPS DIFFER: ref=[" . implode( ',', $r['tabgroups'] ) . '] ours=[' . implode( ',', $m['tabgroups'] ) . "]\n";
	}

	// Full control ORDER (not just membership).
	if ( $r['order'] !== $m['order'] ) {
		$common_ref = array_values( array_intersect( $r['order'], $m['order'] ) );
		$common_our = array_values( array_intersect( $m['order'], $r['order'] ) );
		if ( $common_ref !== $common_our ) {
			$exit = 1;
			echo "  CONTROL ORDER DIFFERS for the shared ids:\n";
			foreach ( $common_ref as $i => $id ) {
				if ( ( $common_our[ $i ] ?? null ) !== $id ) {
					printf( "    position %d: reference '%s' vs ours '%s'\n", $i, $id, $common_our[ $i ] ?? '(none)' );
					break;
				}
			}
		} else {
			echo "control order for shared ids: identical\n";
		}
	} else {
		echo "control order: identical\n";
	}

	// Skins.
	if ( $r['skins'] !== $m['skins'] ) {
		echo "  SKIN BUCKET (ids must match; classes are expected to differ):\n";
		echo '    reference: ' . json_encode( $r['skins'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
		echo '    ours     : ' . json_encode( $m['skins'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
		if ( array_keys( $r['skins'] ) !== array_keys( $m['skins'] ) ) {
			$exit = 1;
			echo "    ^^ SKIN IDS DIFFER — this is fatal, see 07-POSTS-SPEC.md §9.1\n";
		}
	}

	// Widget-level surface.
	foreach ( array( 'dynamic', 'inner_wrapper', 'categories' ) as $k ) {
		if ( $r[ $k ] !== $m[ $k ] ) {
			$exit = 1;
			printf( "  WIDGET %s DIFFERS: ref=%s ours=%s\n", strtoupper( $k ), json_encode( $r[ $k ] ), json_encode( $m[ $k ] ) );
		}
	}
	printf( "is_dynamic_content: ref=%s ours=%s\n", json_encode( $r['dynamic'] ), json_encode( $m['dynamic'] ) );
	printf( "inner wrapper     : ref=%s ours=%s\n", json_encode( $r['inner_wrapper'] ), json_encode( $m['inner_wrapper'] ) );

	// Reported, not gated: get_style_depends() is a deliberate deviation.
	foreach ( array( 'title', 'icon', 'styles', 'scripts' ) as $k ) {
		$same = $r[ $k ] === $m[ $k ];
		printf( "%-18s: %s ref=%s%s\n", $k, $same ? '   same ' : ' DIFFERS', short( $r[ $k ] ), $same ? '' : ' ours=' . short( $m[ $k ] ) );
	}

	/* ---- unfiltered per-control field diff, for every shared id ---------- */

	$field_diffs = array();
	foreach ( array_intersect( $ref_ids, $our_ids ) as $id ) {
		$a = $r['controls'][ $id ];
		$b = $m['controls'][ $id ];

		// Labels and descriptions are translated strings; the text domain
		// differs by design (`elementor-pro` vs `piecyfer-core`) and neither
		// reaches the front end.
		$skip = array( 'label', 'description', 'title', 'placeholder', 'label_on', 'label_off', 'separator_label' );

		$keys = array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) );
		foreach ( $keys as $k ) {
			if ( in_array( $k, $skip, true ) ) {
				continue;
			}
			$av = $a[ $k ] ?? '<<absent>>';
			$bv = $b[ $k ] ?? '<<absent>>';
			if ( $av === $bv ) {
				continue;
			}
			// options/toggle labels are UI text too
			if ( in_array( $k, array( 'options', 'recommended', 'groups', 'fields_options', 'device_args', 'label_block' ), true ) && normalise_labels( $av ) === normalise_labels( $bv ) ) {
				continue;
			}
			$field_diffs[ $id ][ $k ] = array( $av, $bv );
		}
	}

	// Split into the ones that can change output and the rest.
	$loadbearing = array();
	$cosmetic    = array();
	$critical_keys = array( 'type', 'section', 'tab', 'default', 'condition', 'conditions', 'selectors', 'selector', 'prefix_class', 'frontend_available', 'responsive', 'render_type', 'global', 'dynamic', 'return_value', 'tablet_default', 'mobile_default' );
	foreach ( $field_diffs as $id => $fields ) {
		foreach ( $fields as $k => $pair ) {
			if ( in_array( $k, $critical_keys, true ) ) {
				$loadbearing[ $id ][ $k ] = $pair;
			} else {
				$cosmetic[ $id ][ $k ] = $pair;
			}
		}
	}

	printf( "field diffs: %d control(s) differ in a load-bearing field, %d in a cosmetic one\n", count( $loadbearing ), count( $cosmetic ) );

	if ( $loadbearing ) {
		$exit = 1;
		echo "  LOAD-BEARING FIELD DIFFS:\n";
		foreach ( $loadbearing as $id => $fields ) {
			printf( "    %s\n", $id );
			foreach ( $fields as $k => $pair ) {
				printf( "      %-20s ref=%s\n      %-20s our=%s\n", $k, short( $pair[0] ), '', short( $pair[1] ) );
			}
		}
	}

	if ( $cosmetic && $verbose ) {
		echo "  COSMETIC FIELD DIFFS:\n";
		foreach ( $cosmetic as $id => $fields ) {
			printf( "    %s\n", $id );
			foreach ( $fields as $k => $pair ) {
				printf( "      %-20s ref=%s | our=%s\n", $k, short( $pair[0] ), short( $pair[1] ) );
			}
		}
	} elseif ( $cosmetic ) {
		echo '  (' . count( $cosmetic ) . " control(s) with cosmetic-only diffs; re-run with --verbose)\n";
	}

	echo "\n";

	$report[ $n ] = array(
		'reference_class' => $r['class'],
		'our_class'       => $m['class'],
		'reference_count' => count( $ref_ids ),
		'our_count'       => count( $our_ids ),
		'missing'         => $missing,
		'extra'           => $extra,
		'sections_ref'    => $r['sections'],
		'sections_our'    => $m['sections'],
		'tabs_ref'        => $r['tabs'],
		'tabs_our'        => $m['tabs'],
		'skins_ref'       => $r['skins'],
		'skins_our'       => $m['skins'],
		'loadbearing'     => $loadbearing,
		'cosmetic'        => $cosmetic,
	);
}

echo str_repeat( '=', 78 ) . "\n";
echo "STACK-REBUILD PROOF (delete_stack actually took effect)\n";
echo str_repeat( '=', 78 ) . "\n";
foreach ( $names as $n ) {
	$builds = array_unique( $GLOBALS['pcf_builds'][ $n ] ?? array() );
	printf( "  %-14s section_layout built by: %s\n", $n, implode( ' , ', $builds ) ?: '(never)' );
	if ( count( $builds ) < 2 ) {
		$exit = 1;
		echo "    ^^ the stack was NOT rebuilt — every diff above is meaningless.\n";
	}
}
echo "\n";

if ( $json_path ) {
	file_put_contents( $json_path, json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	echo "wrote $json_path\n";
}

echo $exit ? "PARITY: FAIL\n" : "PARITY: OK — zero missing, zero extra, sections and tabs identical and in order.\n";
exit( $exit );

/* ------------------------------------------------------------------ helpers */

function call_protected( object $obj, string $method ) {
	try {
		$m = new ReflectionMethod( $obj, $method );
		$m->setAccessible( true );
		return $m->invoke( $obj );
	} catch ( \Throwable $e ) {
		return '<<error: ' . $e->getMessage() . '>>';
	}
}

function short( $v ): string {
	$s = is_string( $v ) ? $v : json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$s = (string) $s;
	return strlen( $s ) > 300 ? substr( $s, 0, 300 ) . '…' : $s;
}

/** Replace every leaf string with its position, so only the shape is compared. */
function normalise_labels( $v ) {
	if ( ! is_array( $v ) ) {
		return is_string( $v ) ? '<str>' : $v;
	}
	$out = array();
	foreach ( $v as $k => $vv ) {
		$out[ $k ] = normalise_labels( $vv );
	}
	return $out;
}
