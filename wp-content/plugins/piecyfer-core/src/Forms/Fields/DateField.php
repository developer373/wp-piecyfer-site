<?php
/**
 * `date` — Pro validates it not at all; this does.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Fields;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;

defined( 'ABSPATH' ) || exit;

final class DateField extends FieldBase {

	public function get_type(): string {
		return 'date';
	}

	/**
	 * DIVERGENCE FROM PRO: Pro's Date field class (fields/date.php) has no
	 * `validation()` method at all. The `pattern`, `min` and `max` attributes the
	 * widget prints are the only gate, and they are client-side only.
	 *
	 * The format checked here — `Y-m-d` — is exactly what the widget's own
	 * `pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}"` asks for and what flatpickr
	 * produces, so a submission that passes in the browser passes here. `min_date`
	 * and `max_date` are compared as dates rather than as strings, because the
	 * control stores them in the same ISO order.
	 *
	 * No date field exists on this site today, so nothing live changes.
	 *
	 * @param array<string,mixed> $field
	 */
	public function validation( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
		$value = trim( (string) ( $field['value'] ?? '' ) );

		if ( '' === $value ) {
			return;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				esc_html__( 'The field should be in YYYY-MM-DD format.', 'piecyfer-core' )
			);

			return;
		}

		$min = $this->bound( (string) ( $field['min_date'] ?? '' ) );
		$max = $this->bound( (string) ( $field['max_date'] ?? '' ) );

		if ( $min && $date < $min ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				sprintf(
					/* translators: %s: the earliest allowed date. */
					esc_html__( 'The date must be %s or later.', 'piecyfer-core' ),
					esc_html( $min->format( 'Y-m-d' ) )
				)
			);
		}

		if ( $max && $date > $max ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				sprintf(
					/* translators: %s: the latest allowed date. */
					esc_html__( 'The date must be %s or earlier.', 'piecyfer-core' ),
					esc_html( $max->format( 'Y-m-d' ) )
				)
			);
		}
	}

	/**
	 * The DATE_TIME control stores `Y-m-d` or `Y-m-d H:i`; take the date part.
	 */
	private function bound( string $raw ): ?\DateTimeImmutable {
		$raw = trim( $raw );

		if ( '' === $raw ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', substr( $raw, 0, 10 ) );

		return $date ?: null;
	}
}
