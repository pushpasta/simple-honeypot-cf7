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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- MIME type is checked, not used for output.
		if ( ! empty( $_FILES['import_file']['type'] ) && 'application/json' !== $_FILES['import_file']['type'] ) {
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

		if ( ! is_array( $data ) || empty( $data['global_settings'] ) || ! is_array( $data['global_settings'] ) || empty( $data['version'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid file format.', 'simple-honeypot-cf7' ),
			);
		}

		$version = sanitize_text_field( $data['version'] );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The file format is not supported by this version of the plugin.', 'simple-honeypot-cf7' ),
			);
		}

		$global = $data['global_settings'];
		$merged = wp_parse_args( $global, Settings::default_settings() );

		$merged['time_check_enabled']   = empty( $merged['time_check_enabled'] ) ? 0 : 1;
		$merged['min_time_seconds']     = max( 0, absint( $merged['min_time_seconds'] ) );
		$merged['max_age_minutes']      = max( 10, absint( $merged['max_age_minutes'] ) );
		$merged['pow_enabled']          = empty( $merged['pow_enabled'] ) ? 0 : 1;
		$merged['pow_complexity']       = max( 4, min( 20, absint( $merged['pow_complexity'] ) ) );
		$merged['store_honeypot_value'] = empty( $merged['store_honeypot_value'] ) ? 0 : 1;
		$merged['keep_recent_events']   = max( 10, absint( $merged['keep_recent_events'] ) );
		$merged['custom_rules_enabled'] = empty( $merged['custom_rules_enabled'] ) ? 0 : 1;
		$merged['custom_rules']         = Settings::sanitize_rules( $merged['custom_rules'] );

		Settings::update_settings( $merged );

		if ( ! empty( $data['form_settings'] ) && is_array( $data['form_settings'] ) && Contact_Form_7::is_active() ) {
			foreach ( $data['form_settings'] as $form_id => $form_settings ) {
				if ( is_numeric( $form_id ) && is_array( $form_settings ) ) {
					$form_settings['time_mode']        = sanitize_key( isset( $form_settings['time_mode'] ) ? $form_settings['time_mode'] : 'inherit' );
					$form_settings['min_time_seconds'] = max( 0, absint( isset( $form_settings['min_time_seconds'] ) ? $form_settings['min_time_seconds'] : 0 ) );
					Settings::update_form_settings( (int) $form_id, $form_settings );
				}
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return array( 'success' => true );
	}
}
