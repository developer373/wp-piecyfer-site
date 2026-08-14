<?php
/**
 * Request -> template routing.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder;

use PieCyfer\Core\ThemeBuilder\Conditions\ConditionBase;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/theme-builder/classes/conditions-manager.php`.
 *
 * This class decides which template renders where. Everything else in the
 * Theme Builder replacement is plumbing around its answer, so it is ported
 * line-for-line rather than reimplemented — including the parts that look
 * arbitrary:
 *
 *   - the runtime reads the *option cache*, never `_elementor_conditions`
 *     postmeta, because the option's key order is the tie-breaker;
 *   - excludes are applied after every include has been collected, so an
 *     exclude always beats an include for the same template no matter how
 *     specific the include was;
 *   - `asort()` (stable in PHP 8) picks the winner: lowest number wins;
 *   - templates that are not `publish` are skipped even when their condition
 *     matches.
 *
 * The one worked example to keep in your head: on `/privacy-policy/` the footer
 * location sees 991509 at priority 20 (`include/singular/page/1648`) and 1273 at
 * priority 100 (`include/general`), and 20 wins. If a change to this file makes
 * `/privacy-policy/` render footer 1273, the arithmetic is wrong.
 */
class ConditionsManager {

	/**
	 * @var array<string,ConditionBase>
	 */
	private array $conditions = array();

	private bool $conditions_registered = false;

	private ConditionsCache $cache;

	/**
	 * Per-request memo, keyed by location.
	 *
	 * Both enqueue_styles() (at wp_enqueue_scripts) and do_location() (during
	 * render) resolve the same locations, so without this the whole condition
	 * tree is walked twice per location per request.
	 *
	 * @var array<string,array<int,object>>
	 */
	private array $location_cache = array();

	public function __construct() {
		$this->cache = new ConditionsCache();
	}

	public function get_cache(): ConditionsCache {
		return $this->cache;
	}

	public function clear_cache(): void {
		$this->cache->clear();
	}

	public function clear_location_cache(): void {
		$this->location_cache = array();
	}

	/* ---------------------------------------------------------------------
	 * Condition registry
	 * ------------------------------------------------------------------ */

	public function register_condition_instance( ConditionBase $instance ): void {
		$this->conditions[ $instance->get_name() ] = $instance;

		// Children are created and registered by the parent, so the parent's
		// sub_conditions list is complete before any priority is computed. That
		// count feeds the -5 specificity bonus in condition_priority().
		$instance->register_sub_conditions( $this );
	}

	public function get_condition( string $id ): ?ConditionBase {
		return $this->conditions[ $id ] ?? null;
	}

	/**
	 * @return array<string,ConditionBase>
	 */
	public function get_conditions(): array {
		return $this->conditions;
	}

	/**
	 * Build the condition tree.
	 *
	 * Pro does this on `wp_loaded` (after every plugin has registered its post
	 * types and taxonomies) via a reflective `ucfirst( $id )` class lookup. The
	 * tree is spelled out here instead: it is the same set of names, it cannot
	 * silently resolve to the wrong class, and it can be built on demand from a
	 * CLI script that never reaches `wp_loaded`.
	 */
	public function register_conditions(): void {
		if ( $this->conditions_registered ) {
			return;
		}
		$this->conditions_registered = true;

		// General first, mirroring Pro's recursion order; `archive` and
		// `singular` register their own children as they are added.
		$this->register_condition_instance( new Conditions\General() );
		$this->register_condition_instance( new Conditions\Archive() );
		$this->register_condition_instance( new Conditions\Singular() );

		// Sub-conditions that are named by their parents but constructed here.
		$this->register_condition_instance( new Conditions\Author() );
		$this->register_condition_instance( new Conditions\Date() );
		$this->register_condition_instance( new Conditions\Search() );
		$this->register_condition_instance( new Conditions\FrontPage() );
		$this->register_condition_instance( new Conditions\ChildOf() );
		$this->register_condition_instance( new Conditions\AnyChildOf() );
		$this->register_condition_instance( new Conditions\ByAuthor() );
		$this->register_condition_instance( new Conditions\NotFound404() );

		/** This action is documented in elementor-pro/modules/theme-builder/classes/conditions-manager.php */
		do_action( 'elementor/theme/register_conditions', $this );
	}

