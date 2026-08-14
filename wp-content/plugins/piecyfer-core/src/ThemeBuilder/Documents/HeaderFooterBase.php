<?php
/**
 * Shared base for the header and footer document types.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\Post;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/header-footer-base.php`.
 *
 * The CSS wrapper selector here is the whole reason document-type registration
 * matters. Header 171's generated stylesheet is written against
 * `.elementor-171`; the unregistered-type fallback would write it against
 * `body.elementor-page-171`, which matches nothing, and the header would render
 * completely unstyled the first time the CSS cache regenerated.
 */
abstract class HeaderFooterBase extends ThemeSectionDocument {

	public function get_css_wrapper_selector() {
		return '.elementor-' . $this->get_main_id();
	}

	protected function register_controls() {
		parent::register_controls();

		// Free-core page style controls (background, padding, ...). Their ids
		// are what the saved page settings key against, so they must be
		// registered or those settings are dropped on the next save and their
		// CSS rules disappear.
		Post::register_style_controls( $this );

		$this->update_control(
			'section_page_style',
			array(
				'label' => esc_html__( 'Style', 'piecyfer-core' ),
			)
		);

		$this->start_injection(
			array(
				'of' => 'margin',
			)
		);

		// A hidden control that exists only to carry two static CSS rules into
		// the generated stylesheet — the clearfix on header/footer locations
		// and the editor's content-area placeholder height. Pro emits these for
		// every header and footer document, so dropping it would change the
		// generated CSS.
		$this->add_control(
			'hidden_header_footer_style_control',
			array(
				'type'      => Controls_Manager::HIDDEN,
				'default'   => 'hidden_control',
				'selectors' => array(
					'.elementor-theme-builder-content-area' => 'height: 400px;',
					'.elementor-location-header:before, .elementor-location-footer:before' => 'content: ""; display: table; clear: both;',
				),
			)
		);

		$this->end_injection();
	}
}
