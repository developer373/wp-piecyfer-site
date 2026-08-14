<?php
/**
 * Popup replacement — module entry point.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Popup;

use Elementor\Core\Base\Document as DocumentBase;
use Elementor\Core\Common\Modules\Ajax\Module as Ajax;
use Elementor\Core\DynamicTags\Manager as TagsManager;
use PieCyfer\Core\ThemeBuilder\Module as ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Replacement for `elementor-pro/modules/popup/module.php`.
 *
 * ============================================================================
 *  THE SWITCH — read this before touching anything else
 * ============================================================================
 *
 * OFF by default. Nothing in `Plugin.php` boots it. Turning it on takes both of:
 *
 *     define( 'PIECYFER_POPUP', true );                 // in wp-config.php
 *     add_filter( 'piecyfer/popup/enabled', '__return_true' );
 *
 * plus a single `\PieCyfer\Core\Popup\Module::boot();` call from the plugin
 * bootstrap. With the switch off, `boot()` returns before registering a hook: no
 * document type, no location, no dynamic tag, no assets. The files sit on disk
 * with zero effect on the running site.
 *
 * It also depends on the Theme Builder replacement being on, because a popup is
 * printed through `elementor_theme_do_location( 'popup' )` and that function only
 * exists once `ThemeBuilder\Module::boot()` has defined it.
 *
 * ============================================================================
 *  THE COUPLING WE MUST NOT REPRODUCE
 * ============================================================================
 *
 * `elementor-pro/modules/popup/module.php:135` declares
 *
 *     public function register_location( Locations_Manager $location_manager )
 *
 * type-hinted on **Pro's own** `ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager`.
 * That single type hint is why our Theme Builder cannot run beside Pro at all:
 * the moment our locations manager fires `elementor/theme/register_locations` and
 * passes itself, PHP throws a TypeError and every front-end request dies.
 *
 * `register_location()` below therefore takes an **untyped** parameter. The
 * method is documented against the surface it actually uses — one call to
 * `register_location()` — rather than against a class. Anything that provides
 * that method works, which is the property Pro gave up for a type hint.
 *
 * ============================================================================
 *
 * What this module owns:
 *   - the `popup` document type (Document)
 *   - the trigger / timing display-settings control stacks (DisplaySettings\*)
 *   - the `popup` theme location and the manual-queue insertion that puts a
 *     popup on a page that has no display conditions
 *   - printing popups at `wp_footer`
 *   - the `popup` dynamic tag, which is what the six button links on this site
 *     resolve to (Tag)
 *   - the ajax action the editor uses to persist trigger / timing settings
 *   - the frontend stylesheet and JavaScript
 */
final class Module {

	/**
	 * Constant that enables the module. Default: undefined, i.e. off.
	 */
	public const ENABLE_CONSTANT = 'PIECYFER_POPUP';

	/**
	 * Filter that enables the module. Default: false.
	 */
	public const ENABLE_FILTER = 'piecyfer/popup/enabled';

	/**
	 * The `_elementor_template_type` value these documents carry.
	 */
	public const DOCUMENT_TYPE = 'popup';

	/**
	 * The theme location popups are printed into.
	 */
	public const LOCATION = 'popup';

	/**
	 * Pro's stylesheet handle, deliberately reused.
	 *
	 * Keeping the handle means the printed `<link id="e-popup-style-css">` keeps
	 * its id across the cutover, so the only difference the pixel harness sees is
	 * the href. It also means anything that dequeues or filters Pro's handle —
	 * ours included, see ProStyleGuard — keeps working without a second name to
	 * learn. There is no collision risk because this module refuses to boot while
	 * Pro is active.
	 */
	public const STYLE_HANDLE = 'e-popup-style';

	public const SCRIPT_HANDLE = 'piecyfer-popup';

	private static ?Module $instance = null;

	private static bool $booted = false;

	/**
	 * Memo for has_popups(): one WP_Query per request at most.
	 */
	private ?bool $has_popups = null;

