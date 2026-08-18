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
	 * Maximum import file size in bytes.
	 *
	 * Settings exports are typically a few KB. This cap prevents a
	 * privileged upload from causing avoidable memory pressure via
	 * file_get_contents / json_decode of the full file content.
	 */
	const MAX_IMPORT_SIZE = 524288; // 512 KB.

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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File size is checked numerically.
		$file_size = ! empty( $_FILES['import_file']['size'] ) ? (int) $_FILES['import_file']['size'] : 0;

		if ( $file_size > self::MAX_IMPORT_SIZE ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: maximum file size in KB. */
					__( 'File is too large. Maximum size is %s KB.', 'simple-honeypot-cf7' ),
					number_format( self::MAX_IMPORT_SIZE / 1024 )
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

		if ( '' === $version ) {
			return array(
				'success' => false,
				'error'   => __( 'The file is missing a version number and cannot be imported.', 'simple-honeypot-cf7' ),
			);
		}

		if ( version_compare( $version, '1.0.0', '<' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This export was created with an incompatible plugin version.', 'simple-honeypot-cf7' ),
			);
		}

		if ( version_compare( $version, SIMPLE_HONEYPOT_CF7_VERSION, '>' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This export was created with a newer version of the plugin. Please update before importing.', 'simple-honeypot-cf7' ),
			);
		}

		// Pre-3.2.0 exports store rules inside global_settings.
		// Move them to rule_settings so the rest of the importer can
		// rely on a single structure.
		if ( version_compare( $version, '3.2.0', '<' ) ) {
			if ( ! empty( $data['global_settings'] ) && is_array( $data['global_settings'] ) ) {
				if ( ! isset( $data['rule_settings'] ) || ! is_array( $data['rule_settings'] ) ) {
					$data['rule_settings'] = array();
				}

				$rule_keys = array( 'custom_rules_enabled', 'custom_rules' );

				foreach ( $rule_keys as $rule_key ) {
					if ( isset( $data['global_settings'][ $rule_key ] ) ) {
						$data['rule_settings'][ $rule_key ] = $data['global_settings'][ $rule_key ];
						unset( $data['global_settings'][ $rule_key ] );
					}
				}
			}
		}

		if ( ! is_array( $data ) || empty( $data['global_settings'] ) || ! is_array( $data['global_settings'] ) || ! isset( $data['global_settings']['time_check_enabled'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The file does not match the expected format and cannot be imported.', 'simple-honeypot-cf7' ),
			);
		}

		$merged = wp_parse_args( $data['global_settings'], Settings::get_settings() );

		Settings::update_settings( Settings::sanitize_global( $merged ) );

		if ( ! empty( $data['form_settings'] ) && is_array( $data['form_settings'] ) && Contact_Form_7::is_active() ) {
			foreach ( $data['form_settings'] as $form_id => $form_settings ) {
				if ( ! is_numeric( $form_id ) || ! is_array( $form_settings ) ) {
					continue;
				}

				if ( 'wpcf7_contact_form' !== get_post_type( (int) $form_id ) ) {
					continue;
				}

				$allowed_modes                     = array( 'inherit', 'enabled', 'disabled' );
				$time_mode                         = sanitize_key( isset( $form_settings['time_mode'] ) ? $form_settings['time_mode'] : 'inherit' );
				$form_settings['time_mode']        = in_array( $time_mode, $allowed_modes, true ) ? $time_mode : 'inherit';
				$form_settings['min_time_seconds'] = max( 0, absint( isset( $form_settings['min_time_seconds'] ) ? $form_settings['min_time_seconds'] : 0 ) );
				Settings::update_form_settings( (int) $form_id, $form_settings );
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return array( 'success' => true );
	}
}
