#!/usr/bin/env php
<?php
/**
 * Compare PieCyfer's Theme Builder routing against Elementor Pro's, offline.
 *
 * The pixel harness can only tell us that a page looks the same. It cannot tell
 * us that the *same template* produced it, and two different templates can
 * easily render similar chrome. This script answers the question directly: for
 * a set of representative URLs, which template id does each resolver choose for
 * each location, and with what priority?
 *
 * It changes nothing. The replacement is never enabled, no hook is registered,
 * no option or postmeta is written. It simulates each request in memory, asks
 * both resolvers, and prints a table. Exit code is non-zero on any disagreement,
 * so it can gate a verification run the way which-implementation.php does.
 *
 * Usage:  D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe _project/scripts/theme-builder-routing.php [--verbose]
 *
 * Requires Elementor Pro to still be installed — the whole point is the
 * side-by-side. Once Pro is deleted, run it one last time before removal and
 * keep the output.
 */

define( 'WP_USE_THEMES', false );
require_once 'D:/laragon/www/piecyfer/wp-load.php';

$verbose = in_array( '--verbose', $argv, true );

if ( ! did_action( 'elementor/loaded' ) ) {
	exit( "Elementor is not loaded.\n" );
}

if ( ! class_exists( '\PieCyfer\Core\ThemeBuilder\Module' ) ) {
	exit( "piecyfer-core's ThemeBuilder classes are not autoloadable.\n" );
}

$pro_available = class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' );

/*
 * Sanity: the replacement must still be switched off. If it is not, both
 * resolvers are live at once and the comparison is meaningless — and, worse,
 * three Pro modules type-hint Pro's Locations_Manager on the
 * `elementor/theme/register_locations` action, so a booted replacement would
 * already have fataled the front end.
 */
if ( \PieCyfer\Core\ThemeBuilder\Module::is_enabled() ) {
	exit( "REFUSING TO RUN: the Theme Builder replacement is enabled. This script is for the off state.\n" );
}

$ours = \PieCyfer\Core\ThemeBuilder\Module::instance();
$pro  = $pro_available ? \ElementorPro\Modules\ThemeBuilder\Module::instance() : null;

/*
 * Populate both location registries by hand.
 *
 * `register_locations()` fires the global `elementor/theme/register_locations`
 * action, which can only ever run once per request — so whichever manager calls
 * it first is the only one that gets populated. Worse, while Pro is active that
 * action carries listeners type-hinted on Pro's Locations_Manager, so firing it
 * with our manager is a TypeError. Registering the four core locations directly
 * on each manager sidesteps both problems and is exactly what the theme's own
 * callback does (elementor-bridge.php:717).
 */
$ours->get_locations_manager()->register_all_core_location();
if ( $pro ) {
	$pro->get_locations_manager()->register_all_core_location();
}

/*
 * Now disarm the action itself, in memory, for the rest of this process.
 *
 * get_location() -> get_locations() -> register_locations() will fire
 * `elementor/theme/register_locations` the first time anything asks for a
 * location's settings, and with Pro installed that is an immediate fatal:
 *
 *   TypeError: ElementorPro\Modules\Popup\Module::register_location():
 *   Argument #1 must be of type ...ThemeBuilder\Classes\Locations_Manager,
 *   PieCyfer\Core\ThemeBuilder\LocationsManager given
 *
 * (popup/module.php:135, floating-buttons/module.php:100 and
 * custom-code/module.php:346 all type-hint Pro's class.)
 *
 * Both managers already hold the four core locations from the calls above, and
 * the only listener that adds anything else is the theme's, which adds
 * `page-title-location` — not one of the locations under comparison. Nothing is
 * persisted; the hook registry is rebuilt on the next request.
 *
 * This is also the reason Module::boot() flatly refuses to run while Pro is
 * loaded: there is no safe way for both to be live at once.
 */
remove_all_actions( 'elementor/theme/register_locations' );

const LOCATIONS = array( 'header', 'footer', 'single', 'archive' );

/**
 * The URLs that matter. The pixel baseline covers pages well and archives not at
 * all, so every archive-ish route is here explicitly.
 */
