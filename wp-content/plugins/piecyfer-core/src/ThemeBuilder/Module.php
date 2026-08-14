<?php
/**
 * Theme Builder replacement — module entry point.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder;

use Elementor\Core\Base\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Replacement for `elementor-pro/modules/theme-builder/module.php`.
 *
 * ============================================================================
 *  THE SWITCH — read this before touching anything else
 * ============================================================================
 *
 * This module is OFF by default and does nothing at all until it is turned on
 * deliberately. Nothing in `Plugin.php` boots it; the only way to activate it is
 * either of:
 *
 *     define( 'PIECYFER_THEME_BUILDER', true );        // in wp-config.php
 *     add_filter( 'piecyfer/theme_builder/enabled', '__return_true' );
 *
 * and then a single call to `\PieCyfer\Core\ThemeBuilder\Module::boot();` from
 * the plugin bootstrap. Both are required. With the switch off, `boot()` returns
 * before registering a single hook, `api.php` is never loaded, and no document
 * type is registered — so the files can sit on disk indefinitely with zero
 * effect on the running site.
 *
 * That caution is not ceremony. Defining `elementor_theme_do_location()` is a
 * one-way trapdoor: `themes/tecnologia/vamtam/classes/elementor-bridge.php:735`
 * guards on `function_exists( 'elementor_theme_do_location' )` and then
 * immediately dereferences `ElementorPro\Modules\ThemeBuilder\Module`. Define
 * the function while Pro is gone and without the compatibility class, and every
 * page and post fatals with "Class not found" — the theme calls that method
 * from `page.php:13`, `page.php:42`, `single.php:17` and `single.php:33`. See
 * boot() for how that is handled, and 04-THEME-BUILDER-SPEC.md §4.3 and §5.6 for
 * the full account.
 *
 * ============================================================================
 *
 * What this module owns, in the order it matters:
 *   - the nine Theme Builder document types (Documents\*)
 *   - request -> template routing (ConditionsManager + Conditions\*)
 *   - printing a location and taking over `template_include` (LocationsManager)
 *   - the `elementor_theme_do_location()` / `elementor_location_exits()` API
 *   - the `[elementor-template]` shortcode, which is Pro's Library module rather
 *     than its Theme Builder, but which a single post on this site depends on
 *
 * What it deliberately does not own: the Theme Builder admin app, the display
 * conditions editor UI, and the "Preview Dynamic Content as" feature. Those are
 * Pro editor bundles; routing becomes a database edit until a replacement
 * conditions screen is built.
 */
final class Module {

	/**
	 * Constant that enables the module. Default: undefined, i.e. off.
	 */
	public const ENABLE_CONSTANT = 'PIECYFER_THEME_BUILDER';

	/**
	 * Filter that enables the module. Default: false.
	 */
	public const ENABLE_FILTER = 'piecyfer/theme_builder/enabled';

	/**
	 * Templates tab group in the admin. Core reads this property name.
	 */
	public const ADMIN_LIBRARY_TAB_GROUP = 'theme';

	/**
	 * The nine document types, keyed by the exact `_elementor_template_type`
	 * string stored in postmeta.
	 *
	 * These strings are the join key between the database and the class that
	 * renders. A type that is not in this map does not error — Elementor falls
	 * back to the `post` document class, whose CSS wrapper selector is wrong for
	 * headers and footers. See Documents\ThemeDocument.
	 *
	 * @var array<string,class-string>
	 */
	private const DOCUMENT_TYPES = array(
		'section'        => Documents\SectionDocument::class,
		'header'         => Documents\HeaderDocument::class,
		'footer'         => Documents\FooterDocument::class,
		'single'         => Documents\SingleDocument::class,
		'single-post'    => Documents\SinglePostDocument::class,
		'single-page'    => Documents\SinglePageDocument::class,
		'archive'        => Documents\ArchiveDocument::class,
		'search-results' => Documents\SearchResultsDocument::class,
		'error-404'      => Documents\Error404Document::class,
	);

	private static ?Module $instance = null;

	private static bool $booted = false;

	private LocationsManager $locations;

	private ConditionsManager $conditions;

	/**
	 * Whether the replacement is switched on.
	 *
	 * Default false, and it takes an explicit act to change that.
	 */
	public static function is_enabled(): bool {
		$enabled = defined( self::ENABLE_CONSTANT ) && constant( self::ENABLE_CONSTANT );

		/**
		 * Enable the PieCyfer Theme Builder replacement.
		 *
		 * @param bool $enabled
		 */
		return (bool) apply_filters( self::ENABLE_FILTER, $enabled );
	}