	/* ---------------------------------------------------------------------
	 * Resolution
	 * ------------------------------------------------------------------ */

	/**
	 * Resolve a location to `template id => priority`, lowest priority first.
	 *
	 * @return array<int,int>
	 */
	public function get_location_templates( string $location ): array {
		$this->register_conditions();

		$conditions_priority = array();

		$conditions_groups = $this->cache->get_by_location( $location );

		if ( empty( $conditions_groups ) ) {
			return $conditions_priority;
		}

		$excludes = array();

		foreach ( $conditions_groups as $theme_template_id => $conditions ) {
			/** This filter is documented in elementor-pro/modules/theme-builder/classes/conditions-manager.php */
			$theme_template_id = apply_filters( 'elementor/theme/get_location_templates/template_id', $theme_template_id, $location );

			foreach ( (array) $conditions as $condition ) {
				$parsed_condition = $this->parse_condition( (string) $condition );

				$include  = $parsed_condition['type'];
				$name     = $parsed_condition['name'];
				$sub_name = $parsed_condition['sub_name'];
				$sub_id   = $parsed_condition['sub_id'];

				$is_include         = 'include' === $include;
				$condition_instance = $this->get_condition( $name );

				// An unregistered condition name is skipped, not treated as
				// false. For an exclude the two are the same outcome, so this
				// stays safe either way — but it does mean a typo'd or removed
				// condition class fails silently.
				if ( ! $condition_instance ) {
					continue;
				}

				$condition_pass          = $condition_instance->check( array() );
				$sub_condition_instance  = null;

				if ( $condition_pass && $sub_name ) {
					$sub_condition_instance = $this->get_condition( $sub_name );
					if ( ! $sub_condition_instance ) {
						continue;
					}

					$args = array(
						/** This filter is documented in elementor-pro/modules/theme-builder/classes/conditions-manager.php */
						'id' => apply_filters( 'elementor/theme/get_location_templates/condition_sub_id', $sub_id, $parsed_condition ),
					);

					$condition_pass = $sub_condition_instance->check( $args );
				}

				if ( $condition_pass ) {
					// Drafts never route, however specific their condition. The
					// draft `section` document 8519 relies on this: it reaches
					// the page through the [elementor-template] shortcode, not
					// through a location.
					if ( 'publish' !== get_post_status( $theme_template_id ) ) {
						continue;
					}

					if ( $is_include ) {
						$conditions_priority[ $theme_template_id ] = $this->condition_priority( $condition_instance, $sub_condition_instance, $sub_id );
					} else {
						$excludes[] = $theme_template_id;
					}
				}
			}
		}

		// After all includes — an exclude always wins for the same template.
		foreach ( $excludes as $exclude_id ) {
			unset( $conditions_priority[ $exclude_id ] );
		}

		// Ascending: lowest number = most specific = first. Stable since PHP 8,
		// so ties fall back to the option's key order.
		asort( $conditions_priority );

		return $conditions_priority;
	}

	/**
	 * @return array<int,int>
	 */
	public function get_theme_templates_ids( string $location ): array {
		// Editor / WP preview: ?theme_template_id=N forces a template, provided
		// it really belongs to this location.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview switch, mirrors Pro.
		$force_template_id = isset( $_GET['theme_template_id'] ) ? absint( wp_unslash( $_GET['theme_template_id'] ) ) : 0;

		if ( $force_template_id ) {
			$document = Module::instance()->get_document( $force_template_id );

			if ( $document && $location === $document->get_location() ) {
				return array( $force_template_id => 1 );
			}
		}

		// The queried post *is* a template for this location — this is what
		// makes the editor preview render the template being edited.
		$current_post_id = (int) get_the_ID();
		$document        = Module::instance()->get_document( $current_post_id );

		if ( $document && $location === $document->get_location() ) {
			return array( $current_post_id => 1 );
		}

		return $this->get_location_templates( $location );
	}