	/**
	 * Whether the replacement is switched on. Default false.
	 */
	public static function is_enabled(): bool {
		$enabled = defined( self::ENABLE_CONSTANT ) && constant( self::ENABLE_CONSTANT );

		/**
		 * Enable the PieCyfer Popup replacement.
		 *
		 * @param bool $enabled
		 */
		return (bool) apply_filters( self::ENABLE_FILTER, $enabled );
	}

	/**
	 * The only entry point. Safe to call unconditionally.
	 */
	public static function boot(): void {
		if ( self::$booted || ! self::is_enabled() ) {
			return;
		}

		/*
		 * Same hard refusal as ThemeBuilder\Module::boot(), for the same reason
		 * and with the same test.
		 *
		 * ELEMENTOR_PRO_VERSION is checked as well as the class because boot()
		 * runs at `plugins_loaded`, before Pro constructs its modules — the class
		 * alone is still undeclared at that point and would wave the conflict
		 * straight through. The constant is defined the instant Pro's main file is
		 * included, so it is true whenever Pro is active and false the moment it
		 * is deactivated, whether or not the directory is still on disk.
		 *
		 * With Pro active we would also be registering a second `popup` document
		 * type and a second `popup` location on top of Pro's.
		 */
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Modules\Popup\Module', false ) ) {
			self::instance()->log( 'not booting: Elementor Pro is active. Deactivate elementor-pro first.' );
			return;
		}

		/*
		 * A popup reaches the page through `elementor_theme_do_location( 'popup' )`.
		 * Without the Theme Builder replacement that function does not exist, and
		 * print_popups() would fatal on every request at wp_footer. Refusing here
		 * turns a white screen into a logged no-op.
		 */
		if ( ! ThemeBuilder::is_enabled() ) {
			self::instance()->log( 'not booting: the Theme Builder replacement is off, so there is no popup location to print into.' );
			return;
		}

		self::$booted = true;

		$module = self::instance();

		add_action( 'elementor/documents/register', array( $module, 'register_documents' ) );
		add_action( 'elementor/theme/register_locations', array( $module, 'register_location' ) );

		/*
		 * Priority 150, not Pro's 10 — the same trap the widgets hit.
		 *
		 * VamTam's companion plugin registers its own `popup` tag at priority 100
		 * (`vamtam-elementor-integration-tecnologia/includes/dynamic-tags/vamtam-popup.php:150`).
		 * At any priority below that, ours would be registered and then
		 * immediately overwritten, and the page would still render correctly —
		 * because VamTam's tag was doing the work. That is the false pass this
		 * project has already been bitten by twice.
		 *
		 * VamTam's tag does bail out on its own once Pro is deleted (it extends a
		 * Pro base class and guards on `class_exists( 'ElementorPro\Plugin' )`), so
		 * relying on that would work in the end state. Registering above it makes
		 * the takeover explicit and testable instead of incidental.
		 */
		add_action( 'elementor/dynamic_tags/register', array( $module, 'register_tag' ), 150 );

		/*
		 * The editor's only route for persisting triggers and timing.
		 *
		 * They are not document settings, so Elementor's normal document save
		 * never carries them (see DisplaySettings\Base). Pro adds this action and
		 * without it the popup's display settings become read-only: the panel
		 * appears to accept a change and the value is gone on reload.
		 */
		add_action( 'elementor/ajax/register_actions', array( $module, 'register_ajax_actions' ) );

		add_action( 'wp_footer', array( $module, 'print_popups' ) );

