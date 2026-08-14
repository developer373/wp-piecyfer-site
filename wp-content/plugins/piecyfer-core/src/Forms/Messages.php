<?php
/**
 * The six user-facing form messages and the settings lookup around them.
 *
 * Mirrors ElementorPro\Modules\Forms\Classes\Ajax_Handler's message constants
 * and `get_default_message()` (elementor-pro/modules/forms/classes/ajax-handler.php:25-59)
 * with one deliberate fix — see {@see self::CONTROL_MAP}.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms;

use PieCyfer\Core\Widgets\FormWidget;

defined( 'ABSPATH' ) || exit;

final class Messages {

	public const SUCCESS                    = 'success';
	public const ERROR                      = 'error';
	public const FIELD_REQUIRED             = 'required_field';
	public const INVALID_FORM               = 'invalid_form';
	public const SERVER_ERROR               = 'server_error';
	public const SUBSCRIBER_ALREADY_EXISTS  = 'subscriber_already_exists';

	/**
	 * Message id => the widget control that overrides it.
	 *
	 * DIVERGENCE FROM PRO (documented in 06-FORM-SPEC.md §2.5).
	 *
	 * Pro builds the control id as `<message id> . '_message'`, which yields
	 * `server_error_message` and `invalid_form_message`. Neither control exists —
	 * form.php registers `server_message` (:1030) and `invalid_message` (:1048) —
	 * so in Pro those two overrides are dead and always fall back to the English
	 * defaults. The map below wires them to the controls that actually exist.
	 *
	 * Live impact on this site: **none**. `custom_messages` is empty on all nine
	 * forms, so the gate below never opens and every message is the default. The
	 * fix only becomes visible the day an editor switches custom messages on, and
	 * at that point Pro's behaviour was a bug, not a contract.
	 *
	 * @var array<string,string>
	 */
	private const CONTROL_MAP = array(
		self::SUCCESS                   => 'success_message',
		self::ERROR                     => 'error_message',
		self::FIELD_REQUIRED            => 'required_field_message',
		self::INVALID_FORM              => 'invalid_message',
		self::SERVER_ERROR              => 'server_message',
		self::SUBSCRIBER_ALREADY_EXISTS => 'subscriber_already_exists_message',
	);

	/**
	 * Verbatim from Ajax_Handler::get_default_messages() (ajax-handler.php:37-46).
	 *
	 * Sourced from FormWidget when it is loaded so the two halves of the widget
	 * cannot drift apart; the literal copy is the fallback for the case where the
	 * form back end runs without the widget (CLI, tests).
	 *
	 * @return array<string,string>
	 */
	public static function defaults(): array {
		if ( class_exists( FormWidget::class ) ) {
			return FormWidget::get_default_messages();
		}

		return array(
			self::SUCCESS                   => esc_html__( 'Your submission was successful.', 'piecyfer-core' ),
			self::ERROR                     => esc_html__( 'Your submission failed because of an error.', 'piecyfer-core' ),
			self::FIELD_REQUIRED            => esc_html__( 'This field is required.', 'piecyfer-core' ),
			self::INVALID_FORM              => esc_html__( 'Your submission failed because the form is invalid.', 'piecyfer-core' ),
			self::SERVER_ERROR              => esc_html__( 'Your submission failed because of a server error.', 'piecyfer-core' ),
			self::SUBSCRIBER_ALREADY_EXISTS => esc_html__( 'Subscriber already exists.', 'piecyfer-core' ),
		);
	}

	/**
	 * The message for `$id`, honouring the form's custom-message controls.
	 *
	 * The `custom_messages` gate and the `isset()` test are Pro's, kept exactly:
	 * a control hidden by a failing condition comes back as NULL from
	 * `get_settings_for_display()`, `isset( null )` is false, and the default
	 * wins. That is why visitors see the English defaults today rather than the
	 * `success_message` values saved on all nine forms.
	 *
	 * @param string              $id       One of the class constants.
	 * @param array<string,mixed> $settings The resolved form settings.
	 */
	public static function get( string $id, array $settings ): string {
		if ( ! empty( $settings['custom_messages'] ) ) {
			$control = self::CONTROL_MAP[ $id ] ?? $id . '_message';

			/*
			 * Pro's test is a bare `isset()` (ajax-handler.php:51). The extra
			 * string/empty test here means a custom message an editor left blank
			 * falls back to the English default rather than showing the visitor
			 * nothing at all.
			 */
			if ( isset( $settings[ $control ] ) && is_string( $settings[ $control ] ) && '' !== $settings[ $control ] ) {
				return $settings[ $control ];
			}
		}

		$defaults = self::defaults();

		return $defaults[ $id ] ?? esc_html__( 'Unknown error.', 'piecyfer-core' );
	}
}