$urls = array(
	'/'                                => 'front page (page 146)',
	'/our-team/'                       => 'ordinary page',
	'/privacy-policy/'                 => 'page 1648 — must choose footer 991509',
	'/terms-conditions/'               => 'page 1646 — must choose footer 991509',
	'/blogs/'                          => 'page 93, NOT the posts page (theme zeroes page_for_posts)',
	'/category/web-apps/'              => 'category archive',
	'/category/erp/'                   => 'category archive',
	'/?s=crm'                          => 'search results',
	'/2026/'                           => 'date archive',
	'/this-url-does-not-exist-404-test/' => '404',
);

// A real single post, looked up rather than hard-coded so the script keeps
// working as content changes.
$latest = get_posts(
	array(
		'posts_per_page' => 1,
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'fields'         => 'ids',
	)
);
if ( $latest ) {
	$permalink                = wp_make_link_relative( get_permalink( $latest[0] ) );
	$urls[ $permalink ]       = 'single post ' . $latest[0];
}

/**
 * Rebuild the WordPress query for a path, so the conditional tags that every
 * condition calls (is_singular, is_404, is_search, is_category, ...) report the
 * truth for that URL.
 */
function pc_simulate_request( string $path ): void {
	global $wp, $wp_query, $wp_the_query, $post, $authordata;

	$parts = wp_parse_url( $path );

	$_GET    = array();
	$_POST   = array();
	$_REQUEST = array();

	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $_GET );
	}

	$_SERVER['REQUEST_URI']    = $path;
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$post       = null;
	$authordata = null;

	$wp_query     = new WP_Query();
	$wp_the_query = $wp_query;
	$wp           = new WP();

	// WP::main() runs init/parse_request/query_posts/handle_404/register_globals.
	// It does not fire template_redirect, so no template is loaded and no
	// location is ever printed.
	$wp->main( '' );
}

/**
 * Clear both resolvers' per-request memo. Without this the first URL's answer
 * would be returned for every subsequent URL.
 */
function pc_reset_memos( $ours, $pro ): void {
	$ours->get_conditions_manager()->clear_location_cache();
	if ( $pro ) {
		$pro->get_conditions_manager()->clear_location_cache();
	}
}

/**
 * Render `id(priority)` pairs, in the resolver's own order.
 *
 * @param array<int,int> $templates
 */
function pc_fmt( array $templates ): string {
	if ( ! $templates ) {
		return '(none)';
	}

	$parts = array();
	foreach ( $templates as $id => $priority ) {
		$parts[] = $id . '(' . $priority . ')';
	}

	return implode( ' ', $parts );
}

/**
 * The winner: the first entry, which is what a location with no `multiple` flag
 * actually prints.
 *
 * @param array<int,int> $templates
 */
function pc_winner( array $templates ) {
	if ( ! $templates ) {
		return '(none)';
	}

	return (string) array_key_first( $templates );
}

echo "PieCyfer Theme Builder — routing comparison\n";
echo str_repeat( '=', 108 ) . "\n";
printf( "  Elementor Pro present: %s\n", $pro_available ? 'yes (side-by-side comparison)' : 'NO — printing our resolution only' );
printf( "  Replacement enabled:   no (as required)\n\n" );

printf( "  %-34s %-9s %-24s %-24s %s\n", 'URL', 'location', 'PieCyfer', 'Elementor Pro', '' );
echo '  ' . str_repeat( '-', 106 ) . "\n";

$mismatches = array();
$rows       = 0;

foreach ( $urls as $path => $note ) {
	pc_simulate_request( $path );
	pc_reset_memos( $ours, $pro );

	$first = true;

	foreach ( LOCATIONS as $location ) {
		$our_templates = $ours->get_conditions_manager()->get_location_templates( $location );
		$pro_templates = $pro ? $pro->get_conditions_manager()->get_location_templates( $location ) : array();

		$our_winner = pc_winner( $our_templates );
		$pro_winner = $pro ? pc_winner( $pro_templates ) : $our_winner;

		$agree = ! $pro || ( $our_templates === $pro_templates );

		if ( ! $agree ) {
			$mismatches[] = sprintf(
				'%s / %s: ours %s vs pro %s',
				$path,
				$location,
				pc_fmt( $our_templates ),
				pc_fmt( $pro_templates )
			);
		}

		$rows++;

		printf(
			"  %-34s %-9s %-24s %-24s %s\n",
			$first ? substr( $path, 0, 34 ) : '',
			$location,
			$verbose ? pc_fmt( $our_templates ) : $our_winner,
			$pro ? ( $verbose ? pc_fmt( $pro_templates ) : $pro_winner ) : 'n/a',
			$agree ? 'ok' : '*** MISMATCH ***'
		);

		$first = false;
	}

	printf( "  %-34s %s\n", '', '(' . $note . ')' );
}

