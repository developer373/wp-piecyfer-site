<?php
/**
 * Byte-for-byte markup comparison for the `posts` / `archive-posts` port.
 *
 * For every one of the 15 saved instances: set up a realistic WordPress request
 * context, render the widget the site registers TODAY (Elementor Pro's
 * Posts_Base + VamTam's subclass and skin), then swap in the piecyfer-core
 * widget and render the same instance again, and diff the two strings.
 *
 * Three things make this trustworthy rather than merely green:
 *
 *   1. **Self-stability first.** Each reference instance is rendered twice
 *      before ours runs. If reference != reference the context is unstable and
 *      the instance is reported as UNSTABLE rather than passing.
 *   2. **The swap is one-way and irreversible.** PostsBaseWidget::register_skins()
 *      detaches foreign skins from the shared Skins_Manager bucket and unhooks
 *      their control callbacks, so all reference rendering must finish before
 *      the first piecyfer-core type instance is constructed. It does.
 *   3. **The context is validated against a real capture.** Where a snapshot
 *      exists, the reference render's <article> count and container class are
 *      checked against _project/snapshots/ref2-a/html/. A context that does not
 *      reproduce the real page is called out, not silently trusted.
 *
 * Usage: php posts-markup-diff.php [--dump <dir>] [--show <el-id>]
 */

define( 'WP_USE_THEMES', false );
require_once 'C:/xampp/htdocs/piecyfer/wp-load.php';

if ( ! did_action( 'elementor/loaded' ) ) {
	exit( "Elementor is not loaded.\n" );
}

do_action( 'elementor/init' );

$dump_dir = null;
$idx      = array_search( '--dump', $argv, true );
if ( false !== $idx && isset( $argv[ $idx + 1 ] ) ) {
	$dump_dir = rtrim( $argv[ $idx + 1 ], '/\\' );
	if ( ! is_dir( $dump_dir ) ) {
		mkdir( $dump_dir, 0777, true );
	}
}
$show = null;
$idx  = array_search( '--show', $argv, true );
if ( false !== $idx && isset( $argv[ $idx + 1 ] ) ) {
	$show = $argv[ $idx + 1 ];
}

$site = untrailingslashit( wp_parse_url( home_url(), PHP_URL_PATH ) );

/* ------------------------------------------------------------------ contexts */

/**
 * `singular` mirrors what a theme does before `the_content` runs: start the
 * loop. Archive contexts deliberately do NOT start it, because Elementor's
 * theme-builder archive document renders outside the loop — confirmed by the
 * real capture, where /?s=software lists ten articles rather than one.
 */
$contexts = array(
	'blogs'       => array( 'query' => array( 'page_id' => 93 ), 'uri' => "$site/blogs/", 'singular' => true, 'snapshot' => 'blogs.html' ),
	'blogs-p2'    => array( 'query' => array( 'page_id' => 93, 'page' => 2 ), 'uri' => "$site/blogs/2/", 'singular' => true, 'snapshot' => null ),
	'home'        => array( 'query' => array( 'page_id' => 146 ), 'uri' => "$site/", 'singular' => true, 'snapshot' => 'home-root.html' ),
	'single-post' => array( 'query' => array( 'p' => 996336 ), 'uri' => "$site/why-ai-native-companies-are-outperforming-traditional-software-businesses/", 'singular' => true, 'snapshot' => 'why-ai-native-companies-are-outperforming-traditional-software-businesses.html' ),
	'cat-erp'     => array( 'query' => array( 'category_name' => 'erp' ), 'uri' => "$site/category/erp/", 'singular' => false, 'snapshot' => 'category-erp.html' ),
	'search'      => array( 'query' => array( 's' => 'software' ), 'uri' => "$site/?s=software", 'singular' => false, 'qs' => 's=software', 'get' => array( 's' => 'software' ), 'snapshot' => 's-software.html' ),
);

/**
 * doc id => the context(s) each of its instances is rendered in.
 *
 * The three library documents are unreferenced templates (nothing on the site
 * points at them), so they have no context of their own. 996210 is a copy of
 * the home-page widget and 996219 / 996222 are byte-for-byte copies of the
 * three widgets on page 93, so they are rendered where their originals live.
 * That is enough to compare markup; it is not a claim that they render there.
 */
