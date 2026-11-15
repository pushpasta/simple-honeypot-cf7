<?php
/**
 * Plugin settings facade.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserves the public settings API while delegating to focused classes.
 *
 * Schema and validation live in Settings_Schema, stored settings in
 * Settings_Store, and report counters in Settings_Reports.
 */
final class Settings {

	const SETTINGS_OPTION  = SIMPLE_HONEYPOT_CF7_BASE . '_settings';
	const META_OPTION      = SIMPLE_HONEYPOT_CF7_BASE . '_meta';
	const FORM_META        = '_' . SIMPLE_HONEYPOT_CF7_BASE . '_settings';
	const RULES_SOFT_LIMIT = 10000;

	/**
	 * Create default options when they do not exist.
	 *
	 * @return void
	 */
	public static function activate() {
		Settings_Store::activate();
	}

	/**
	 * Remove all plugin data.
	 *
	 * @return void
	 */
	public static function uninstall() {
		Settings_Store::uninstall();
	}

	/**
	 * Define the global settings schema.
	 *
	 * @return array<string, array{type: string, default: mixed, tab: string, min?: int, max?: int, step?: int}>
	 */
	public static function setting_schema() {
		return Settings_Schema::setting_schema();
	}

	/**
	 * Default global settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return Settings_Schema::default_settings();
	}

	/**
	 * Setting keys accepted on the Settings tab.
	 *
	 * @return string[]
	 */
	public static function settings_tab_keys() {
		return Settings_Schema::settings_tab_keys();
	}

	/**
	 * Setting keys accepted on the Rules tab.
	 *
	 * @return string[]
	 */
	public static function rules_tab_keys() {
		return Settings_Schema::rules_tab_keys();
	}

	/**
	 * Default report counters.
	 *
	 * @return array
	 */
	public static function default_meta() {
		return Settings_Reports::default_meta();
	}

	/**
	 * Get global settings, normalized against the schema.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return Settings_Store::get_settings();
	}

	/**
	 * Save global settings.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	public static function update_settings( array $settings ) {
		Settings_Store::update_settings( $settings );
	}

	/**
	 * Normalize a settings array against the schema.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function normalize_settings( array $settings ) {
		return Settings_Schema::normalize_settings( $settings );
	}

	/**
	 * Re-validate stored settings against the schema.
	 *
	 * @return void
	 */
	public static function normalize_stored_settings() {
		Settings_Store::normalize_stored_settings();
	}

	/**
	 * Get report data merged with defaults.
	 *
	 * @return array
	 */
	public static function get_meta() {
		return Settings_Reports::get_meta();
	}

	/**
	 * Get report data current for the local day.
	 *
	 * @return array
	 */
	public static function get_reports_stats() {
		return Settings_Reports::get_reports_stats();
	}

	/**
	 * Reset all reporting data.
	 *
	 * @return void
	 */
	public static function reset_meta() {
		Settings_Reports::reset_meta();
	}

	/**
	 * Reset Settings-tab values to defaults.
	 *
	 * @return void
	 */
	public static function reset_settings() {
		Settings_Store::reset_settings();
	}

	/**
	 * Get per-form settings.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return array
	 */
	public static function get_form_settings( $form_id ) {
		return Settings_Store::get_form_settings( $form_id );
	}

	/**
	 * Save per-form settings.
	 *
	 * @param int   $form_id  Contact Form 7 form ID.
	 * @param array $settings Settings.
	 * @return void
	 */
	public static function update_form_settings( $form_id, array $settings ) {
		Settings_Store::update_form_settings( $form_id, $settings );
	}

	/**
	 * Check whether timing validation is enabled.
	 *
	 * @param int $form_id Optional Contact Form 7 form ID.
	 * @return bool
	 */
	public static function is_time_check_enabled( $form_id = 0 ) {
		return Settings_Store::is_time_check_enabled( $form_id );
	}

	/**
	 * Get the minimum allowed submission time.
	 *
	 * @param int $form_id Optional Contact Form 7 form ID.
	 * @return int
	 */
	public static function get_min_submission_time( $form_id = 0 ) {
		return Settings_Store::get_min_submission_time( $form_id );
	}

	/**
	 * Get the maximum allowed minimum-submission-time.
	 *
	 * @return int
	 */
	public static function get_max_min_time_seconds() {
		return Settings_Store::get_max_min_time_seconds();
	}

	/**
	 * Get the maximum length for stored honeypot values.
	 *
	 * @return int
	 */
	public static function get_honeypot_value_max_length() {
		return Settings_Store::get_honeypot_value_max_length();
	}

	/**
	 * Sanitize textarea rules line by line.
	 *
	 * @param string $rules Rules text.
	 * @return string
	 */
	public static function sanitize_rules( $rules ) {
		return Settings_Schema::sanitize_rules( $rules );
	}

	/**
	 * Validate an integer setting against a step pattern and range.
	 *
	 * @param mixed      $value    Raw value to validate.
	 * @param int        $fallback Fallback when the value is invalid.
	 * @param int        $min      Minimum allowed value (inclusive).
	 * @param int|string $max      Maximum allowed value (inclusive), or empty for no upper bound.
	 * @param int        $step     Required step increment.
	 * @return int Validated value.
	 */
	public static function validate_step_int( $value, $fallback, $min = 0, $max = '', $step = 1 ) {
		return Settings_Schema::validate_step_int( $value, $fallback, $min, $max, $step );
	}

	/**
	 * Validate an untrusted value against a schema descriptor.
	 *
	 * @param mixed $value      Raw value.
	 * @param array $descriptor Schema descriptor from setting_schema().
	 * @return mixed The validated value, or the schema default when invalid.
	 */
	public static function validate_typed_value( $value, array $descriptor ) {
		return Settings_Schema::validate_typed_value( $value, $descriptor );
	}

	/**
	 * Sanitize global settings from untrusted input.
	 *
	 * @param array $settings Unslashed settings data.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_global( array $settings ) {
		return Settings_Schema::sanitize_global( $settings );
	}
}
