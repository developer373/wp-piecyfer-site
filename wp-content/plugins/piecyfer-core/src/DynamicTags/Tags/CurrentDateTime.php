<?php
/**
 * Replacement for Elementor Pro's `current-date-time` dynamic tag.
 *
 * 2 uses, both on plain `heading.title` widgets.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

final class CurrentDateTime extends Tag {

	public function get_name() {
		return 'current-date-time';
	}

	public function get_title() {
		return esc_html__( 'Current Date Time', 'piecyfer-core' );
	}

	public function get_group() {
		return 'site';
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control(
			'date_format',
			array(
				'label'   => esc_html__( 'Date Format', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'default' => esc_html__( 'Default', 'piecyfer-core' ),
					''        => esc_html__( 'None', 'piecyfer-core' ),
					'F j, Y'  => gmdate( 'F j, Y' ),
					'Y-m-d'   => gmdate( 'Y-m-d' ),
					'm/d/Y'   => gmdate( 'm/d/Y' ),
					'd/m/Y'   => gmdate( 'd/m/Y' ),
					'custom'  => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'default' => 'default',
			)
		);

		$this->add_control(
			'time_format',
			array(
				'label'     => esc_html__( 'Time Format', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'default' => esc_html__( 'Default', 'piecyfer-core' ),
					''        => esc_html__( 'None', 'piecyfer-core' ),
					'g:i a'   => gmdate( 'g:i a' ),
					'g:i A'   => gmdate( 'g:i A' ),
					'H:i'     => gmdate( 'H:i' ),
				),
				'default'   => 'default',
				'condition' => array( 'date_format!' => 'custom' ),
			)
		);

		$this->add_control(
			'custom_format',
			array(
				'label'     => esc_html__( 'Custom Format', 'piecyfer-core' ),
				'default'   => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				'condition' => array( 'date_format' => 'custom' ),
			)
		);
	}

	public function render() {
		$date_format = (string) $this->get_settings( 'date_format' );

		if ( 'custom' === $date_format ) {
			$format = (string) $this->get_settings( 'custom_format' );
		} else {
			$time_format = (string) $this->get_settings( 'time_format' );

			if ( 'default' === $date_format ) {
				$date_format = (string) get_option( 'date_format' );
			}
			if ( 'default' === $time_format ) {
				$time_format = (string) get_option( 'time_format' );
			}

			$format = $date_format;
			if ( '' !== $time_format ) {
				$format .= ( '' !== $format ? ' ' : '' ) . $time_format;
			}
		}

		if ( '' === $format ) {
			return;
		}

		echo wp_kses_post( date_i18n( $format ) );
	}
}
