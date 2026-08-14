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
	);

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
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ), 20 );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_categories' ) );

		// Dynamic tags register at priority 20 for the same reason as widgets:
		// after Pro, so ours win while Pro is still installed and each one can
		// be verified against the baseline before anything depends on it.
		DynamicTags\Manager::init();
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_frontend' ) );

		// Registration must happen on both the front end and in the editor, and
		// before Elementor resolves each widget's get_style_depends().
		add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ), 5 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'register_styles' ), 5 );
		add_action( 'admin_notices', array( $this, 'maybe_warn_untested_elementor' ) );
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
		'piecyfer-blockquote' => 'blockquote.css',
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
