<?php
/**
 * Base for documents that replace a whole page body.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\Post;
use Elementor\Modules\PageTemplates\Module as PageTemplatesModule;
use Elementor\TemplateLibrary\Source_Local;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/theme-page-document.php`.
 *
 * Single, archive, search-results and error-404 documents descend from here.
 * Their CSS is scoped to a body class rather than to the wrapper, so the body
 * class has to actually be emitted — hence the `body_class` filter that the
 * constructor installs.
 */
abstract class ThemePageDocument extends ThemeDocument {

	/**
	 * Document sub type meta key.
	 */
	public const REMOTE_CATEGORY_META_KEY = '_elementor_template_sub_type';

	public function get_css_wrapper_selector() {
		return 'body.elementor-page-' . $this->get_main_id();
	}

	public static function get_properties() {
		$properties = parent::get_properties();

		$properties['support_wp_page_templates'] = true;

		return $properties;
	}

	/**
	 * Add `elementor-page-{id}` to the body.
	 *
	 * Observed live as `elementor-page-8502` on single posts,
	 * `elementor-page-8559` on category archives and `elementor-page-8711` on
	 * search. Header and footer documents deliberately add none — they are
	 * ThemeSectionDocuments, not ThemePageDocuments.
	 *
	 * @param string[] $body_classes
	 * @return string[]
	 */
	public function filter_body_classes( $body_classes ) {
		// True when we are *editing or previewing* an archive document: the
		// request is technically singular (an elementor_library post) but has to
		// behave like an archive.
		$is_archive_template = 'archive' === Source_Local::get_template_type( get_the_ID() );

		$add_body_class = false;

		if ( $this instanceof ArchiveDocument && ( is_archive() || is_search() || is_home() || $is_archive_template ) ) {
			$add_body_class = true;
		} elseif ( $this instanceof SingleBase && ( is_singular() || is_404() ) && ! $is_archive_template ) {
			$add_body_class = true;
		}

		if ( $add_body_class ) {
			$body_classes[] = 'elementor-page-' . $this->get_main_id();
		}

		return $body_classes;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function __construct( array $data = array() ) {
		// Only documents constructed *with data* are ones that are actually
		// being rendered; the type registry instantiates bare copies to read
		// static properties, and those must not touch body_class.
		if ( $data ) {
			add_filter( 'body_class', array( $this, 'filter_body_classes' ) );
		}

		parent::__construct( $data );
	}

	protected function register_controls() {
		parent::register_controls();

		// `page_template` is read at runtime by
		// LocationsManager::template_include(); a saved value here overrides the
		// location's own template decision.
		$this->start_injection(
			array(
				'of'       => 'post_status',
				'fallback' => array(
					'of' => 'post_title',
				),
			)
		);

		$this->add_control(
			'page_template',
			array(
				'label'   => esc_html__( 'Page Layout', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''                                          => esc_html__( 'Default', 'piecyfer-core' ),
					PageTemplatesModule::TEMPLATE_CANVAS        => esc_html__( 'Elementor Canvas', 'piecyfer-core' ),
					PageTemplatesModule::TEMPLATE_HEADER_FOOTER => esc_html__( 'Elementor Full Width', 'piecyfer-core' ),
				),
			)
		);

		$this->end_injection();

		Post::register_style_controls( $this );
	}
}
