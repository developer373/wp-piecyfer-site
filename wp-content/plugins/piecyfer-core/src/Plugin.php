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
	private const WIDGETS = array();

	public static function instance(): Plugin {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_categories' ) );
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_frontend' ) );
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
	 * Front-end assets.
	 *
	 * Intentionally does nothing yet. When widgets land, each one enqueues its
	 * own CSS/JS only on pages where it is actually present — the conditional
	 * loading Elementor Pro does not do, and one of the free performance wins
	 * of owning this code.
	 */
	public function enqueue_frontend(): void {
		// No global stylesheet by design. See Widgets\AbstractWidget::enqueue_assets().
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
