<?php
/**
 * `acceptance` — 3 fields on this site, two of them required.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Fields;

defined( 'ABSPATH' ) || exit;

final class AcceptanceField extends FieldBase {

	public function get_type(): string {
		return 'acceptance';
	}

	/**
	 * No `validation()` here, deliberately — Pro has none either
	 * (fields/acceptance.php), and none is needed: an unchecked checkbox is not
	 * submitted at all, so the key is missing, the value is `''`, and the generic
	 * required check in FormRecord::validate() rejects it with the standard
	 * "This field is required." message keyed to the field.
	 *
	 * Worth knowing while reading the two footer forms: their acceptance field
	 * IS required, so an unticked box fails the whole submission. That is
	 * current behaviour and is preserved.
	 *
	 * The value that reaches the email when it IS ticked is the checkbox's `on`
	 * (or whatever the browser sends), sanitised as text — the footer forms mail
	 * a line reading `Check Mark: on`. Also current behaviour.
	 */
}
