<?php
/**
 * Plugin bootstrap.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core;

use Elementor\Widgets_Manager;
use Elementor\Elements_Manager;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	/**
	 * Widget classes to register, in the order they should appear.
	 *
	 * Deliberately empty at 0.1.0: the skeleton must be activatable as a no-op
	 * so it can sit alongside Elementor Pro while widgets are built and
	 * verified one at a time. See _project/02-WIDGET-REBUILD-SPEC.md for the
	 * build order.
	 *
	 * @var array<class-string>
	 */
	private const WIDGETS = array(
		Widgets\Template::class,
		Widgets\PostTitleWidget::class,
		Widgets\ArchiveTitleWidget::class,
		Widgets\SiteLogoWidget::class,
		Widgets\PostContentWidget::class,
		Widgets\PostCommentsWidget::class,
		Widgets\BlockquoteWidget::class,
		Widgets\SearchFormWidget::class,
		Widgets\PostInfoWidget::class,
		Widgets\CallToActionWidget::class,
		Widgets\GalleryWidget::class,
		Widgets\TestimonialCarouselWidget::class,
		Widgets\NavMenuWidget::class,
		Widgets\FormWidget::class,
		Widgets\PostsWidget::class,
		Widgets\ArchivePostsWidget::class,
	);

	/**
	 * The widget classes this plugin claims, for the takeover gate.
	 *
	 * Exposed so `scripts/which-implementation.php` can assert that every class
	 * listed here is the one Elementor actually ends up rendering. Reading the
	 * constant reflectively from outside would work too, but it would break
	 * silently the day the constant is renamed — and a silently-skipped gate is
	 * worse than no gate.
	 *
	 * @return string[]
	 */
	public static function widget_classes(): array {
		return self::WIDGETS;
	}

	public static function instance(): Plugin {
		return self::$instance ??= new self();
	}

	private function __construct() {
		/*
		 * Priority 20 — deliberately after Elementor Pro, which registers at the
		 * default 10.
		 *
		 * Widgets_Manager::register() is a plain array assignment keyed on the
		 * widget name, so the last registration for a given name wins with no
		 * conflict and no warning. That lets us replace Pro's widgets one at a
		 * time *while Pro is still installed*: implement one, capture, compare
		 * against the baseline, and either keep it or delete the class and fall
		 * straight back to Pro's version.
		 *
		 * The alternative — remove Pro first, then rebuild 22 widgets against a
		 * broken site — has no working reference to compare against and no way
		 * back. This ordering is what makes the migration reversible at every
		 * single step.
		 */
		/*
		 * Priority 150, not 20.
		 *
		 * 20 was chosen to land after Pro's default 10, which is true but not
		 * sufficient. VamTam's companion plugin registers at **100**, and for six
		 * widget names it does:
		 *
		 *     $widgets_manager->unregister( 'nav-menu' );
		 *     $widgets_manager->register( new Vamtam_Widget_Nav_Menu );
		 *
		 * (`nav-menu`, `posts`, `archive-posts`, `login`, `button`, `tabs` —
		 * includes/widgets/*.php in vamtam-elementor-integration-tecnologia.)
		 * Three of those are ours to replace and are the largest ones left.
		 *
		 * At 20 our replacements would have been unregistered again a moment
		 * later, rendered nothing, and the pixel comparison would still have
		 * passed — because VamTam's widget was drawing the page. A false pass
		 * that would have shipped. `scripts/which-implementation.php` is the
		 * gate that catches this class of failure; run it for every widget.
		 */
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ), 150 );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_categories' ) );

		// Dynamic tags register at priority 20 for the same reason as widgets:
		// after Pro, so ours win while Pro is still installed and each one can
		// be verified against the baseline before anything depends on it.
		DynamicTags\Manager::init();

		// Drop Pro's stylesheet for every widget we have taken over, so a
		// passing comparison means our CSS carries the widget on its own rather
		// than merely coexisting with Pro's.
		ProStyleGuard::init();
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_frontend' ) );

		// Registration must happen on both the front end and in the editor, and
		// before Elementor resolves each widget's get_style_depends().
		add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ), 5 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'register_styles' ), 5 );
		add_action( 'admin_notices', array( $this, 'maybe_warn_untested_elementor' ) );
		add_action( 'admin_init', array( $this, 'maybe_clear_elementor_cache' ), 20 );

		/*
		 * Form back end — src/Forms/.
		 *
		 * Module::init() is a no-op unless the master switch is on:
		 *
		 *     define( 'PIECYFER_FORMS_ENABLED', true );        // wp-config.php
		 * or  add_filter( 'piecyfer/forms/enabled', '__return_true' );
		 *
		 * Even when enabled, it refuses to register the AJAX endpoint while
		 * Elementor Pro's forms module is loaded — two handlers on the same
		 * action would send every submission twice. See Forms\Module for the
		 * full interlock and the cut-over checklist.
		 */
		Forms\Module::init();

		/*
		 * Frontend JS layer — src/Frontend.php.
		 *
		 * Self-gating behind PIECYFER_CORE_FRONTEND_JS (default OFF).
		 * Safe to wire in now; remains dormant until cutover.
		 */
		Frontend::init();

		/*
		 * Theme Builder — src/ThemeBuilder/Module.php.
		 *
		 * Self-gating behind PIECYFER_THEME_BUILDER (default OFF) and
		 * refuses to boot while Elementor Pro is active.
		 */
		ThemeBuilder\Module::boot();

		/*
		 * Popup — src/Popup/Module.php.
		 *
		 * Self-gating behind PIECYFER_POPUP (default OFF) and
		 * refuses to boot while Elementor Pro is active.
		 */
		Popup\Module::boot();
	}

	/**
	 * Clear Elementor's caches when the set of widgets or stylesheets we own
	 * changes.
	 *
	 * Since 3.24 Elementor records the exact list of style and script handles a
	 * page needs in `_elementor_page_assets` postmeta, and enqueues from that
	 * list rather than from the live widgets. Taking a widget over therefore has
	 * no effect on already-cached pages: the header template's cached list still
	 * named Pro's `widget-search-form`, so our stylesheet was never enqueued and
	 * the widget rendered unstyled the moment Pro's stylesheet was suppressed.
	 *
	 * Hooked to admin_init and CLI so it never clears CSS on a visitor front-end
	 * request (which previously caused missing header/footer styles on the request
	 * it fired on).
	 */
	public function maybe_clear_elementor_cache(): void {
		$signature = md5( wp_json_encode( array( self::WIDGETS, self::STYLES, VERSION ) ) );

		if ( get_option( 'piecyfer_core_asset_signature' ) === $signature ) {
			return;
		}

		if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		update_option( 'piecyfer_core_asset_signature', $signature, false );
		$this->log( 'widget/style set changed — cleared Elementor cache' );
	}

	/**
	 * Register every widget.
	 *
	 * Each widget is wrapped individually: a fatal in one replacement widget
	 * must not take down the editor or the front end for all the others. This
	 * is the "fail-safe rendering" rule from the plan — the site degrades to a
	 * missing widget plus a logged error, never a white screen.
	 */
	public function register_widgets( Widgets_Manager $widgets_manager ): void {
		foreach ( self::WIDGETS as $class ) {
			try {
				if ( ! class_exists( $class ) ) {
					throw new \RuntimeException( "widget class not found: {$class}" );
				}
				$widgets_manager->register( new $class() );
			} catch ( \Throwable $e ) {
				$this->log( "failed to register {$class}: " . $e->getMessage() );
			}
		}
	}

	/**
	 * Our widgets reuse the categories the existing content already references,
	 * so saved pages keep finding them in the same panel sections. `pro-elements`
	 * and `general` are Elementor's own; `piecyfer` is ours for anything new.
	 */
	public function register_categories( Elements_Manager $elements_manager ): void {
		$elements_manager->add_category(
			'piecyfer',
			array(
				'title' => esc_html__( 'PieCyfer', 'piecyfer-core' ),
				'icon'  => 'eicon-star',
			)
		);
	}

	/**
	 * Stylesheets, keyed by handle.
	 *
	 * Registered — not enqueued. Each widget names the handles it needs in
	 * get_style_depends(), and Elementor enqueues them only on pages where that
	 * widget actually appears. Elementor Pro ships one stylesheet per widget but
	 * loads them more eagerly; owning this code lets us be strict about it,
	 * which is a page-weight win that costs nothing.
	 *
	 * @var array<string,string> handle => file under assets/css/
	 */
	private const STYLES = array(
		'piecyfer-blockquote'             => 'blockquote.css',
		'piecyfer-search-form'           => 'search-form.css',
		'piecyfer-post-info'             => 'post-info.css',
		'piecyfer-call-to-action'        => 'call-to-action.css',
		'piecyfer-gallery'               => 'gallery.css',
		'piecyfer-testimonial-carousel'  => 'testimonial-carousel.css',
		// Shared by testimonial-carousel and, once rebuilt, the other carousel
		// widgets. Registered separately because Pro ships it as its own handle
		// and more than one widget depends on it.
		'piecyfer-carousel-module-base'  => 'carousel-module-base.css',
		// Also carries the `e--pointer-*` geometry and the hide-scroll keyframe
		// that Pro's gallery filter bar borrows from nav-menu's stylesheet.
		'piecyfer-nav-menu'              => 'nav-menu.css',
		'piecyfer-form'                  => 'form.css',
		'piecyfer-posts'                 => 'posts.css',
	);

	/**
	 * Register stylesheets early enough for get_style_depends() to resolve them.
	 *
	 * Version is the file's mtime rather than the plugin version, so editing a
	 * stylesheet busts the cache during development without a version bump —
	 * and two deploys of the same version never serve stale CSS.
	 */
	public function register_styles(): void {
		foreach ( self::STYLES as $handle => $file ) {
			$path = PIECYFER_CORE_DIR . 'assets/css/' . $file;
			if ( ! file_exists( $path ) ) {
				$this->log( "missing stylesheet: {$file}" );
				continue;
			}
			wp_register_style(
				$handle,
				PIECYFER_CORE_URL . 'assets/css/' . $file,
				array(),
				(string) filemtime( $path )
			);
		}
	}

	/**
	 * Front-end assets.
	 *
	 * Nothing global by design — everything is per-widget via get_style_depends().
	 */
	public function enqueue_frontend(): void {
	}

	/**
	 * Warn once in the admin if Elementor has moved past the version we tested,
	 * so a surprise is caught by us rather than by a visitor.
	 */
	public function maybe_warn_untested_elementor(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}
		if ( version_compare( ELEMENTOR_VERSION, ELEMENTOR_TESTED, '<=' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			sprintf(
				/* translators: 1: installed Elementor version, 2: tested Elementor version */
				esc_html__( 'PieCyfer Core has been tested up to Elementor %2$s; version %1$s is installed. Run the pixel comparison in _project/pixel-tool before trusting this in production.', 'piecyfer-core' ),
				esc_html( ELEMENTOR_VERSION ),
				esc_html( ELEMENTOR_TESTED )
			)
		);
	}

	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-core] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
