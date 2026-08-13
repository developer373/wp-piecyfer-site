<?php
/**
 * Replacement for Elementor Pro's `post-terms` dynamic tag.
 *
 * 1 use, on a plain `heading.title`.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

final class PostTerms extends Tag {

	public function get_name() {
		return 'post-terms';
	}

	public function get_title() {
		return esc_html__( 'Terms', 'piecyfer-core' );
	}

	public function get_group() {
		return 'post';
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$taxonomies = get_taxonomies(
			array( 'show_in_nav_menus' => true ),
			'objects'
		);

		$options = array();
		foreach ( $taxonomies as $slug => $taxonomy ) {
			$options[ $slug ] = $taxonomy->label;
		}

		$this->add_control(
			'taxonomy',
			array(
				'label'   => esc_html__( 'Taxonomy', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $options,
				'default' => 'post_tag',
			)
		);

		$this->add_control(
			'separator',
			array(
				'label'   => esc_html__( 'Separator', 'piecyfer-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => ', ',
			)
		);

		$this->add_control(
			'link',
			array(
				'label'   => esc_html__( 'Link', 'piecyfer-core' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
	}

	public function render() {
		$taxonomy  = (string) $this->get_settings( 'taxonomy' );
		$separator = (string) $this->get_settings( 'separator' );

		if ( '' === $taxonomy ) {
			return;
		}

		if ( 'yes' === $this->get_settings( 'link' ) ) {
			$value = get_the_term_list( get_the_ID(), $taxonomy, '', $separator );
			if ( is_wp_error( $value ) || empty( $value ) ) {
				return;
			}
			echo wp_kses_post( $value );
			return;
		}

		$terms = get_the_terms( get_the_ID(), $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$names = array();
		foreach ( $terms as $term ) {
			$names[] = '<span>' . esc_html( $term->name ) . '</span>';
		}

		echo wp_kses_post( implode( $separator, $names ) );
	}
}
