<?php
/**
 * Plugin settings and stored report data.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, writes, and deletes plugin data.
 */
final class Settings {

	const SETTINGS_OPTION = 'simple_honeypot_cf7_settings';
	const STATS_OPTION    = 'simple_honeypot_cf7_stats';
	const FORM_META       = '_simple_honeypot_cf7_settings';

	/**
	 * Create default options when they do not exist.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			add_option( self::SETTINGS_OPTION, self::default_settings(), '', false );
		}

		if ( false === get_option( self::STATS_OPTION, false ) ) {
			add_option( self::STATS_OPTION, self::default_stats(), '', false );
		}
	}

	/**
	 * Remove all plugin data.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option( self::SETTINGS_OPTION );
		delete_option( self::STATS_OPTION );
		delete_site_transient( 'shcf7_github_release' );

		self::delete_form_meta_settings();
		self::remove_auto_update_opt_in();
	}

	/**
	 * Default global settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'time_check_enabled'   => 1,
			'min_time_seconds'     => 4,
			'max_age_minutes'      => 120,
			'custom_rules_enabled' => 0,
			'custom_rules'         => '',
			'pow_enabled'          => 0,
			'pow_complexity'       => 8,
			'store_honeypot_value' => 0,
			'keep_recent_events'   => 100,
		);
	}

	/**
	 * Default report counters.
	 *
	 * @return array
	 */
	public static function default_stats() {
		return array(
			'total'     => 0,
			'run_since' => time(),
			'reasons'   => array(),
			'forms'     => array(),
			'events'    => array(),
		);
	}

	/**
	 * Get global settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::SETTINGS_OPTION, array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
	}

	/**
	 * Save global settings.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	public static function update_settings( array $settings ) {
		update_option( self::SETTINGS_OPTION, wp_parse_args( $settings, self::default_settings() ), false );
	}

	/**
	 * Get report data merged with defaults.
	 *
	 * @return array
	 */
	public static function get_stats() {
		$stats = get_option( self::STATS_OPTION, array() );

		return wp_parse_args( is_array( $stats ) ? $stats : array(), self::default_stats() );
	}

	/**
	 * Save report data.
	 *
	 * @param array $stats Stats.
	 * @return void
	 */
	public static function update_stats( array $stats ) {
		update_option( self::STATS_OPTION, wp_parse_args( $stats, self::default_stats() ), false );
	}

	/**
	 * Reset report data.
	 *
	 * @return void
	 */
	public static function reset_stats() {
		self::update_stats( self::default_stats() );
	}

	/**
	 * Reset all global settings to defaults.
	 * Report data and per-form settings are preserved.
	 *
	 * @return void
	 */
	public static function reset_settings() {
		update_option( self::SETTINGS_OPTION, self::default_settings(), false );
	}

	/**
	 * Get per-form settings.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return array
	 */
	public static function get_form_settings( $form_id ) {
		$settings = $form_id ? get_post_meta( $form_id, self::FORM_META, true ) : array();

		return wp_parse_args(
			is_array( $settings ) ? $settings : array(),
			array(
				'time_mode'        => 'inherit',
				'min_time_seconds' => 0,
			)
		);
	}

	/**
	 * Save per-form settings.
	 *
	 * @param int   $form_id  Contact Form 7 form ID.
	 * @param array $settings Settings.
	 * @return void
	 */
	public static function update_form_settings( $form_id, array $settings ) {
		$data = array(
			'time_mode'        => self::allowed_mode( isset( $settings['time_mode'] ) ? $settings['time_mode'] : 'inherit' ),
			'min_time_seconds' => absint( isset( $settings['min_time_seconds'] ) ? $settings['min_time_seconds'] : 0 ),
		);

		update_post_meta( $form_id, self::FORM_META, $data );
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
			delete_post_meta( $form_id, self::FORM_META );
		}
	}

	/**
	 * Remove plugin from the auto_update_plugins option.
	 *
	 * @return void
	 */
	private static function remove_auto_update_opt_in() {
		$auto_updates = get_site_option( 'auto_update_plugins', array() );

		if ( in_array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME, $auto_updates, true ) ) {
			update_site_option(
				'auto_update_plugins',
				array_diff( $auto_updates, array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME ) )
			);
		}
	}
}
