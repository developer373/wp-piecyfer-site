<?php
/**
 * Frontend JavaScript layer — registration and enqueue.
 *
 * NOT WIRED IN. Nothing calls Frontend::init(); Plugin.php is untouched by
 * design. Even once it is called, every asset stays behind the switch described
 * below, which is OFF by default.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS FILE EXISTS
 * ---------------------------------------------------------------------------
 * Every widget in this plugin was ported for markup and `data-settings`, and
 * both are correct. But Elementor Pro's widgets are only half server-side: the
 * other half is a frontend handler that reads that `data-settings` payload and
 * builds the behaviour. Pro ships those handlers in `elements-handlers.js` and
 * a set of lazy-loaded per-widget bundles. This plugin shipped none of them.
 *
 * The result is a site that photographs perfectly and does nothing: the mobile
 * burger is inert, the testimonial carousel is a static column, the gallery has
 * no layout. The pixel harness cannot see any of it. This layer is the missing
 * half.
 *
 * ---------------------------------------------------------------------------
 * THE ENABLE SWITCH
 * ---------------------------------------------------------------------------
 * Default OFF. Three ways to read it, in precedence order:
 *
 *   1. `PIECYFER_CORE_FRONTEND_JS` constant — define in wp-config.php:
 *
 *          define( 'PIECYFER_CORE_FRONTEND_JS', true );
 *
 *   2. `piecyfer_core/frontend_js/enabled` filter — for a mu-plugin, or to
 *      switch it on for a single template while testing:
 *
 *          add_filter( 'piecyfer_core/frontend_js/enabled', '__return_true' );
 *
 *   3. Neither set: OFF.
 *
 * The constant wins when defined, so a filter cannot silently re-enable a layer
 * that wp-config.php has explicitly switched off.
 *
 * Being off means NOTHING is registered and NOTHING is enqueued — not even the
 * `smartmenus` handle. That is deliberate: with Pro still active, Pro registers
 * `smartmenus` and attaches its own handlers, and two sets of handlers on one
 * widget would double-bind every click. Off is genuinely inert.
 *
 * ---------------------------------------------------------------------------
 * TURNING IT ON
 * ---------------------------------------------------------------------------
 *   1. Add `Frontend::init();` to the Plugin constructor, next to
 *      `ProStyleGuard::init();`.
 *   2. Define the constant, or add the filter.
 *   3. Deactivate Elementor Pro — NOT before. See the note on double-binding
 *      in `is_enabled()`.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	/**
	 * Handler scripts, keyed by handle.
	 *
	 * Every one depends on `piecyfer-frontend`, which owns the registry and the
	 * `elementor/frontend/init` bootstrap. Order within this array does not
	 * matter — each file only calls `piecyferFrontend.register()` at parse time
	 * and nothing is built until Elementor fires init.
	 *
	 * @var array<string,string> handle => file under assets/js/handlers/
	 */
	private const HANDLERS = array(
		'piecyfer-handler-nav-menu'             => 'nav-menu.js',
		'piecyfer-handler-testimonial-carousel' => 'testimonial-carousel.js',
		'piecyfer-handler-search-form'          => 'search-form.js',
		'piecyfer-handler-gallery'              => 'gallery.js',
		'piecyfer-handler-form'                 => 'form.js',
	);

	/**
	 * The bootstrap that all handlers depend on.
	 */
	private const CORE_HANDLE = 'piecyfer-frontend';

	/**
	 * Third-party library handle for SmartMenus.
	 *
	 * The handle name is not ours to choose: NavMenuWidget::get_script_depends()
	 * already returns `array( 'smartmenus', 'vamtam-nav-menu' )`, matching what
	 * Elementor Pro registers in elementor-pro/plugin.php. We must claim exactly
	 * that string or the dependency silently resolves to nothing.
	 */
	private const SMARTMENUS_HANDLE = 'smartmenus';

	/**
	 * SmartMenus version. Matches the vendored file at
	 * assets/lib/smartmenus/, which is byte-identical to the copy Pro ships.
	 */
	private const SMARTMENUS_VERSION = '1.2.1';

	public static function init(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		/*
		 * Registration must land before Elementor resolves each widget's
		 * get_script_depends(), which happens during render. Priority 5 on
		 * wp_enqueue_scripts mirrors Plugin::register_styles() for the same
		 * reason.
		 */
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_scripts' ), 5 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( self::class, 'register_scripts' ), 5 );

		/*
		 * Enqueue on every page Elementor's frontend runs on, which is exactly
		 * what Pro does with `pro-elements-handlers`. The alternative — naming
		 * each handler in the matching widget's get_script_depends() — would be
		 * leaner, but those files belong to another agent and this layer is
		 * explicitly forbidden from editing them. See README.md, "Known gaps".
		 */
		add_action( 'elementor/frontend/after_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
	}

	/**
	 * Is the frontend JS layer switched on?
	 *
	 * Default false. A constant, if defined, is authoritative; otherwise the
	 * filter decides.
	 *
	 * A word on why this is not simply always-on: while Elementor Pro is still
	 * active it registers the identical handlers against the identical
	 * `frontend/element_ready/*` hooks. Both sets would run, and every widget
	 * would be initialised twice — two SmartMenus instances on one <ul>, two
	 * click bindings on one burger (so it opens and immediately closes), two
	 * Swiper instances on one container. The switch is what makes it safe for
	 * this code to sit in the tree while Pro is still doing the work.
	 */
	public static function is_enabled(): bool {
		if ( defined( 'PIECYFER_CORE_FRONTEND_JS' ) ) {
			return (bool) constant( 'PIECYFER_CORE_FRONTEND_JS' );
		}

		/**
		 * Filters whether the PieCyfer frontend JavaScript layer loads.
		 *
		 * @param bool $enabled Default false.
		 */
		return (bool) apply_filters( 'piecyfer_core/frontend_js/enabled', false );
	}

	/**
	 * Register — never enqueue — every script this layer owns.
	 */
	public static function register_scripts(): void {
		self::register_smartmenus();

		self::register_script(
			self::CORE_HANDLE,
			'assets/js/piecyfer-frontend.js',
			array( 'jquery', 'elementor-frontend' )
		);

		foreach ( self::HANDLERS as $handle => $file ) {
			self::register_script(
				$handle,
				'assets/js/handlers/' . $file,
				array( 'jquery', 'elementor-frontend', self::CORE_HANDLE )
			);
		}
	}

	/**
	 * Claim the `smartmenus` handle, but only if nothing else has.
	 *
	 * SmartMenus (MIT, v1.2.1) is a third-party library that Elementor FREE does
	 * not ship. Only Elementor Pro does, and NavMenuWidget::get_script_depends()
	 * names Pro's handle. The moment Pro is deactivated the handle stops
	 * existing, `wp_enqueue_script( 'smartmenus' )` becomes a silent no-op, and
	 * every sub-menu in the mobile nav stops opening — with no error anywhere.
	 *
	 * So this plugin vendors the library and registers the same handle. The
	 * `wp_script_is()` check means Pro (or anything else that got there first)
	 * keeps ownership while it is installed, so nothing changes until it is not.
	 * Same defensive shape as NavMenuWidget::register_vamtam_script().
	 */
	private static function register_smartmenus(): void {
		if ( wp_script_is( self::SMARTMENUS_HANDLE, 'registered' ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$file   = 'assets/lib/smartmenus/jquery.smartmenus' . $suffix . '.js';

		if ( ! file_exists( PIECYFER_CORE_DIR . $file ) ) {
			self::log( "missing vendored library: {$file}" );
			return;
		}

		wp_register_script(
			self::SMARTMENUS_HANDLE,
			PIECYFER_CORE_URL . $file,
			array( 'jquery' ),
			self::SMARTMENUS_VERSION,
			true
		);
	}

	/**
	 * Register one of our own scripts.
	 *
	 * Version is the file's mtime rather than the plugin version, so editing a
	 * handler busts the cache during development without a version bump — the
	 * same convention Plugin::register_styles() uses for stylesheets.
	 *
	 * @param string   $handle Script handle.
	 * @param string   $file   Path relative to the plugin directory.
	 * @param string[] $deps   Dependencies.
	 */
	private static function register_script( string $handle, string $file, array $deps ): void {
		$path = PIECYFER_CORE_DIR . $file;

		if ( ! file_exists( $path ) ) {
			self::log( "missing script: {$file}" );
			return;
		}

		wp_register_script(
			$handle,
			PIECYFER_CORE_URL . $file,
			$deps,
			(string) filemtime( $path ),
			true
		);
	}

	/**
	 * Enqueue the bootstrap and every handler.
	 *
	 * Runs on `elementor/frontend/after_enqueue_scripts`, so it only fires where
	 * Elementor's own frontend bundle is already on the page.
	 */
	public static function enqueue_scripts(): void {
		if ( ! wp_script_is( self::CORE_HANDLE, 'registered' ) ) {
			// register_scripts() runs on wp_enqueue_scripts@5, well before this.
			// If the handle is missing, the file was missing — already logged.
			return;
		}

		wp_enqueue_script( self::CORE_HANDLE );

		foreach ( array_keys( self::HANDLERS ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}
	}

	private static function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-core] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
