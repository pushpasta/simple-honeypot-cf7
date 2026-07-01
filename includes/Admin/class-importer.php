<?php
/**
 * Settings importer.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

use SimpleHoneypotCF7\Settings;
use SimpleHoneypotCF7\Support\Contact_Form_7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles importing plugin settings from an uploaded JSON file.
 */
final class Importer {

	/**
	 * Import settings from an uploaded JSON file.
	 *
	 * @return array{success: bool, error?: string}
	 */
	public function import() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller (Settings_Page::handle_post).
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File uploads are not sanitized.
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a validated temporary upload.
		if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			return array( 'success' => false );
		}

		$max_size = wp_max_upload_size();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File size is checked numerically.
		if ( ! empty( $_FILES['import_file']['size'] ) && (int) $_FILES['import_file']['size'] > $max_size ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: maximum file size in MB. */
					__( 'File is too large. Maximum size is %s MB.', 'simple-honeypot-cf7' ),
					number_format( $max_size / ( 1024 * 1024 ), 1 )
				),
			);
		}

		$file_name = isset( $_FILES['import_file']['name'] ) ? wp_unslash( $_FILES['import_file']['name'] ) : '';
		$file_type = wp_check_filetype( $file_name, array( 'json' => 'application/json' ) );

		if ( empty( $file_type['type'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Only JSON files are supported.', 'simple-honeypot-cf7' ),
			);
		}

		$contents = file_get_contents( $_FILES['import_file']['tmp_name'] );

		if ( false === $contents ) {
			return array( 'success' => false );
		}

		$data = json_decode( $contents, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return array(
				'success' => false,
				'error'   => __( 'The file is not valid JSON.', 'simple-honeypot-cf7' ),
			);
		}

		$version = isset( $data['version'] ) ? sanitize_text_field( $data['version'] ) : '';
		if ( '' !== $version && version_compare( $version, '1.0.0', '<' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This export was created with an incompatible plugin version.', 'simple-honeypot-cf7' ),
			);
		}

		if ( ! is_array( $data ) || empty( $data['global_settings'] ) || ! is_array( $data['global_settings'] ) || ! isset( $data['global_settings']['time_check_enabled'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The file does not match the expected format and cannot be imported.', 'simple-honeypot-cf7' ),
			);
		}

		$global = $this->validate_global_settings( $data['global_settings'] );
		$merged = wp_parse_args( $global, Settings::get_settings() );

		$merged = Settings::sanitize_global( $merged );

		Settings::update_settings( $merged );

		if ( ! empty( $data['form_settings'] ) && is_array( $data['form_settings'] ) && Contact_Form_7::is_active() ) {
			foreach ( $data['form_settings'] as $form_id => $form_settings ) {
				if ( is_numeric( $form_id ) && is_array( $form_settings ) ) {
					$allowed_modes                     = array( 'inherit', 'enabled', 'disabled' );
					$time_mode                         = sanitize_key( isset( $form_settings['time_mode'] ) ? $form_settings['time_mode'] : 'inherit' );
					$form_settings['time_mode']        = in_array( $time_mode, $allowed_modes, true ) ? $time_mode : 'inherit';
					$form_settings['min_time_seconds'] = max( 0, absint( isset( $form_settings['min_time_seconds'] ) ? $form_settings['min_time_seconds'] : 0 ) );
					Settings::update_form_settings( (int) $form_id, $form_settings );
				}
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return array( 'success' => true );
	}

	/**
	 * Validate and sanitize imported global settings.
	 *
	 * Ensures each value is the correct type and within the allowed range.
	 *
	 * @param array $settings Raw settings from the import file.
	 * @return array Validated settings.
	 */
	private function validate_global_settings( array $settings ) {
		$defaults = Settings::default_settings();

		$settings = array_merge( $defaults, $settings );

		$settings['time_check_enabled']        = empty( $settings['time_check_enabled'] ) ? 0 : 1;
		$settings['min_time_seconds']          = max( 0, absint( $settings['min_time_seconds'] ) );
		$settings['max_age_minutes']           = max( 10, absint( $settings['max_age_minutes'] ) );
		$settings['pow_enabled']               = empty( $settings['pow_enabled'] ) ? 0 : 1;
		$settings['pow_complexity']            = max( 4, min( 20, absint( $settings['pow_complexity'] ) ) );
		$settings['store_honeypot_value']      = empty( $settings['store_honeypot_value'] ) ? 0 : 1;
		$settings['honeypot_value_max_length'] = max( 10, min( 200, absint( $settings['honeypot_value_max_length'] ) ) );
		$settings['keep_recent_events']        = max( 10, absint( $settings['keep_recent_events'] ) );
		$settings['purge_events_after_days']   = max( 0, absint( $settings['purge_events_after_days'] ) );
		$settings['events_per_page']           = max( 5, min( 200, absint( $settings['events_per_page'] ) ) );
		$settings['custom_rules_enabled']      = empty( $settings['custom_rules_enabled'] ) ? 0 : 1;
		$settings['custom_rules']              = isset( $settings['custom_rules'] ) ? sanitize_text_field( $settings['custom_rules'] ) : '';

		return $settings;
	}
}
