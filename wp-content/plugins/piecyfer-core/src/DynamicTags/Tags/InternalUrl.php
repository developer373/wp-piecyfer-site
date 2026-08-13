<?php
/**
 * Replacement for Elementor Pro's `internal-url` data tag.
 *
 * 3 uses, all on `button.link`.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

final class InternalUrl extends Data_Tag {

	public function get_name() {
		return 'internal-url';
	}

	public function get_title() {
		return esc_html__( 'Internal URL', 'piecyfer-core' );
	}

	public function get_group() {
		return 'site';
	}

	public function get_categories() {
		return array( Module::URL_CATEGORY );
	}

	public function get_panel_template() {
		return ' ({{ url }})';
	}

	protected function register_controls() {
		$this->add_control(
			'type',
			array(
				'label'   => esc_html__( 'Type', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'post'       => esc_html__( 'Content', 'piecyfer-core' ),
					'taxonomy'   => esc_html__( 'Taxonomy', 'piecyfer-core' ),
					'attachment' => esc_html__( 'Media', 'piecyfer-core' ),
					'author'     => esc_html__( 'Author', 'piecyfer-core' ),
				),
				'default' => 'post',
			)
		);

		/*
		 * Pro uses its QUERY_CONTROL_ID autocomplete for each of these. The id
		 * and the stored value (a numeric id) are what matter for round-tripping
		 * saved data, so a SELECT2 over the same objects is equivalent as far as
		 * the content is concerned.
		 */
		$this->add_control(
			'post_id',
			array(
				'label'       => esc_html__( 'Search & Select', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_post_options(),
				'condition'   => array( 'type' => 'post' ),
			)
		);

		$this->add_control(
			'taxonomy_id',
			array(
				'label'       => esc_html__( 'Search & Select', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_term_options(),
				'condition'   => array( 'type' => 'taxonomy' ),
			)
		);

		$this->add_control(
			'attachment_id',
			array(
				'label'       => esc_html__( 'Search & Select', 'piecyfer-core' ),
				'type'        => Controls_Manager::MEDIA,
				'label_block' => true,
				'condition'   => array( 'type' => 'attachment' ),
			)
		);

		$this->add_control(
			'author_id',
			array(
				'label'       => esc_html__( 'Search & Select', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_author_options(),
				'condition'   => array( 'type' => 'author' ),
			)
		);
	}

	/**
	 * @param array $options Unused; part of the base signature.
	 * @return string
	 */
	public function get_value( array $options = array() ) {
		$type = (string) $this->get_settings( 'type' );
		$url  = '';

		if ( 'post' === $type && $this->get_settings( 'post_id' ) ) {
			$url = get_permalink( (int) $this->get_settings( 'post_id' ) );
		} elseif ( 'taxonomy' === $type && $this->get_settings( 'taxonomy_id' ) ) {
			$url = get_term_link( (int) $this->get_settings( 'taxonomy_id' ) );
		} elseif ( 'attachment' === $type ) {
			$media = $this->get_settings( 'attachment_id' );
			$id    = is_array( $media ) ? (int) ( $media['id'] ?? 0 ) : (int) $media;
			$url   = $id ? get_attachment_link( $id ) : '';
		} elseif ( 'author' === $type && $this->get_settings( 'author_id' ) ) {
			$url = get_author_posts_url( (int) $this->get_settings( 'author_id' ) );
		}

		return ( is_string( $url ) && ! is_wp_error( $url ) ) ? $url : '';
	}

	/** @return array<int,string> */
	private function get_post_options(): array {
		$out   = array();
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ( $posts as $p ) {
			$out[ $p->ID ] = $p->post_title . ' (' . $p->post_type . ')';
		}
		return $out;
	}

	/** @return array<int,string> */
	private function get_term_options(): array {
		$out   = array();
		$terms = get_terms(
			array(
				'taxonomy'   => array_keys( get_taxonomies( array( 'public' => true ) ) ),
				'hide_empty' => false,
				'number'     => 300,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $t ) {
			$out[ $t->term_id ] = $t->name . ' (' . $t->taxonomy . ')';
		}
		return $out;
	}

	/** @return array<int,string> */
	private function get_author_options(): array {
		$out = array();
		foreach ( get_users( array( 'number' => 100 ) ) as $u ) {
			$out[ $u->ID ] = $u->display_name;
		}
		return $out;
	}
}