	/**
	 * The only entry point. Safe to call unconditionally: it is a no-op unless
	 * the switch is on, and it refuses to define the public API functions in the
	 * one configuration where doing so would fatal the site.
	 */
	public static function boot(): void {
		if ( self::$booted || ! self::is_enabled() ) {
			return;
		}

		/*
		 * Hard refusal: this module and Elementor Pro's theme-builder cannot be
		 * live in the same request, and the failure mode is a fatal rather than
		 * a conflict.
		 *
		 * Three Pro modules hook `elementor/theme/register_locations` with a
		 * callback type-hinted on *Pro's* Locations_Manager:
		 *
		 *   modules/popup/module.php:135            register_location( Locations_Manager $m )
		 *   modules/floating-buttons/module.php:100 register_location( Locations_Manager $m )
		 *   modules/custom-code/module.php:346      register_location( Locations_Manager $m )
		 *
		 * Our manager fires that same action and passes itself, so with Pro
		 * active the first listener throws a TypeError and every front-end
		 * request dies. Deactivating Pro is therefore a prerequisite for turning
		 * the switch on, not a follow-up step — and this guard makes forgetting
		 * that a logged no-op instead of a white screen.
		 *
		 * ELEMENTOR_PRO_VERSION is checked as well as the class, because boot()
		 * runs at `plugins_loaded` and Pro does not construct its modules until
		 * later — the class alone would still be undeclared at this point, and
		 * the guard would wave the conflict straight through. The constant is
		 * defined the moment Pro's main plugin file is included, so it is true
		 * whenever Pro is active and false as soon as it is deactivated,
		 * whether or not the directory is still on disk.
		 */
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Modules\ThemeBuilder\Module', false ) ) {
			self::instance()->log( 'not booting: Elementor Pro is active. Deactivate elementor-pro first.' );
			return;
		}

		self::$booted = true;

		$module = self::instance();

		add_action( 'elementor/documents/register', array( $module, 'register_documents' ) );
		add_action( 'wp_loaded', array( $module->get_conditions_manager(), 'register_conditions' ) );

		add_action(
			'wp_trash_post',
			static function ( $post_id ): void {
				self::instance()->get_conditions_manager()->purge_post_from_cache( (int) $post_id );
			}
		);
		add_action(
			'untrashed_post',
			static function ( $post_id ): void {
				self::instance()->get_conditions_manager()->on_untrash_post( (int) $post_id );
			}
		);

		$module->get_locations_manager()->hook();

		/*
		 * `data-elementor-post-type` is on the wrapper of *every* Elementor
		 * document on the site — wp-page, wp-post, elementskit_content, all of
		 * them — because Pro adds it through an unconditional filter. Losing it
		 * is a markup diff on every page, not just on theme templates.
		 */
		add_filter( 'elementor/document/wrapper_attributes', array( $module, 'add_document_attributes' ), 10, 2 );

		Shortcode::register();

