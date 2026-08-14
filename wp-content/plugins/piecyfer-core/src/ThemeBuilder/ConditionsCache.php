<?php
/**
 * The denormalised location => template => conditions option.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/theme-builder/classes/conditions-cache.php`.
 *
 * The option name is Pro's and stays Pro's. That is deliberate: the live option
 * already holds the routing for all ten templates, in an order that decides
 * ties, and rewriting it under a new key would mean regenerating it — which is
 * the one operation that can reorder it. Reading Pro's option verbatim means
 * the cutover changes no data at all.
 *
 * Key order is load-bearing. `asort()` is stable in PHP 8, so two templates that
 * tie on priority are resolved by whichever appears first in this array, and
 * that order came from a `post_date DESC` query at whatever moment Pro last
 * regenerated. regenerate() reproduces the same query so a regeneration is a
 * no-op — but diff the option before and after all the same.
 */
class ConditionsCache {

	public const OPTION_NAME = 'elementor_pro_theme_builder_conditions';

	/**
	 * @var array<string,array<int,string[]>> location => template id => conditions
	 */
	protected array $conditions = array();

	public function __construct() {
		$this->refresh();
	}

	public function refresh(): self {
		$conditions = get_option( self::OPTION_NAME, array() );

		$this->conditions = is_array( $conditions ) ? $conditions : array();

		return $this;
	}

	/**
	 * @param string[] $conditions
	 */
	public function add( object $document, array $conditions ): self {
		$location = $document->get_location();

		if ( $location ) {
			if ( ! isset( $this->conditions[ $location ] ) ) {
				$this->conditions[ $location ] = array();
			}
			$this->conditions[ $location ][ $document->get_main_id() ] = $conditions;
		}

		return $this;
	}

	public function remove( int $post_id ): self {
		$post_id = absint( $post_id );

		foreach ( $this->conditions as $location => $templates ) {
			foreach ( $templates as $id => $template ) {
				if ( $post_id === $id ) {
					unset( $this->conditions[ $location ][ $id ] );
				}
			}
		}

		return $this;
	}

	/**
	 * @param string[] $conditions
	 */
	public function update( object $document, array $conditions ): self {
		return $this->remove( (int) $document->get_main_id() )->add( $document, $conditions );
	}

	public function save(): bool {
		return update_option( self::OPTION_NAME, $this->conditions );
	}

	public function clear(): self {
		$this->conditions = array();

		return $this;
	}

	/**
	 * @return array<int,string[]> template id => condition strings
	 */
	public function get_by_location( string $location ): array {
		return $this->conditions[ $location ] ?? array();
	}

	/**
	 * @return array<string,array<int,string[]>>
	 */
	public function get_all(): array {
		return $this->conditions;
	}

	/**
	 * Rebuild the option from `_elementor_conditions` postmeta.
	 *
	 * Only ever call this from an explicit action (saving conditions, plugin
	 * activation). It is the one code path that can change tie-break order, and
	 * a reordered option can silently swap which of two equally-specific
	 * templates renders.
	 */
	public function regenerate(): self {
		$this->clear();

		$document_types = \Elementor\Plugin::$instance->documents->get_document_types();

		$post_types = array( \Elementor\TemplateLibrary\Source_Local::CPT );

		foreach ( $document_types as $document_type ) {
			if ( $document_type::get_property( 'support_conditions' ) && $document_type::get_property( 'cpt' ) ) {
				$post_types = array_merge( $post_types, $document_type::get_property( 'cpt' ) );
			}
		}

		$query_args = array(
			'posts_per_page' => -1,
			'post_type'      => $post_types,
			'fields'         => 'ids',
			'meta_key'       => '_elementor_conditions', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		);

		/** This filter is documented in elementor-pro/modules/theme-builder/classes/conditions-cache.php */
		$query_args = apply_filters( 'elementor/theme/conditions/cache/regenerate/query_args', $query_args );

		$query = new \WP_Query( $query_args );

		foreach ( $query->posts as $post_id ) {
			$document = Module::instance()->get_document( (int) $post_id );

			if ( $document ) {
				$conditions = $document->get_meta( '_elementor_conditions' );
				$this->add( $document, is_array( $conditions ) ? $conditions : array() );
			}
		}

		$this->save();

		return $this;
	}
}