/*
 * Second, weaker but end-to-end check: get_documents_for_location() adds the
 * ?theme_template_id override, the "queried post is itself a template"
 * short-circuit, and the `multiple` truncation on top of the priority map. It
 * is what do_location() actually consumes.
 */
echo "\n  documents_for_location() — the ids do_location() would print\n";
echo '  ' . str_repeat( '-', 106 ) . "\n";

foreach ( $urls as $path => $note ) {
	pc_simulate_request( $path );
	pc_reset_memos( $ours, $pro );

	$our_ids = array();
	$pro_ids = array();

	foreach ( LOCATIONS as $location ) {
		$our_ids[ $location ] = implode( ',', array_keys( $ours->get_conditions_manager()->get_documents_for_location( $location ) ) );
		if ( $pro ) {
			$pro_ids[ $location ] = implode( ',', array_keys( $pro->get_conditions_manager()->get_documents_for_location( $location ) ) );
		}
	}

	$agree = ! $pro || ( $our_ids === $pro_ids );

	if ( ! $agree ) {
		$mismatches[] = sprintf( '%s documents: ours %s vs pro %s', $path, wp_json_encode( $our_ids ), wp_json_encode( $pro_ids ) );
	}

	printf(
		"  %-34s %-34s %-34s %s\n",
		substr( $path, 0, 34 ),
		implode( '/', array_map( fn( $v ) => '' === $v ? '-' : $v, $our_ids ) ),
		$pro ? implode( '/', array_map( fn( $v ) => '' === $v ? '-' : $v, $pro_ids ) ) : 'n/a',
		$agree ? 'ok' : '*** MISMATCH ***'
	);
}
echo "  (order: header/footer/single/archive)\n";

/*
 * Third check: the condition registry itself. A condition name that Pro knows
 * and we do not is skipped rather than failed, so it disappears silently —
 * exactly the class of bug that a routing table which happens to agree today
 * would hide until content changes.
 */
if ( $pro_available ) {
	echo "\n  condition registry\n";
	echo '  ' . str_repeat( '-', 106 ) . "\n";

	$ours->get_conditions_manager()->register_conditions();
	$our_names = array_keys( $ours->get_conditions_manager()->get_conditions() );

	$reflection = new ReflectionClass( $pro->get_conditions_manager() );
	$prop       = $reflection->getProperty( 'conditions' );
	$prop->setAccessible( true );
	$pro_names = array_keys( $prop->getValue( $pro->get_conditions_manager() ) );

	sort( $our_names );
	sort( $pro_names );

	$missing = array_diff( $pro_names, $our_names );
	$extra   = array_diff( $our_names, $pro_names );

	printf( "  ours: %d conditions, pro: %d\n", count( $our_names ), count( $pro_names ) );

	if ( $missing ) {
		$mismatches[] = 'conditions registered by Pro but not by us: ' . implode( ', ', $missing );
		printf( "  MISSING (pro has, we do not): %s\n", implode( ', ', $missing ) );
	}
	if ( $extra ) {
		printf( "  extra (we have, pro does not): %s\n", implode( ', ', $extra ) );
	}
	if ( ! $missing && ! $extra ) {
		echo "  identical\n";
	}
}

echo "\n" . str_repeat( '=', 108 ) . "\n";

if ( $mismatches ) {
	printf( "FAIL — %d disagreement(s):\n", count( $mismatches ) );
	foreach ( $mismatches as $line ) {
		echo '  - ' . $line . "\n";
	}
	exit( 1 );
}

printf( "PASS — %d location resolutions agree with Elementor Pro.\n", $rows );
exit( 0 );