		$module->load_public_api();
	}

	/**
	 * Define `elementor_theme_do_location()` — but only once it is safe to.
	 *
	 * The order here is the whole point. The theme dereferences
	 * `ElementorPro\Modules\ThemeBuilder\Module` the moment that function
	 * exists, so the compatibility class has to be declared *first*. If it
	 * cannot be declared for any reason, the functions are not defined either
	 * and the theme keeps taking its own fallback branches — a site with no
	 * Elementor header is recoverable; a fatal on every URL is not.
	 */
	private function load_public_api(): void {
		$pro_module_class = '\ElementorPro\Modules\ThemeBuilder\Module';

		/*
		 * The test is "is Pro's class declared", not "is elementor-pro/ on
		 * disk". Pro only ever declares that class when it is *active*, and
		 * boot() has already refused to get this far if it is — so a deactivated
		 * Pro that has not been deleted yet is a perfectly good state to run in.
		 *
		 * That distinction matters for the cutover: step 6 deactivates Pro and
		 * step 7 deletes it, and between those two steps a disk-presence check
		 * would have left the site with no header, no footer and no explanation.
		 */
		if ( ! class_exists( $pro_module_class, false ) ) {
			require_once __DIR__ . '/compat/elementor-pro-theme-builder-shim.php';
		}

		if ( ! class_exists( $pro_module_class, false ) ) {
			// Neither Pro nor our stand-in is available. Defining the API here
			// would turn every page into a fatal inside the theme.
			$this->log( 'refusing to define elementor_theme_do_location(): no ElementorPro\Modules\ThemeBuilder\Module is available' );
			return;
		}

		if ( ! function_exists( 'elementor_theme_do_location' ) ) {
			require_once __DIR__ . '/api.php';
		}
	}

	public static function instance(): Module {
		return self::$instance ??= new self();
	}

	/**
	 * Side-effect free by contract: constructing the module registers nothing.
	 * The offline routing comparison in _project/scripts/theme-builder-routing.php
	 * relies on that to resolve locations without changing the request.
	 */
	private function __construct() {
		$this->conditions = new ConditionsManager();
		$this->locations  = new LocationsManager();
	}

	public function get_locations_manager(): LocationsManager {
		return $this->locations;
	}

	public function get_conditions_manager(): ConditionsManager {
		return $this->conditions;
	}

	/* ---------------------------------------------------------------------
	 * Documents
	 * ------------------------------------------------------------------ */

	public function register_documents(): void {
		foreach ( self::DOCUMENT_TYPES as $type => $class ) {
			\Elementor\Plugin::$instance->documents->register_document_type( $type, $class );
		}
	}

	/**
	 * @return array<string,class-string>
	 */
	public static function document_types(): array {
		return self::DOCUMENT_TYPES;
	}

	/**
	 * Return the document for a post id, or null if it is not a theme document.
	 *
	 * @return Documents\ThemeDocument|object|null
	 */
	public function get_document( int $post_id ) {
		$document = null;

		try {
			$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		if ( empty( $document ) || ! $this->is_theme_document( $document ) ) {
			return null;
		}

		return $document;
	}

	/**
	 * Transition-aware type test.
	 *
	 * While Elementor Pro is still installed it owns the document type registry,
	 * so `documents->get( 171 )` hands back *Pro's* Header class, not ours. That
	 * is exactly the state the routing comparison runs in, and refusing Pro's
	 * documents there would make the comparison impossible to run at all.
	 *
	 * The second branch is dead the moment `elementor-pro/` is deleted. Leave it
	 * until then, remove it after.
	 */
	private function is_theme_document( object $document ): bool {
		if ( $document instanceof Documents\ThemeDocument ) {
			return true;
		}

		return class_exists( '\ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document', false )
			&& $document instanceof \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document;
	}

	/* ---------------------------------------------------------------------
	 * Helpers shared with the conditions
	 * ------------------------------------------------------------------ */

	/**
	 * Port of `ElementorPro\Core\Utils::get_public_post_types()` plus Pro's
	 * WooCommerce exclusion.
	 *
	 * The filter name is Pro's on purpose: anything that was narrowing the
	 * condition list through it keeps working across the cutover.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,string>
	 */
	public static function get_public_post_types( array $args = array() ): array {
		$post_type_args = array(
			// Default is the value of $public.
			'show_in_nav_menus' => true,
		);

		if ( ! empty( $args['post_type'] ) ) {
			$post_type_args['name'] = $args['post_type'];
			unset( $args['post_type'] );
		}

		$post_type_args = wp_parse_args( $post_type_args, $args );

		$post_types = array();

		foreach ( get_post_types( $post_type_args, 'objects' ) as $post_type => $object ) {
			$post_types[ $post_type ] = $object->label;
		}

		/** This filter is documented in elementor-pro/core/utils.php */
		$post_types = apply_filters( 'elementor_pro/utils/get_public_post_types', $post_types );

		if ( class_exists( 'woocommerce' ) ) {
			unset( $post_types['product'] );
		}

		return $post_types;
	}

	public static function is_preview(): bool {
		return \Elementor\Plugin::$instance->preview->is_preview_mode() || is_preview();
	}

	/**
	 * Add `data-elementor-post-type` to every Elementor document wrapper.
	 *
	 * Site-wide, not theme-only — it is the last attribute on every `.elementor`
	 * wrapper on the site, including plain pages and ElementsKit content. It
	 * lands last because attribute order follows insertion order and this filter
	 * runs after the core array is built.
	 *
	 * @param array<string,mixed> $attributes
	 * @return array<string,mixed>
	 */
	public function add_document_attributes( array $attributes, Document $document ): array {
		$attributes['data-elementor-post-type'] = $document->get_post()->post_type;

		return $attributes;
	}

	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-core/theme-builder] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
