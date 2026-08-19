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
				'error'   => sprintf(
					/* translators: 1: exported plugin version, 2: installed plugin version. */
					__( 'This export was created with version %1$s which is not compatible with version %2$s of the plugin.', 'simple-honeypot-cf7' ),
					'<strong>' . esc_html( $version ) . '</strong>',
					'<strong>' . esc_html( SIMPLE_HONEYPOT_CF7_VERSION ) . '</strong>'
				),
			);
		}

		if ( version_compare( $version, SIMPLE_HONEYPOT_CF7_VERSION, '>' ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: 1: exported plugin version, 2: installed plugin version. */
					__( 'This export was created with version %1$s but the installed version is %2$s. Please update the plugin before importing.', 'simple-honeypot-cf7' ),
					'<strong>' . esc_html( $version ) . '</strong>',
					'<strong>' . esc_html( SIMPLE_HONEYPOT_CF7_VERSION ) . '</strong>'
				),
			);
		}

		// Site URL check — validate the checkbox value as a boolean
		// with a safe default of true. Only '1' (checked) and '0'
		// (unchecked) are valid; unrecognized values fall back to true
		// (the restrictive default). A missing field means unchecked.
		$raw_check = isset( $_POST[ SIMPLE_HONEYPOT_CF7_BASE . '_check_site_url' ] )
			? sanitize_text_field( wp_unslash( $_POST[ SIMPLE_HONEYPOT_CF7_BASE . '_check_site_url' ] ) )
			: '';

		// '' = not in POST (unchecked), '0' = unchecked → false.
		// '1' = checked → true. Anything else = invalid → true.
		$check_site_url = ( '' !== $raw_check && '0' !== $raw_check );

		if ( $check_site_url && ! empty( $data['site_url'] ) ) {
			$exported_url = esc_url_raw( $data['site_url'] );
			$current_url  = home_url();

			if ( $exported_url !== $current_url ) {
				return array(
					'success' => false,
					'error'   => sprintf(
						/* translators: 1: exported site URL, 2: current site URL. */
						__( 'This export was created on %1$s but the current site is %2$s. Import aborted.', 'simple-honeypot-cf7' ),
						'<strong>' . esc_html( $exported_url ) . '</strong>',
						'<strong>' . esc_html( $current_url ) . '</strong>'
					),
				);
			}
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

		// --- Extract and validate each layer independently. ---

		$global = self::extract_global_settings(
			is_array( $data['global_settings'] ) ? $data['global_settings'] : array()
		);

		$rules = self::extract_rule_settings(
			is_array( $data['rule_settings'] ) ? $data['rule_settings'] : array()
		);

		// Merge rule keys into the global array before sanitization
		// so sanitize_global() can handle them in one pass.
		$merged = array_merge( $global, $rules );

		Settings::update_settings( Settings::sanitize_global( $merged ) );

		$forms = is_array( $data['form_settings'] ) ? $data['form_settings'] : array();

		self::import_form_settings( $forms );

		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return array( 'success' => true );
	}

	/**
	 * Extract and validate global (non-rule) settings from import data.
	 *
	 * Only schema-recognized keys with tab "settings" are kept. Unknown
	 * keys are silently discarded. Missing keys are not filled here —
	 * Settings::normalize_settings() handles that downstream.
	 *
	 * @param array $raw Raw global_settings from import.
	 * @return array Validated settings keyed by schema name.
	 */
	private static function extract_global_settings( array $raw ) {
		$extracted = array();
		$schema    = Settings::setting_schema();

		foreach ( $schema as $key => $descriptor ) {
			if ( 'settings' !== $descriptor['tab'] ) {
				continue;
			}

			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}

			$extracted[ $key ] = self::validate_value( $raw[ $key ], $descriptor );
		}

		return $extracted;
	}

	/**
	 * Extract and validate rule settings from import data.
	 *
	 * Only schema-recognized keys with tab "rules" are kept.
	 *
	 * @param array $raw Raw rule_settings from import.
	 * @return array Validated settings keyed by schema name.
	 */
	private static function extract_rule_settings( array $raw ) {
		$extracted = array();
		$schema    = Settings::setting_schema();

		foreach ( $schema as $key => $descriptor ) {
			if ( 'rules' !== $descriptor['tab'] ) {
				continue;
			}

			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}

			$extracted[ $key ] = self::validate_value( $raw[ $key ], $descriptor );
		}

		return $extracted;
	}

	/**
	 * Validate a single setting value against its schema descriptor.
	 *
	 * Treats all input as untrusted. Booleans are coerced via empty(),
	 * integers are absint'd and clamped to min/max, strings are
	 * sanitized with sanitize_text_field(). In doubt, the schema
	 * default is returned.
	 *
	 * @param mixed $value      Raw value from import.
	 * @param array $descriptor Schema descriptor.
	 * @return mixed Validated value.
	 */
	private static function validate_value( $value, array $descriptor ) {
		if ( 'bool' === $descriptor['type'] ) {
			return ! empty( $value ) ? 1 : 0;
		}

		if ( 'int' === $descriptor['type'] ) {
			return absint( $value );
		}

		// string type — used by custom_rules.
		if ( is_string( $value ) ) {
			return $value;
		}

		return (string) $value;
	}

	/**
	 * Import per-form settings from import data.
	 *
	 * Validates each form ID and its settings against known keys
	 * before saving. Unknown keys are discarded.
	 *
	 * @param array $forms Raw form_settings from import.
	 * @return void
	 */
	private static function import_form_settings( array $forms ) {
		if ( empty( $forms ) || ! Contact_Form_7::is_active() ) {
			return;
		}

		foreach ( $forms as $form_id => $form_settings ) {
			if ( ! is_numeric( $form_id ) || ! is_array( $form_settings ) ) {
				continue;
			}

			$form_id = (int) $form_id;

			if ( 'wpcf7_contact_form' !== get_post_type( $form_id ) ) {
				continue;
			}

			// Only update forms that already have stored settings.
			// Forms without explicit per-form overrides inherit global
			// defaults and should not receive imported overrides.
			$existing = get_post_meta( $form_id, Settings::FORM_META, true );

			if ( empty( $existing ) || ! is_array( $existing ) ) {
				continue;
			}

			$clean = array();

			// Only allowlist known form setting keys.
			$allowed_modes = array( 'inherit', 'enabled', 'disabled' );
			$time_mode     = sanitize_key( $form_settings['time_mode'] ?? 'inherit' );

			$clean['time_mode']        = in_array( $time_mode, $allowed_modes, true ) ? $time_mode : 'inherit';
			$clean['min_time_seconds'] = absint( $form_settings['min_time_seconds'] ?? 0 );

			Settings::update_form_settings( $form_id, $clean );
		}
	}
}
