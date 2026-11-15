<?php
/**
 * Plugin settings storage.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

use SimpleHoneypotCF7\Reporting\Event_Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, writes, and deletes stored plugin settings.
 */
final class Settings_Store {

	/**
	 * Cached settings to avoid repeated DB reads within a single request.
	 *
	 * @var array|null
	 */
	private static $settings_cache;

	/**
	 * Create default options when they do not exist.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( Settings::SETTINGS_OPTION, false ) ) {
			add_option( Settings::SETTINGS_OPTION, Settings_Schema::default_settings(), '', false );
		}

		if ( false === get_option( Settings::META_OPTION, false ) ) {
			add_option( Settings::META_OPTION, Settings_Reports::default_meta(), '', false );
		}
	}

	/**
	 * Remove all plugin data.
	 *
	 * Uses a single prefix-based query to delete all plugin options
	 * instead of enumerating each one individually.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		Event_Logger::drop_table();

		// Delete all shp4cf7_ options in one query.
		$esc_prefix = $wpdb->esc_like( SIMPLE_HONEYPOT_CF7_BASE ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$esc_prefix,
				$wpdb->esc_like( '_shp4cf7_settings' ) . '%'
			)
		);

		// Delete all transients (stored as _transient_shp4cf7_* and _transient_timeout_shp4cf7_*).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . SIMPLE_HONEYPOT_CF7_BASE . '_' ) . '%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_' . SIMPLE_HONEYPOT_CF7_BASE . '_' ) . '%'
			)
		);

		// Delete site transients.
		delete_site_transient( SIMPLE_HONEYPOT_CF7_BASE . '_github_release' );
		self::delete_site_transient_rows();

		// Delete per-form post meta.
		self::delete_form_meta_settings();

		// Remove from auto_update_plugins.
		self::remove_auto_update_opt_in();
	}

	/**
	 * Get global settings, normalized against the schema.
	 *
	 * Missing keys are filled with defaults and present values are
	 * validated, so stored values can never bypass the schema bounds.
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}

		$settings = get_option( Settings::SETTINGS_OPTION, array() );

		self::$settings_cache = Settings_Schema::normalize_settings( is_array( $settings ) ? $settings : array() );

		return self::$settings_cache;
	}

	/**
	 * Save global settings.
	 *
	 * Settings are always sanitized against the schema before they are
	 * stored, regardless of the caller.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	public static function update_settings( array $settings ) {
		update_option( Settings::SETTINGS_OPTION, Settings_Schema::sanitize_global( $settings ), false );

		self::$settings_cache = null;
	}

	/**
	 * Re-validate stored settings against the schema.
	 *
	 * Brings out-of-range or off-step values inherited from previous
	 * plugin versions, direct option edits, or restored backups back
	 * within the schema bounds. Only writes when something changed.
	 *
	 * @return void
	 */
	public static function normalize_stored_settings() {
		$current  = get_option( Settings::SETTINGS_OPTION, array() );
		$repaired = Settings_Schema::sanitize_global( is_array( $current ) ? $current : array() );

		if ( $repaired !== $current ) {
			update_option( Settings::SETTINGS_OPTION, $repaired, false );
		}

		self::$settings_cache = null;
	}

	/**
	 * Reset Settings-tab values to defaults.
	 * Rules-tab values, per-form settings, and reporting data are preserved.
	 *
	 * @return void
	 */
	public static function reset_settings() {
		$current  = get_option( Settings::SETTINGS_OPTION, array() );
		$defaults = Settings_Schema::default_settings();

		foreach ( Settings_Schema::settings_tab_keys() as $key ) {
			$current[ $key ] = $defaults[ $key ];
		}

		update_option( Settings::SETTINGS_OPTION, $current, false );

		self::$settings_cache = null;
	}

	/**
	 * Get per-form settings.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return array
	 */
	public static function get_form_settings( $form_id ) {
		$settings = $form_id ? get_post_meta( $form_id, Settings::FORM_META, true ) : array();

		return self::normalize_form_settings( is_array( $settings ) ? $settings : array() );
	}

	/**
	 * Save per-form settings.
	 *
	 * @param int   $form_id  Contact Form 7 form ID.
	 * @param array $settings Settings.
	 * @return void
	 */
	public static function update_form_settings( $form_id, array $settings ) {
		update_post_meta( $form_id, Settings::FORM_META, self::normalize_form_settings( $settings ) );
	}