$doc_contexts = array(
	93     => array( 'blogs', 'blogs-p2' ),
	146    => array( 'home' ),
	8502   => array( 'single-post' ),
	8559   => array( 'cat-erp' ),
	8711   => array( 'search' ),
	6126   => array( 'cat-erp' ),   // its real condition (cat 22 / tags 46,47) does not exist — see the report
	996210 => array( 'home' ),
	996219 => array( 'blogs' ),
	996222 => array( 'blogs' ),
);

/* ------------------------------------------------------------------ helpers */

function walk_elements( array $els, callable $cb ): void {
	foreach ( $els as $el ) {
		if ( ( $el['elType'] ?? '' ) === 'widget' && in_array( $el['widgetType'] ?? '', array( 'posts', 'archive-posts' ), true ) ) {
			$cb( $el );
		}
		if ( ! empty( $el['elements'] ) ) {
			walk_elements( $el['elements'], $cb );
		}
	}
}

function apply_context( array $ctx ): void {
	global $wp_query, $wp_the_query, $post, $authordata, $pages, $page, $numpages, $multipage, $more;

	$_SERVER['REQUEST_URI']  = $ctx['uri'];
	$_SERVER['QUERY_STRING'] = $ctx['qs'] ?? '';
	$_GET                    = $ctx['get'] ?? array();
	unset( $_SERVER['HTTP_REFERER'] );

	$q = new WP_Query();
	$q->query( $ctx['query'] );

	$wp_query     = $q;
	$wp_the_query = $q;

	// Reset the loop-state globals the_post() writes, so a previous context
	// cannot leak into this one.
	$post = null;
	$page = 1;
	$more = 0;

	if ( ! empty( $ctx['singular'] ) && $q->have_posts() ) {
		$q->the_post();
	}

	// paged/page are read straight off the query by get_current_page().
	if ( isset( $ctx['query']['page'] ) ) {
		$q->set( 'page', $ctx['query']['page'] );
	}
}

function render_instance( array $el, array $ctx ): string {
	apply_context( $ctx );

	$widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $el );

	if ( ! $widget ) {
		return '<<create_element_instance returned null>>';
	}

	ob_start();
	try {
		$widget->render_content();
	} catch ( \Throwable $e ) {
		ob_end_clean();
		return '<<THREW: ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . '>>';
	}
	$out = ob_get_clean();

	wp_reset_postdata();

	return $out;
}

function unified_diff( string $a, string $b, int $context = 2 ): string {
	$al = explode( "\n", $a );
	$bl = explode( "\n", $b );
	$max = max( count( $al ), count( $bl ) );
	$out = array();
	$shown = 0;
	for ( $i = 0; $i < $max; $i++ ) {
		$x = $al[ $i ] ?? '<<eof>>';
		$y = $bl[ $i ] ?? '<<eof>>';
		if ( $x === $y ) {
			continue;
		}
		$out[] = sprintf( '  line %d', $i + 1 );
		$out[] = sprintf( '    ref: %s', vis( $x ) );
		$out[] = sprintf( '    our: %s', vis( $y ) );
		if ( ++$shown >= 25 ) {
			$out[] = '  … (truncated)';
			break;
		}
	}
	return implode( "\n", $out );
}

function vis( string $s ): string {
	$s = str_replace( array( "\t", "\r" ), array( '»', '\\r' ), $s );
	return strlen( $s ) > 220 ? substr( $s, 0, 220 ) . '…' : $s;
}

/** Structural fingerprint used for the snapshot sanity check. */
function fingerprint( string $html ): array {
	preg_match_all( '/class="([^"]*elementor-posts-container[^"]*)"/', $html, $m );

	/*
	 * Only the widget's own items. A theme can and does emit its own <article>
	 * with a post-<ID> class nearby — on a single post the tecnologia theme
	 * wraps the post in `single-post-wrapper full post-<ID>` — and counting
	 * those would make an honest render look wrong.
	 */
	preg_match_all( '/<article class="elementor-post elementor-grid-item post-(\d+)\b/', $html, $ids );

	return array(
		'articles'  => count( $ids[1] ),
		'container' => $m[1][0] ?? '(none)',
		'post_ids'  => array_values( array_unique( $ids[1] ) ),
		'metadata_outside_text' => (bool) preg_match( '/<\/a>\s*<div class="elementor-post__meta-data">/', $html ),
		'bytes'     => strlen( $html ),
	);
}

