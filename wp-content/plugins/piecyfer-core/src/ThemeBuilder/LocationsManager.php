<?php
/**
 * Theme locations: registry, rendering, template takeover, asset enqueue.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder;

use Elementor\Core\Base\Elements_Iteration_Actions\Assets;
use Elementor\Core\Files\CSS\Post as Post_CSS;
use Elementor\Modules\PageTemplates\Module as PageTemplatesModule;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/theme-builder/classes/locations-manager.php`.
 *
 * This object is handed to the theme. `themes/tecnologia/vamtam/classes/elementor-bridge.php:715`
 * calls `register_all_core_location()` and `register_location()` on it through
 * the `elementor/theme/register_locations` action, and the parameter is untyped
 * there, so our class can stand in. Every public method Pro exposed is kept even
 * where nothing on this site calls it — a missing method on this object is a
 * fatal inside the theme, not a degraded feature.
 *
 * Two details are worth more than they look:
 *
 *   1. `archive` carries `'overwrite' => true`. That flag is the only reason
 *      `/category/*` and `/?s=` render through Elementor's `header-footer.php`
 *      page template and therefore carry the `elementor-template-full-width`
 *      body class. Drop it and every archive page changes.
 *
 *   2. `location_exits()` answers "is the location registered?", not "does a
 *      template match?", unless asked. `themes/tecnologia/footer.php:18` calls
 *      it with the default and has **no else branch** — if it ever returns
 *      false the site loses its footer entirely, with no error.
 */
class LocationsManager {

	/**
	 * @var array<string,array<string,mixed>>
	 */
	protected array $core_locations = array();

	/**
	 * @var array<string,array<string,mixed>>
	 */
	protected array $locations = array();

	/**
	 * @var string[]
	 */
	protected array $did_locations = array();

	protected ?string $current_location = null;

	protected string $current_page_template = '';

	/**
	 * @var array<string,array<int,int>>
	 */
	protected array $locations_queue = array();

	/**
	 * @var array<string,array<int,int>>
	 */
	protected array $locations_printed = array();

	/**
	 * @var array<string,array<int,int>>
	 */
	protected array $locations_skipped = array();

	public function __construct() {
		// Constructor is deliberately side-effect free: it defines the core
		// location table and nothing else. Every hook lives in hook(), which is
		// only reached once the module switch is on.
		$this->set_core_locations();
	}

