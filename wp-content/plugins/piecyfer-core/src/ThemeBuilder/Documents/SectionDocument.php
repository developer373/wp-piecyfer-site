<?php
/**
 * The `section` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

use Elementor\Controls_Manager;
use PieCyfer\Core\ThemeBuilder\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/section.php`.
 *
 * Eleven `elementor_library` posts on this site are of type `section`, which
 * makes this the most-used Pro document type here even though none of them is
 * routed by a display condition. They are pulled in by the `template` widget
 * and by `[elementor-template]`.
 *
 * Its CSS wrapper selector is the inherited empty string — section CSS is not
 * scoped at all — and it takes its location from `_elementor_location` postmeta
 * rather than from a static property.
 */
class SectionDocument extends ThemeSectionDocument {

	public static function get_type() {
		return 'section';
	}

	public static function get_title() {
		return esc_html__( 'Section', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Sections', 'piecyfer-core' );
	}

	public static function get_properties() {
		$properties = parent::get_properties();

		// Sections live under the plain "library" tab, not the theme tab.
		$properties['admin_tab_group']     = 'library';
		$properties['support_site_editor'] = false;

		return $properties;
	}

	protected function register_controls() {
		parent::register_controls();

		$locations_manager = Module::instance()->get_locations_manager();
		$locations_manager->register_locations();

		$locations = $locations_manager->get_locations( array( 'public' => true ) );

		if ( empty( $locations ) ) {
			return;
		}

		$this->start_controls_section(
			'location_settings',
			array(
				'label' => esc_html__( 'Location Settings', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_SETTINGS,
			)
		);

		$options = array( '' => esc_html__( 'Select', 'piecyfer-core' ) );

		foreach ( $locations as $location => $settings ) {
			$options[ $location ] = $settings['label'];
		}

		$this->add_control(
			'location',
			array(
				'label'        => esc_html__( 'Location', 'piecyfer-core' ),
				'label_block'  => true,
				'type'         => Controls_Manager::SELECT,
				'default'      => $this->get_location(),
				'save_default' => true,
				'options'      => $options,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * The location is stored as postmeta, not as a page setting, and changing it
	 * changes routing — so the conditions option has to be rebuilt on save or
	 * the section keeps rendering in its old location until something else
	 * triggers a regenerate.
	 *
	 * @param array<string,mixed> $settings
	 */
	public function save_settings( $settings ) {
		if ( isset( $settings['location'] ) ) {
			if ( empty( $settings['location'] ) ) {
				$this->delete_main_meta( self::LOCATION_META_KEY );
			} else {
				$this->update_main_meta( self::LOCATION_META_KEY, $settings['location'] );
				unset( $settings['location'] );
			}

			Module::instance()->get_conditions_manager()->get_cache()->regenerate();
		}

		parent::save_settings( $settings );
	}
}
