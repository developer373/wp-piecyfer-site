<?php
/**
 * `number` — no live fields on this site, but the bypass it carries is generic.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Fields;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;

defined( 'ABSPATH' ) || exit;

final class NumberField extends FieldBase {

	public function get_type(): string {
		return 'number';
	}

	/**
	 * Min/max bounds. Messages are Pro's (fields/number.php:79-90).
	 *
	 * DIVERGENCE FROM PRO, though not one anybody can see today. Pro reads
	 * `$field['field_max']` off the *record's* field array, and Form_Record only
	 * ever copies `file_*` keys onto upload rows (form-record.php:237-242) — so
	 * in Pro `field_min` and `field_max` are never present and the bounds check
	 * never fires. FormRecord copies both onto number rows, so the controls now
	 * do what the editor panel says they do. Zero number fields exist on this
	 * site, so nothing changes for any live form.
	 *
	 * @param array<string,mixed> $field
	 */
	public function validation( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
		$value = (int) ( $field['value'] ?? 0 );

		if ( ! empty( $field['field_max'] ) && (int) $field['field_max'] < $value ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				sprintf(
					/* translators: %s: the maximum allowed value. */
					esc_html__( 'The field value must be less than or equal to %s.', 'piecyfer-core' ),
					esc_html( (string) $field['field_max'] )
				)
			);
		}

		if ( ! empty( $field['field_min'] ) && (int) $field['field_min'] > $value ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				sprintf(
					/* translators: %s: the minimum allowed value. */
					esc_html__( 'The field value must be greater than or equal to %s.', 'piecyfer-core' ),
					esc_html( (string) $field['field_min'] )
				)
			);
		}
	}

	/**
	 * `intval()`, as Pro does (fields/number.php:92-94).
	 *
	 * This is the sanitiser that opened the required-field bypass: it returns an
	 * int, and Pro's required check compares `'' === $value`, which an int can
	 * never satisfy. The sanitiser is unchanged — the fix is in
	 * FormRecord::is_empty_value(), which looks at the raw submitted value
	 * instead, so it closes the same hole for every non-string sanitiser
	 * including third-party ones.
	 *
	 * @param mixed               $value
	 * @param array<string,mixed> $field
	 */
	public function sanitize_field( $value, $field ) {
		return intval( $value );
	}
}