	/**
	 * Normalize per-form settings.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	private static function normalize_form_settings( array $settings ) {
		$min_time = absint( $settings['min_time_seconds'] ?? 0 );

		return array(
			'time_mode'        => self::allowed_mode( $settings['time_mode'] ?? 'inherit' ),
			'min_time_seconds' => min( $min_time, self::get_max_min_time_seconds() ),
		);
	}

	/**
	 * Check whether timing validation is enabled.
	 *
	 * @param int $form_id Optional Contact Form 7 form ID.
	 * @return bool
	 */
	public static function is_time_check_enabled( $form_id = 0 ) {
		$settings = self::get_settings();

		if ( $form_id ) {
			$form_settings = self::get_form_settings( $form_id );

			if ( 'enabled' === $form_settings['time_mode'] ) {
				return true;
			}

			if ( 'disabled' === $form_settings['time_mode'] ) {
				return false;
			}
		}

		return ! empty( $settings['time_check_enabled'] );
	}

	/**
	 * Get the minimum allowed submission time.
	 *
	 * @param int $form_id Optional Contact Form 7 form ID.
	 * @return int
	 */
	public static function get_min_submission_time( $form_id = 0 ) {
		$settings = self::get_settings();

		if ( $form_id ) {
			$form_settings = self::get_form_settings( $form_id );

			if ( ! empty( $form_settings['min_time_seconds'] ) ) {
				return absint( $form_settings['min_time_seconds'] );
			}
		}

		return absint( $settings['min_time_seconds'] );
	}

	/**
	 * Get the maximum allowed minimum-submission-time.
	 *
	 * The minimum time can never exceed the token lifetime, or every
	 * submission is flagged as too fast once the token expires. Used for
	 * backend input bounds and per-form clamping.
	 *
	 * @return int
	 */
	public static function get_max_min_time_seconds() {
		$settings = self::get_settings();
		$schema   = Settings_Schema::setting_schema();

		return min( absint( $settings['max_age_minutes'] ) * 60, $schema['min_time_seconds']['max'] );
	}

	/**
	 * Get the maximum length for stored honeypot values.
	 *
	 * The value is already within the configured bounds because
	 * get_settings() normalizes against the schema. Callers should
	 * use this rather than reading the setting directly, so every
	 * storage path applies the same cap.
	 *
	 * @return int
	 */
	public static function get_honeypot_value_max_length() {
		$settings = self::get_settings();

		return absint( $settings['honeypot_value_max_length'] );
	}

	/**
	 * Validate an inherit/enabled/disabled mode.
	 *
	 * @param string $mode Mode.
	 * @return string
	 */
	private static function allowed_mode( $mode ) {
		return in_array( $mode, array( 'inherit', 'enabled', 'disabled' ), true ) ? $mode : 'inherit';
	}

	/**
	 * Delete stored form settings for all Contact Form 7 forms.
	 *
	 * @return void
	 */
	private static function delete_form_meta_settings() {
		$forms = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		foreach ( $forms as $form_id ) {
			delete_post_meta( $form_id, Settings::FORM_META );
		}
	}

	/**
	 * Remove plugin from the auto_update_plugins option.
	 *
	 * Uses the site-option API for consistency with activation and the
	 * plugin list toggle. WordPress maps site options to regular options
	 * on single-site installs, so the same code handles both cases.
	 *
	 * @return void
	 */
	private static function remove_auto_update_opt_in() {
		$auto_updates = get_site_option( 'auto_update_plugins', array() );

		if ( ! in_array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME, $auto_updates, true ) ) {
			return;
		}

		$auto_updates = array_values(
			array_diff( $auto_updates, array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME ) )
		);

		if ( empty( $auto_updates ) ) {
			delete_site_option( 'auto_update_plugins' );
		} else {
			update_site_option( 'auto_update_plugins', $auto_updates );
		}
	}

	/**
	 * Delete all plugin site transients from the database.
	 *
	 * Site transients are stored as _site_transient_shp4cf7_* rows in
	 * wp_options on single site and wp_sitemeta on multisite. Their
	 * names can contain hashes of the release tag, so they cannot be
	 * enumerated individually and are removed by prefix instead.
	 *
	 * @return void
	 */
	private static function delete_site_transient_rows() {
		global $wpdb;

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		} else {
			$table  = $wpdb->options;
			$column = 'option_name';
		}

		$value_prefix   = $wpdb->esc_like( '_site_transient_' . SIMPLE_HONEYPOT_CF7_BASE ) . '%';
		$timeout_prefix = $wpdb->esc_like( '_site_transient_timeout_' . SIMPLE_HONEYPOT_CF7_BASE ) . '%';

		// Table and column names cannot be parameterized.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE {$column} LIKE %s OR {$column} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$value_prefix,
				$timeout_prefix
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
