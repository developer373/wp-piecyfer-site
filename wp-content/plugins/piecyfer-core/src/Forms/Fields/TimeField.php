<?php
/**
 * `time` — HH:MM, verbatim from Pro (fields/time.php:82-90).
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Fields;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;

defined( 'ABSPATH' ) || exit;

final class TimeField extends FieldBase {

	public function get_type(): string {
		return 'time';
	}

	/**
	 * @param array<string,mixed> $field
	 */
	public function validation( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
		$value = (string) ( $field['value'] ?? '' );

		if ( '' === $value ) {
			return;
		}

		if ( 1 !== preg_match( '/^(([0-1][0-9])|(2[0-3])):[0-5][0-9]$/', $value ) ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				esc_html__( 'The field should be in HH:MM format.', 'piecyfer-core' )
			);
		}
	}
}