	/**
	 * @return array<int,object> template id => document
	 */
	public function get_documents_for_location( string $location ): array {
		if ( isset( $this->location_cache[ $location ] ) ) {
			return $this->location_cache[ $location ];
		}

		$theme_templates_ids = $this->get_theme_templates_ids( $location );

		$module            = Module::instance();
		$location_settings = $module->get_locations_manager()->get_location( $location );

		$documents = array();

		foreach ( $theme_templates_ids as $theme_template_id => $priority ) {
			$document = $module->get_document( (int) $theme_template_id );
			if ( $document ) {
				$documents[ $theme_template_id ] = $document;
			}

			// None of the four core locations sets `multiple`, so in practice
			// this breaks on the first entry — the winner of the asort().
			if ( empty( $location_settings['multiple'] ) ) {
				break;
			}
		}

		$this->location_cache[ $location ] = $documents;

		return $documents;
	}

	/* ---------------------------------------------------------------------
	 * Priority arithmetic — the part that must not drift
	 * ------------------------------------------------------------------ */

	/**
	 * Verbatim port of `Conditions_Manager::get_condition_priority()`.
	 *
	 * The magic numbers are Pro's and every one of them is observable on this
	 * site:
	 *
	 *   include/general                    -> 100
	 *   include/archive                    -> 80
	 *   include/archive/search             -> min(80,70)=70, -10, -5 (no subs) = 55
	 *   include/singular/post              -> min(60,40)=40, -10, subs exist    = 30
	 *   include/singular/page/1648         -> min(60,40)=40, -10, sub_id        = 20
	 *   include/singular/not_found404      -> min(60,20)=20, -10, -5 (no subs)  =  5
	 *
	 * @param string $sub_id Raw, possibly empty, string from the condition.
	 */
	private function condition_priority( ConditionBase $condition_instance, ?ConditionBase $sub_condition_instance, string $sub_id ): int {
		$priority = $condition_instance::get_priority();

		if ( $sub_condition_instance ) {
			if ( $sub_condition_instance::get_priority() < $priority ) {
				$priority = $sub_condition_instance::get_priority();
			}

			$priority -= 10;

			if ( $sub_id ) {
				$priority -= 10;
			} elseif ( 0 === count( $sub_condition_instance->get_sub_conditions() ) ) {
				// No sub-conditions of its own means it cannot be narrowed
				// further, so it is inherently more specific.
				$priority -= 5;
			}
		}

		return $priority;
	}

	/**
	 * @return array{type:string,name:string,sub_name:string,sub_id:string}
	 */
	public function parse_condition( string $condition ): array {
		list( $type, $name, $sub_name, $sub_id ) = array_pad( explode( '/', $condition ), 4, '' );

		return compact( 'type', 'name', 'sub_name', 'sub_id' );
	}

	/**
	 * @return array<int,array{type:string,name:string,sub_name:string,sub_id:string}>
	 */
	public function get_document_conditions( object $document ): array {
		$saved_conditions = $document->get_main_meta( '_elementor_conditions' );

		$conditions = array();

		if ( is_array( $saved_conditions ) ) {
			foreach ( $saved_conditions as $condition ) {
				$conditions[] = $this->parse_condition( (string) $condition );
			}
		}

		return $conditions;
	}

	/* ---------------------------------------------------------------------
	 * Cache maintenance
	 * ------------------------------------------------------------------ */

	/**
	 * @param string[]|array<int,string[]> $conditions
	 */
	public function save_conditions( int $post_id, array $conditions ): bool {
		$conditions_to_save = array();

		foreach ( $conditions as $condition ) {
			if ( is_array( $condition ) ) {
				unset( $condition['_id'] );
				$conditions_to_save[] = rtrim( implode( '/', $condition ), '/' );
			} else {
				$conditions_to_save[] = (string) $condition;
			}
		}

		$document = Module::instance()->get_document( $post_id );

		if ( ! $document ) {
			return false;
		}

		if ( empty( $conditions_to_save ) ) {
			$is_saved = $document->delete_meta( '_elementor_conditions' );
		} else {
			$is_saved = $document->update_meta( '_elementor_conditions', $conditions_to_save );
		}

		$this->cache->regenerate();

		return (bool) $is_saved;
	}

	public function purge_post_from_cache( int $post_id ): bool {
		return $this->cache->remove( $post_id )->save();
	}

	public function on_untrash_post( int $post_id ): void {
		$document = Module::instance()->get_document( $post_id );

		if ( ! $document ) {
			return;
		}

		$conditions = $document->get_meta( '_elementor_conditions' );

		if ( $conditions ) {
			$this->cache->add( $document, (array) $conditions )->save();
		}
	}
}