	/**
	 * Attach to WordPress. Called only from Module::boot(), only when enabled.
	 */
	public function hook(): void {
		add_filter( 'the_content', array( $this, 'builder_wrapper' ), 9999999 );

		// 11, not 10 — Pro's comment is "after WooCommerce". Keep it.
		add_filter( 'template_include', array( $this, 'template_include' ), 11 );
		add_action( 'template_redirect', array( $this, 'register_locations' ) );

		if ( ! Module::is_preview() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		}

		add_filter( 'pre_handle_404', array( $this, 'should_allow_pagination_on_single_templates' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Registry
	 * ------------------------------------------------------------------ */

	private function set_core_locations(): void {
		$this->core_locations = array(
			'header' => array(
				'is_core'         => true,
				'public'          => false,
				'label'           => esc_html__( 'Header', 'piecyfer-core' ),
				'edit_in_content' => false,
			),
			'footer' => array(
				'is_core'         => true,
				'public'          => false,
				'label'           => esc_html__( 'Footer', 'piecyfer-core' ),
				'edit_in_content' => false,
			),
			'archive' => array(
				'is_core'         => true,
				'public'          => false,
				// See the class comment: this is what routes archives and
				// search through Elementor's full-width page template.
				'overwrite'       => true,
				'label'           => esc_html__( 'Archive', 'piecyfer-core' ),
				'edit_in_content' => true,
			),
			'single' => array(
				'is_core'         => true,
				'public'          => false,
				'label'           => esc_html__( 'Single', 'piecyfer-core' ),
				'edit_in_content' => true,
			),
		);
	}

	/**
	 * Fire `elementor/theme/register_locations` exactly once.
	 *
	 * The guard is on the global action, as Pro's is. Consequence worth knowing
	 * during the transition: if Pro's locations manager and this one are both
	 * hooked to `template_redirect`, whichever runs first fires the action and
	 * the other ends up with an empty location table — and an empty table makes
	 * `location_exits( 'footer' )` false, which deletes the footer. The module
	 * switch exists so the two are never both live.
	 */
	public function register_locations(): void {
		if ( did_action( 'elementor/theme/register_locations' ) ) {
			return;
		}

		/** This action is documented in elementor-pro/modules/theme-builder/classes/locations-manager.php */
		do_action( 'elementor/theme/register_locations', $this );
	}

	/**
	 * @param array<string,mixed> $args
	 */
	public function register_location( $location, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'           => $location,
				'multiple'        => false,
				'public'          => true,
				'edit_in_content' => true,
				'hook'            => 'elementor/theme/' . $location,
			)
		);

		$this->locations[ $location ] = $args;

		add_action(
			$args['hook'],
			function () use ( $location, $args ) {
				$did_location = Module::instance()->get_locations_manager()->do_location( $location );

				if ( $did_location && ! empty( $args['remove_hooks'] ) ) {
					foreach ( $args['remove_hooks'] as $item ) {
						remove_action( $args['hook'], $item );
					}
				}
			},
			5
		);
	}

	/**
	 * @param array<string,mixed> $args
	 */
	public function register_core_location( $location, $args = array() ) {
		if ( ! isset( $this->core_locations[ $location ] ) ) {
			/* translators: %s: Location name. */
			wp_die( esc_html( sprintf( esc_html__( 'Location \'%s\' is not a core location.', 'piecyfer-core' ), $location ) ) );
		}

		$args = array_replace_recursive( $this->core_locations[ $location ], $args );

		$this->register_location( $location, $args );
	}

	public function register_all_core_location() {
		foreach ( $this->core_locations as $location => $settings ) {
			$this->register_location( $location, $settings );
		}
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function get_core_locations() {
		return $this->core_locations;
	}

	/**
	 * @param array<string,mixed>|string $filter_args
	 * @return array<string,mixed>
	 */
	public function get_locations( $filter_args = array() ) {
		$this->register_locations();

		if ( is_string( $filter_args ) ) {
			_deprecated_argument( __FUNCTION__, '2.4.0', 'Passing a location name is deprecated. Use `get_location` instead.' );
			return $this->get_location( $filter_args );
		}

		return wp_list_filter( $this->locations, $filter_args );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_location( $location ) {
		$locations = $this->get_locations();

		return $locations[ $location ] ?? array();
	}

	/**
	 * Whether a location is registered, and optionally whether anything matches.
	 *
	 * The misspelling is part of the contract — `themes/tecnologia/footer.php:18`
	 * calls `elementor_location_exits()`.
	 */
	public function location_exits( $location = '', $check_match = false ) {
		$location_exits = ! ! $this->get_location( $location );

		if ( $location_exits && $check_match ) {
			$location_exits = ! ! Module::instance()->get_conditions_manager()->get_documents_for_location( $location );
		}

		return $location_exits;
	}

	public function get_doc_location( $post_id ) {
		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		return $document ? $document->get_location() : '';
	}

	/* ---------------------------------------------------------------------
	 * Manual queue
	 *
	 * Independent of conditions. The Popup module (work item 4l) is the only
	 * consumer on this site: popup 7718 has no conditions at all and reaches
	 * the page purely through add_doc_to_location().
	 * ------------------------------------------------------------------ */

	public function add_doc_to_location( $location, $document_id ) {
		if ( isset( $this->locations_skipped[ $location ][ $document_id ] ) ) {
			return;
		}

		if ( ! isset( $this->locations_queue[ $location ] ) ) {
			$this->locations_queue[ $location ] = array();
		}

		$this->locations_queue[ $location ][ $document_id ] = $document_id;
	}

	public function remove_doc_from_location( $location, $document_id ) {
		unset( $this->locations_queue[ $location ][ $document_id ] );
	}

	public function skip_doc_in_location( $location, $document_id ) {
		$this->remove_doc_from_location( $location, $document_id );

		if ( ! isset( $this->locations_skipped[ $location ] ) ) {
			$this->locations_skipped[ $location ] = array();
		}

		$this->locations_skipped[ $location ][ $document_id ] = $document_id;
	}

	public function is_printed( $location, $document_id ) {
		return isset( $this->locations_printed[ $location ][ $document_id ] );
	}

	public function set_is_printed( $location, $document_id ) {
		if ( ! isset( $this->locations_printed[ $location ] ) ) {
			$this->locations_printed[ $location ] = array();
		}

		$this->locations_printed[ $location ][ $document_id ] = $document_id;
		$this->remove_doc_from_location( $location, $document_id );
	}

	/**
	 * @return array<int,int>
	 */
	public function get_documents_for_location( string $location ): array {
		return $this->locations_queue[ $location ] ?? array();
	}

	public function did_location( $location ) {
		return in_array( $location, $this->did_locations, true );
	}

	public function get_current_location() {
		return $this->current_location;
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Print a location.
	 *
	 * The return value is the theme's branch condition — `header.php:40` falls
	 * back to its own markup when this returns false — so returning false on an
	 * empty queue rather than printing nothing is behaviour, not an optimisation.
	 */
	public function do_location( $location ) {
		$documents_by_conditions = Module::instance()->get_conditions_manager()->get_documents_for_location( $location );

		foreach ( $documents_by_conditions as $document_id => $document ) {
			$this->add_doc_to_location( $location, $document_id );
		}

		// The queue may also hold manually-added documents (popups).
		if ( empty( $this->locations_queue[ $location ] ) ) {
			return false;
		}

		if ( is_singular() ) {
			$this->set_global_authordata();
		}

		/** This action is documented in elementor-pro/modules/theme-builder/classes/locations-manager.php */
		do_action( "elementor/theme/before_do_{$location}", $this );

		while ( ! empty( $this->locations_queue[ $location ] ) ) {
			$document_id = key( $this->locations_queue[ $location ] );
			$document    = Module::instance()->get_document( (int) $document_id );

			if ( ! $document || $this->is_printed( $location, $document_id ) ) {
				$this->skip_doc_in_location( $location, $document_id );
				continue;
			}

			// A condition-matched document is already known to be published;
			// a manually-queued one is not, so it is checked here.
			if ( empty( $documents_by_conditions[ $document_id ] ) && 'publish' !== get_post_status( $document_id ) ) {
				$this->skip_doc_in_location( $location, $document_id );
				continue;
			}

			// current_location is read by ThemeDocument::get_container_attributes()
			// to emit `elementor-location-{location}` — which is the *location*,
			// not the document type. That is why the 404 document (error-404)
			// carries `elementor-location-single`.
			$this->current_location = $location;
			$document->print_content();
			$this->did_locations[]  = $this->current_location;
			$this->current_location = null;

			$this->set_is_printed( $location, $document_id );
		}

		/** This action is documented in elementor-pro/modules/theme-builder/classes/locations-manager.php */
		do_action( "elementor/theme/after_do_{$location}", $this );

		return true;
	}

	/**
	 * Port of `ElementorPro\Core\Utils::set_global_authordata()`.
	 *
	 * Widgets that print author information read the `$authordata` global, which
	 * WordPress only populates once the loop has run. A theme template prints
	 * before that on some routes.
	 */
	private function set_global_authordata(): void {
		global $authordata;

		if ( ! isset( $authordata->ID ) ) {
			$post = get_post();
			if ( $post ) {
				$authordata = get_userdata( $post->post_author ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Replace a header/footer document's own content with a placeholder when
	 * that document is being viewed directly. Editor nicety; ported for parity.
	 */
	public function builder_wrapper( $content ) {
		$post_id = get_the_ID();

		if ( $post_id ) {
			$document = Module::instance()->get_document( (int) $post_id );

			if ( $document ) {
				$location_settings = $this->get_location( $document->get_location() );

				if ( $location_settings && ! $location_settings['edit_in_content'] ) {
					$content = '<div class="elementor-theme-builder-content-area">' . esc_html__( 'Content Area', 'piecyfer-core' ) . '</div>';
				}
			}
		}

		return $content;
	}

	/* ---------------------------------------------------------------------
	 * Template takeover
	 * ------------------------------------------------------------------ */

	/**
	 * Port of `Locations_Manager::template_include()`.
	 *
	 * On this site the theme registers all four core locations itself, so
	 * `header`, `footer` and `single` come back unchanged — the theme calls
	 * `elementor_theme_do_location()` inline. Only `archive` (which includes
	 * search, tag, author and date) is overwritten, because only `archive` sets
	 * `overwrite => true`. `themes/tecnologia/archive.php:18` is therefore dead
	 * code today.
	 */
	public function template_include( $template ) {
		$location      = '';
		$page_template = '';

		if ( is_singular() ) {
			$document = \Elementor\Plugin::$instance->documents->get_doc_for_frontend( get_the_ID() );

			if ( $document && $document::get_property( 'support_wp_page_templates' ) ) {
				$wp_page_template = $document->get_meta( '_wp_page_template' );

				if ( $wp_page_template && 'default' !== $wp_page_template ) {
					// Record it and get out; filter_page_template_locations()
					// uses it later to drop core locations on canvas pages.
					$this->current_page_template = $wp_page_template;
					return $template;
				}
			}
		} else {
			$document = false;
		}

		if ( $document && $document instanceof Documents\ThemeDocument ) {
			// Editor preview iframe.
			$location = $document->get_location();
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$location = 'archive';
		} elseif ( is_archive() || is_tax() || is_home() || is_search() ) {
			$location = 'archive';
		} elseif ( is_singular() || is_404() ) {
			$location = 'single';
		}

		$location_settings = array();

		if ( $location ) {
			$location_settings  = $this->get_location( $location );
			$location_documents = Module::instance()->get_conditions_manager()->get_documents_for_location( $location );

			if ( empty( $location_documents ) ) {
				return $template;
			}

			if ( 'single' === $location || 'archive' === $location ) {
				$first_key      = key( $location_documents );
				$theme_document = $location_documents[ $first_key ];

				if ( Module::is_preview() && $theme_document->get_autosave_id() ) {
					$theme_document = $theme_document->get_autosave();
				}

				$document_page_template = $theme_document->get_settings( 'page_template' );

				if ( $document_page_template ) {
					$page_template = $document_page_template;
				}
			}
		}

		/** @var PageTemplatesModule $page_templates_module */
		$page_templates_module = \Elementor\Plugin::$instance->modules_manager->get_modules( 'page-templates' );

		$location_exist         = ! empty( $location_settings );
		$is_header_footer       = 'header' === $location || 'footer' === $location;
		$need_override_location = ! empty( $location_settings['overwrite'] ) && ! $is_header_footer;

		/** This filter is documented in elementor-pro/modules/theme-builder/classes/locations-manager.php */
		$need_override_location = apply_filters( 'elementor/theme/need_override_location', $need_override_location, $location, $this );

		if ( $location && empty( $page_template ) && ( ! $location_exist || $need_override_location ) ) {
			$page_template = $page_templates_module::TEMPLATE_HEADER_FOOTER;
		}

		if ( ! empty( $page_template ) ) {
			$template_path = $page_templates_module->get_template_path( $page_template );

			if ( $template_path ) {
				$page_templates_module->set_print_callback(
					function () use ( $location ) {
						Module::instance()->get_locations_manager()->do_location( $location );
					}
				);

				$template = $template_path;
			}
		}

		return $template;
	}

	/**
	 * @param array<string,array<string,mixed>> $locations
	 * @return array<string,array<string,mixed>>
	 */
	private function filter_page_template_locations( array $locations ): array {
		$templates_to_filter = array(
			PageTemplatesModule::TEMPLATE_CANVAS,
			PageTemplatesModule::TEMPLATE_HEADER_FOOTER,
		);

		if ( ! in_array( $this->current_page_template, $templates_to_filter, true ) ) {
			return $locations;
		}

		$allowed_core = PageTemplatesModule::TEMPLATE_CANVAS === $this->current_page_template
			? array()
			: array( 'header', 'footer' );

		foreach ( $locations as $location => $settings ) {
			if ( ! empty( $settings['is_core'] ) && ! in_array( $location, $allowed_core, true ) ) {
				unset( $locations[ $location ] );
			}
		}

		return $locations;
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueue each matched document's generated CSS and opt-in page assets.
	 *
	 * The ordering at the bottom is not incidental: Elementor's frontend styles
	 * go in *first* and the per-document files after, so the per-document rules
	 * win. Reverse it and the stylesheet order in <head> changes, which the
	 * pixel harness will catch and should.
	 */
	public function enqueue_styles(): void {
		$locations = $this->get_locations();

		if ( empty( $locations ) ) {
			return;
		}

		if ( ! empty( $this->current_page_template ) ) {
			$locations = $this->filter_page_template_locations( $locations );
		}

		$current_post_id = get_the_ID();

		/** @var Post_CSS[] $css_files */
		$css_files = array();

		foreach ( $locations as $location => $settings ) {
			$documents = Module::instance()->get_conditions_manager()->get_documents_for_location( (string) $location );

			foreach ( $documents as $document ) {
				$post_id = $document->get_post()->ID;

				// The queried post is handled by Elementor's own frontend
				// component; enqueueing it here would double it up.
				if ( $current_post_id !== $post_id ) {
					$css_files[] = new Post_CSS( $post_id );

					$page_assets = get_post_meta( $post_id, Assets::ASSETS_META_KEY, true );

					if ( ! empty( $page_assets ) ) {
						\Elementor\Plugin::$instance->assets_loader->enable_assets( $page_assets );
					}
				}
			}
		}

		if ( ! empty( $css_files ) ) {
			// Elementor's frontend styles are not otherwise enqueued on pages
			// that are not themselves built with Elementor.
			\Elementor\Plugin::$instance->frontend->enqueue_styles();

			foreach ( $css_files as $css_file ) {
				$css_file->enqueue();
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * WP 5.5 pagination workaround
	 * ------------------------------------------------------------------ */

	/**
	 * Keep `?page=N` on a single template from 404-ing.
	 *
	 * Returning true means "already handled, do not 404". Port of
	 * `Locations_Manager::should_allow_pagination_on_single_templates()`.
	 *
	 * Pro decides which widgets can paginate by class (`instanceof Posts_Base`).
	 * We cannot, until the `posts` / `archive-posts` widgets land in step 4j, so
	 * the widget names are listed instead and the list is filterable. Both Pro
	 * widgets are covered; Pro's `Global_Widget` is not, and a global widget
	 * wrapping a posts widget would 404 where Pro did not. Nothing on this site
	 * uses one — but that is the known gap, not an unknown one.
	 *
	 * @param bool      $handled
	 * @param \WP_Query $wp_query
	 */
	public function should_allow_pagination_on_single_templates( $handled, $wp_query ) {
		if ( $handled || empty( $wp_query->query_vars['page'] ) || empty( $wp_query->post ) ) {
			return $handled;
		}

		$current_post_id = get_the_ID();
		$documents       = Module::instance()->get_conditions_manager()->get_documents_for_location( 'single' );

		if ( empty( $documents ) ) {
			return $handled;
		}

		foreach ( $documents as $document ) {
			$post_id = $document->get_post()->ID;

			// Handled by the posts module's own pre_handle_404 filter.
			if ( $current_post_id === $post_id ) {
				continue;
			}

			$document = \Elementor\Plugin::$instance->documents->get( $post_id );

			if ( $document && $this->is_valid_pagination( $document->get_elements_data(), $wp_query->query_vars['page'] ) ) {
				$handled = true;
			}
		}

		return $handled;
	}

	/**
	 * @param array<int,mixed> $elements
	 */
	private function is_valid_pagination( array $elements, $current_page ): bool {
		$is_valid = false;

		/**
		 * Widget names that can produce pagination inside a theme template.
		 *
		 * @param string[] $names
		 */
		$posts_widgets = apply_filters(
			'piecyfer/theme_builder/pagination_widgets',
			array( 'posts', 'archive-posts', 'loop-grid', 'loop-carousel' )
		);

		\Elementor\Plugin::$instance->db->iterate_data(
			$elements,
			function ( $element ) use ( &$is_valid, $posts_widgets, $current_page ) {
				if ( ! isset( $element['widgetType'] ) || ! in_array( $element['widgetType'], $posts_widgets, true ) ) {
					return;
				}

				$is_valid = $this->should_allow_pagination( $element, $current_page );
			}
		);

		return $is_valid;
	}

	/**
	 * @param array<string,mixed> $element
	 */
	private function should_allow_pagination( array $element, $current_page ): bool {
		if ( empty( $element['settings']['pagination_type'] ) ) {
			return false;
		}

		$using_ajax_pagination = in_array(
			$element['settings']['pagination_type'],
			array( 'load_more_on_click', 'load_more_infinite_scroll' ),
			true
		);

		if ( empty( $element['settings']['pagination_page_limit'] ) || $using_ajax_pagination ) {
			return true;
		}

		return (int) $current_page <= (int) $element['settings']['pagination_page_limit'];
	}
}