/* --------------------------------------------------------- collect instances */

global $wpdb;
$rows = $wpdb->get_results(
	"SELECT p.ID, pm.meta_value AS data
	   FROM {$wpdb->postmeta} pm
	   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	  WHERE pm.meta_key = '_elementor_data'
	    AND p.post_type <> 'revision'
	    AND p.post_status IN ('publish','draft','private')
	  ORDER BY p.ID"
);

$jobs = array();
foreach ( $rows as $r ) {
	$data = json_decode( $r->data, true );
	if ( ! is_array( $data ) ) {
		continue;
	}
	$doc = (int) $r->ID;
	walk_elements(
		$data,
		function ( $el ) use ( $doc, &$jobs, $doc_contexts ) {
			foreach ( $doc_contexts[ $doc ] ?? array( 'blogs' ) as $ctx_name ) {
				$jobs[] = array(
					'doc'   => $doc,
					'el'    => $el['id'],
					'wtype' => $el['widgetType'],
					'ctx'   => $ctx_name,
					'data'  => $el,
				);
			}
		}
	);
}

printf( "%d render jobs across %d instances\n\n", count( $jobs ), count( array_unique( array_column( $jobs, 'el' ) ) ) );

/* ---------------------------------------------------------- reference render */

echo "--- rendering the LIVE implementation (Pro + VamTam) ---\n";
$reference = array();
foreach ( $jobs as $i => $job ) {
	$ctx = $contexts[ $job['ctx'] ];
	$a   = render_instance( $job['data'], $ctx );
	$b   = render_instance( $job['data'], $ctx );   // self-stability probe
	$reference[ $i ] = array( 'a' => $a, 'b' => $b );
	printf( "  %-6d %-10s %-12s %7d bytes  %s\n", $job['doc'], $job['el'], $job['ctx'], strlen( $a ), $a === $b ? 'stable' : '*** UNSTABLE ***' );
}

/* ------------------------------------------------------------- the takeover */

echo "\n--- swapping in piecyfer-core ---\n";
foreach ( array(
	'posts'         => \PieCyfer\Core\Widgets\PostsWidget::class,
	'archive-posts' => \PieCyfer\Core\Widgets\ArchivePostsWidget::class,
) as $name => $class ) {
	$inst = new $class();
	\Elementor\Plugin::$instance->widgets_manager->register( $inst );
	\Elementor\Plugin::$instance->controls_manager->delete_stack( $inst );
	printf(
		"  %-14s -> %s   skins: %s\n",
		$name,
		get_class( \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $name ) ),
		implode( ', ', array_keys( \Elementor\Plugin::$instance->skins_manager->get_skins( $inst ) ) )
	);
}

/* ----------------------------------------------------------------- our render */

echo "\n--- rendering piecyfer-core ---\n";
$ours = array();
foreach ( $jobs as $i => $job ) {
	$ours[ $i ] = render_instance( $job['data'], $contexts[ $job['ctx'] ] );
	printf( "  %-6d %-10s %-12s %7d bytes\n", $job['doc'], $job['el'], $job['ctx'], strlen( $ours[ $i ] ) );
}

/* ---------------------------------------------------------------- comparison */

echo "\n" . str_repeat( '=', 78 ) . "\nRESULTS\n" . str_repeat( '=', 78 ) . "\n";

$pass = 0;
$fail = 0;
$unstable = 0;

