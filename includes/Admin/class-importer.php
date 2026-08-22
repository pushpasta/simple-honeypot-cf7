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

		$version = isset( $data['version'] ) && is_string( $data['version'] )
			? sanitize_text_field( $data['version'] )
			: '';

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

		if ( $check_site_url ) {
			// Safeguard: with the check enabled there must be something to
			// compare against. Exports without a site URL are rejected so
			// the user can consciously uncheck the box instead.
			if ( empty( $data['site_url'] ) || ! is_string( $data['site_url'] ) ) {
				return array(
					'success' => false,
					'error'   => __( 'The file does not contain a site URL to verify against. Uncheck the site URL option if you want to import anyway.', 'simple-honeypot-cf7' ),
				);
			}

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

		$global = self::extract_tab_settings(
			is_array( isset( $data['global_settings'] ) ? $data['global_settings'] : null ) ? $data['global_settings'] : array(),
			'settings'
		);

		$rules = self::extract_tab_settings(
			is_array( isset( $data['rule_settings'] ) ? $data['rule_settings'] : null ) ? $data['rule_settings'] : array(),
			'rules'
		);

		// Seed the merge with the currently stored settings so keys that
		// are not part of the import file keep their existing values,
		// then overlay the validated import keys.
		$merged = array_merge( Settings::get_settings(), $global, $rules );

		Settings::update_settings( Settings::sanitize_global( $merged ) );

		$forms = isset( $data['form_settings'] ) && is_array( $data['form_settings'] )
			? $data['form_settings']
			: array();

		self::import_form_settings( $forms );

		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return array( 'success' => true );
	}

	/**
	 * Extract and validate one tab's settings from import data.
	 *
	 * Only schema-recognized keys belonging to the given tab are kept;
	 * unknown or gibberish keys are silently ignored. Each key present
	 * in the file is validated against its schema descriptor — invalid
	 * types and out-of-scope values resolve to the schema default.
	 * Keys missing from the file are not returned, so the seeded merge
	 * with current settings leaves their stored values untouched.
	 *
	 * @param array  $raw Raw settings for this tab from the import file.
	 * @param string $tab Schema tab name ("settings" or "rules").
	 * @return array Validated settings keyed by schema name.
	 */
	private static function extract_tab_settings( array $raw, $tab ) {
		$extracted = array();

		foreach ( Settings::setting_schema() as $key => $descriptor ) {
			if ( $tab !== $descriptor['tab'] ) {
				continue;
			}

			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}

			$extracted[ $key ] = Settings::validate_typed_value( $raw[ $key ], $descriptor );
		}

		return $extracted;
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
