<?php
/**
 * Contract for a submit action.
 *
 * Mirrors ElementorPro\Modules\Forms\Classes\Action_Base minus
 * `register_settings_section()` and `on_export()` — the controls those methods
 * register already exist in FormWidget::submit_actions(), which reproduces them
 * one for one so that no saved value is ever dropped.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Actions;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;

defined( 'ABSPATH' ) || exit;

abstract class ActionBase {

	/**
	 * The string that has to appear in the form's `submit_actions` for this
	 * action to run. It is saved data — never change one.
	 */
	abstract public function get_name(): string;

	/**
	 * Used in admin-only error lines: "Email Some failure message".
	 */
	abstract public function get_label(): string;

	/**
	 * @throws \Exception On failure, which the dispatcher turns into an admin
	 *                    error plus the generic ERROR message for the visitor.
	 */
	abstract public function run( FormRecord $record, AjaxHandler $ajax_handler ): void;
}
