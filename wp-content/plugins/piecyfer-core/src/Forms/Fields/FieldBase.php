<?php
/**
 * Server-side contract for one form field type.
 *
 * Mirrors ElementorPro\Modules\Forms\Fields\Field_Base minus everything to do
 * with rendering — FormWidget already draws every field type this site uses, and
 * that half is owned by another branch. What is left is validate / process /
 * sanitize, hooked onto the same three Pro hook names so that anything already
 * listening keeps working.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Fields;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;
use PieCyfer\Core\Forms\Hooks;

defined( 'ABSPATH' ) || exit;

abstract class FieldBase {

	abstract public function get_type(): string;

	/**
	 * Hook this field's three callbacks.
	 *
	 * Called only from Module::init(), i.e. only when the form back end is
	 * switched on.
	 *
	 * The hook names are ours, not `elementor_pro/forms/*` — see {@see Hooks} for
	 * why binding to Pro's names directly is a fatal error while Pro is still
	 * installed. Our pipeline fires both sets, so a third-party listener on
	 * either name still runs.
	 */
	public function register(): void {
		$type = $this->get_type();

		add_action( "piecyfer/forms/validation/{$type}", array( $this, 'validation' ), 10, 3 );
		add_action( "piecyfer/forms/process/{$type}", array( $this, 'process_field' ), 10, 3 );
		add_filter( "piecyfer/forms/sanitize/{$type}", array( $this, 'sanitize_field' ), 10, 2 );
	}

	/**
	 * @param array<string,mixed> $field
	 */
	public function validation( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
	}

	/**
	 * @param array<string,mixed> $field
	 */
	public function process_field( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
	}

	/**
	 * Pro's Field_Base default (field-base.php:70-72).
	 *
	 * @param mixed               $value
	 * @param array<string,mixed> $field
	 *
	 * @return mixed
	 */
	public function sanitize_field( $value, $field ) {
		return sanitize_text_field( (string) $value );
	}
}