foreach ( $jobs as $i => $job ) {
	$ref = $reference[ $i ]['a'];
	$our = $ours[ $i ];
	$key = sprintf( '%d/%s@%s', $job['doc'], $job['el'], $job['ctx'] );

	if ( $dump_dir ) {
		$safe = str_replace( array( '/', '@' ), array( '-', '_' ), $key );
		file_put_contents( "$dump_dir/$safe.ref.html", $ref );
		file_put_contents( "$dump_dir/$safe.our.html", $our );
	}

	if ( $reference[ $i ]['a'] !== $reference[ $i ]['b'] ) {
		$unstable++;
		printf( "UNSTABLE  %-26s %-14s the reference is not reproducible; comparison meaningless\n", $key, $job['wtype'] );
		echo unified_diff( $reference[ $i ]['a'], $reference[ $i ]['b'] ) . "\n";
		continue;
	}

	if ( $ref === $our ) {
		$pass++;
		$fp = fingerprint( $ref );
		printf(
			"IDENTICAL %-26s %-14s %6d bytes  %d article(s)  meta-outside-text=%s\n",
			$key,
			$job['wtype'],
			strlen( $ref ),
			$fp['articles'],
			$fp['metadata_outside_text'] ? 'yes' : 'n/a'
		);
		continue;
	}

	$fail++;
	printf( "DIFFERS   %-26s %-14s ref=%d bytes our=%d bytes\n", $key, $job['wtype'], strlen( $ref ), strlen( $our ) );
	echo unified_diff( $ref, $our ) . "\n";
}

/* ------------------------------------------- context fidelity vs real capture */

echo "\n" . str_repeat( '=', 78 ) . "\nCONTEXT FIDELITY (reference render vs the real captured page)\n" . str_repeat( '=', 78 ) . "\n";

$snap_dir = 'C:/xampp/htdocs/piecyfer/_project/snapshots/ref2-a/html';
$seen_ctx = array();
foreach ( $jobs as $i => $job ) {
	$ctx = $contexts[ $job['ctx'] ];
	if ( empty( $ctx['snapshot'] ) || ! is_file( "$snap_dir/{$ctx['snapshot']}" ) ) {
		continue;
	}
	$html = file_get_contents( "$snap_dir/{$ctx['snapshot']}" );

	// Pull just this widget's subtree out of the capture by data-id.
	$pos = strpos( $html, 'data-id="' . $job['el'] . '"' );
	if ( false === $pos ) {
		continue;
	}
	$start = strpos( $html, '<div class="elementor-widget-container">', $pos );
	if ( false === $start ) {
		continue;
	}

	/*
	 * Bound the slice at the NEXT element's data-id. Articles carry no data-id
	 * of their own, so the next occurrence is always the following Elementor
	 * element — without this the slice runs on into the neighbouring posts
	 * widget and inflates the article count.
	 */
	$next  = strpos( $html, 'data-id="', $pos + 10 );
	$slice = false === $next ? substr( $html, $start ) : substr( $html, $start, $next - $start );

	$fp_live = fingerprint( $slice );
	$fp_ref  = fingerprint( $reference[ $i ]['a'] );

	$ok_articles  = $fp_live['articles'] === $fp_ref['articles'];
	$ok_container = str_replace( ' elementor-has-item-ratio', '', $fp_live['container'] ) === $fp_ref['container'];
	$ok_ids       = $fp_live['post_ids'] === $fp_ref['post_ids'];

	printf(
		"  %-26s articles live=%-2d ref=%-2d %s | container %s | post ids %s\n",
		sprintf( '%d/%s@%s', $job['doc'], $job['el'], $job['ctx'] ),
		$fp_live['articles'],
		$fp_ref['articles'],
		$ok_articles ? 'ok' : 'MISMATCH',
		$ok_container ? 'ok' : 'MISMATCH: live=' . $fp_live['container'] . ' ref=' . $fp_ref['container'],
		$ok_ids ? 'ok' : 'MISMATCH live=[' . implode( ',', $fp_live['post_ids'] ) . '] ref=[' . implode( ',', $fp_ref['post_ids'] ) . ']'
	);
	$seen_ctx[ $job['ctx'] ] = true;
}

if ( ! $seen_ctx ) {
	echo "  (no capture matched — the fidelity check did not run)\n";
}

if ( $show ) {
	foreach ( $jobs as $i => $job ) {
		if ( $job['el'] === $show ) {
			echo "\n--- $show reference ---\n" . $reference[ $i ]['a'] . "\n--- $show ours ---\n" . $ours[ $i ] . "\n";
			break;
		}
	}
}

echo "\n" . str_repeat( '=', 78 ) . "\n";
printf( "identical: %d   differing: %d   unstable: %d   (of %d jobs)\n", $pass, $fail, $unstable, count( $jobs ) );
exit( ( $fail || $unstable ) ? 1 : 0 );