		/*
		 * Pro enqueues its popup stylesheet on every front-end page unconditionally
		 * (`elementor-pro/plugin.php:428`, with its own "TODO: Load popup styling
		 * only when needed [ED-16076]" against it). We do the same, and on purpose:
		 * the pixel baseline has that `<link>` on all 39 captured pages, so loading
		 * it conditionally would be a markup diff everywhere and would bury the
		 * diffs that matter. Narrowing it is a Phase 6 change, made once and
		 * measured once.
		 */
		add_action( 'elementor/frontend/after_enqueue_styles', array( $module, 'enqueue_styles' ) );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( $module, 'enqueue_scripts' ) );
	}

	public static function instance(): Module {
		return self::$instance ??= new self();
	}

	/**
	 * Side-effect free by contract: constructing the module registers nothing.
	 */
	private function __construct() {}

	/* ---------------------------------------------------------------------
	 * Registration
	 * ------------------------------------------------------------------ */

	public function register_documents(): void {
		\Elementor\Plugin::$instance->documents->register_document_type(
			self::DOCUMENT_TYPE,
			Document::class
		);
	}

	/**
	 * Register the `popup` location.
	 *
	 * **The parameter is deliberately untyped.** See the class comment: Pro's
	 * equivalent type-hints its own `Locations_Manager` and that is precisely what
	 * makes Pro's popup module impossible to run against any other theme-builder
	 * implementation. All this method needs is an object with a
	 * `register_location( string $location, array $args )` method.
	 *
	 * The args match `elementor-pro/modules/popup/module.php:136-144` exactly:
	 *
	 *   - `multiple => true`  — more than one popup may print on a page, so
	 *     do_location() must not stop after the first.
	 *   - `public => false`   — keeps `popup` out of the `section` document's
	 *     "Location" select, which lists only public locations.
	 *   - `edit_in_content => false` — a popup viewed on its own URL shows the
	 *     "Content Area" placeholder rather than its own content.
	 *
	 * @param object $location_manager Anything exposing register_location().
	 */
	public function register_location( $location_manager ): void {
		if ( ! is_object( $location_manager ) || ! method_exists( $location_manager, 'register_location' ) ) {
			$this->log( 'register_location: the locations manager passed to elementor/theme/register_locations cannot register locations' );
			return;
		}

		$location_manager->register_location(
			self::LOCATION,
			array(
				'label'           => esc_html__( 'Popup', 'piecyfer-core' ),
				'multiple'        => true,
				'public'          => false,
				'edit_in_content' => false,
			)
		);
	}

	public function register_tag( TagsManager $tags_manager ): void {
		try {
			$tags_manager->register( new Tag() );
		} catch ( \Throwable $e ) {
			// One broken tag must not take the page down with it.
			$this->log( 'dynamic tag popup: ' . $e->getMessage() );
		}
	}

	/**
	 * Register the display-settings save action.
	 *
	 * The action name is Pro's, `pro_popup_save_display_settings`, and it has to
	 * stay Pro's: it is the string the editor JavaScript sends. Renaming it to
	 * something PieCyfer-branded would leave the editor calling an action nobody
	 * answers.
	 *
	 * `Ajax` here is Elementor **free** (`elementor/core/common/modules/ajax/module.php`),
	 * not Pro, so this type hint survives the removal of Pro.
	 */
	public function register_ajax_actions( Ajax $ajax ): void {
		$ajax->register_ajax_action( 'pro_popup_save_display_settings', array( $this, 'save_display_settings' ) );
	}

	/**
	 * Persist a popup's trigger and timing settings.
	 *
	 * Port of `elementor-pro/modules/popup/module.php:164-169`. Pro routes the
	 * lookup through its own `Utils::_unstable_get_document_for_edit()`; the two
	 * checks that helper performs — document exists, current user may edit it —
	 * are reimplemented here against Elementor free's API rather than depending on
	 * a Pro class. The capability check is the one that matters: without it this
	 * would be an authenticated write to arbitrary post meta.
	 *
	 * @param array<string,mixed> $data
	 * @throws \Exception When the document is missing or not editable.
	 */
	public function save_display_settings( $data ): void {
		$editor_post_id = isset( $data['editor_post_id'] ) ? (int) $data['editor_post_id'] : 0;

		$document = \Elementor\Plugin::$instance->documents->get( $editor_post_id );

		if ( ! $document ) {
			throw new \Exception( 'Not found.' );
		}

		if ( ! $document->is_editable_by_current_user() ) {
			throw new \Exception( 'Access denied.' );
		}

		if ( ! $document instanceof Document ) {
			throw new \Exception( 'Not a popup.' );
		}

		$document->save_display_settings_data( $data['settings'] ?? array() );
	}

	/* ---------------------------------------------------------------------
	 * The manual queue
	 * ------------------------------------------------------------------ */

	/**
	 * Put a popup into the theme location queue for this request.
	 *
	 * This is the whole mechanism by which popup 7718 reaches a page. It has no
	 * `_elementor_conditions` at all, so the conditions manager never selects it;
	 * it gets there because the `popup` dynamic tag on a button link calls this
	 * while the header is rendering, and `wp_footer` then prints whatever is in
	 * the queue.
	 *
	 * Consequence worth stating plainly, because it is counter-intuitive: the
	 * popup is on every page only because the *header* has a button that links to
	 * it. Remove that button and the popup markup disappears from the page — the
	 * popup is not "site-wide", it is "wherever a link to it is rendered".
	 *
	 * Mirrors `elementor-pro/modules/popup/module.php:124-129`.
	 */
	public static function add_popup_to_location( $popup_id ): void {
		if ( ! self::$booted ) {
			return;
		}

		ThemeBuilder::instance()->get_locations_manager()->add_doc_to_location( self::LOCATION, $popup_id );
	}

	/**
	 * Print every queued popup. Hooked to `wp_footer` at the default priority,
	 * as Pro's is (`module.php:40`, `:147-149`).
	 */
	public function print_popups(): void {
		if ( ! function_exists( 'elementor_theme_do_location' ) ) {
			return;
		}

		elementor_theme_do_location( self::LOCATION );
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	public function enqueue_styles(): void {
		$path = PIECYFER_CORE_DIR . 'assets/popup/popup.css';

		if ( ! file_exists( $path ) ) {
			$this->log( 'missing stylesheet: assets/popup/popup.css' );
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			PIECYFER_CORE_URL . 'assets/popup/popup.css',
			array(),
			// mtime rather than the plugin version, so editing the file busts the
			// cache during development without a version bump.
			(string) filemtime( $path )
		);
	}

	public function enqueue_scripts(): void {
		$path = PIECYFER_CORE_DIR . 'assets/popup/popup.js';

		if ( ! file_exists( $path ) ) {
			$this->log( 'missing script: assets/popup/popup.js' );
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			PIECYFER_CORE_URL . 'assets/popup/popup.js',
			/*
			 * `elementor-dialog` is Elementor **free**
			 * (`elementor/assets/lib/dialog/dialog.min.js`, registered at
			 * `elementor/includes/frontend.php:433`), so depending on it survives
			 * the removal of Pro. The script also copes with the library being
			 * absent at runtime, because Elementor can be configured to load it
			 * dynamically instead — see popup.js `onInit()`.
			 */
			array( 'jquery', 'elementor-frontend', 'elementor-dialog' ),
			(string) filemtime( $path ),
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'PieCyferPopupConfig',
			array(
				/*
				 * Page-view and session counters are only maintained on sites that
				 * actually have a popup, because they write to localStorage on
				 * every page view. Pro gates them the same way through
				 * `ElementorProFrontendConfig.popup.hasPopUps`
				 * (`module.php:266-270`, consumed at `elements-handlers.js:1489`).
				 */
				'hasPopups' => $this->has_popups(),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Whether this site has any popup document at all.
	 *
	 * Port of `elementor-pro/modules/popup/module.php:243-264`. Memoised because
	 * it runs a WP_Query and is asked once per request.
	 */
	public function has_popups(): bool {
		if ( null !== $this->has_popups ) {
			return $this->has_popups;
		}

		$existing = new \WP_Query(
			array(
				'post_type'              => \Elementor\TemplateLibrary\Source_Local::CPT,
				'posts_per_page'         => 1,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => DocumentBase::TYPE_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => self::DOCUMENT_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$this->has_popups = $existing->post_count > 0;

		return $this->has_popups;
	}

	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-core/popup] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
