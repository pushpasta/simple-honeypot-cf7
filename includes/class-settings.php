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

	const SETTINGS_OPTION  = SIMPLE_HONEYPOT_CF7_BASE . '_settings';
	const STATS_OPTION     = SIMPLE_HONEYPOT_CF7_BASE . '_stats';
	const FORM_META        = '_' . SIMPLE_HONEYPOT_CF7_BASE . '_settings';
	const RULES_SOFT_LIMIT = 10000;

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
		\SimpleHoneypotCF7\Reporting\Event_Logger::drop_table();

		delete_option( self::SETTINGS_OPTION );
		delete_option( self::STATS_OPTION );
		delete_option( Upgrader::DB_VERSION_OPTION );
		delete_transient( Upgrader::TRANSIENT_VERSION_OPTION );

		delete_site_transient( SIMPLE_HONEYPOT_CF7_BASE . '_github_release' );
		self::cleanup_readme_transients();

		self::delete_form_meta_settings();
		self::remove_auto_update_opt_in();

		// @todo Remove legacy option cleanup after a suitable deprecation period.
		foreach ( Upgrader::LEGACY_OPTIONS as $legacy_option ) {
			delete_option( $legacy_option );
		}

		delete_option( '_simple_honeypot_cf7_settings' );
	}

	/**
	 * Default global settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'time_check_enabled'      => 1,
			'min_time_seconds'        => 4,
			'max_age_minutes'         => 120,
			'custom_rules_enabled'    => 0,
			'custom_rules'            => '',
			'pow_enabled'             => 0,
			'pow_complexity'          => 8,
			'store_honeypot_value'    => 0,
			'keep_recent_events'      => 1000,
			'purge_events_after_days' => 0,
			'events_per_page'         => 20,
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
		);
	}

	/**
	 * Cached settings to avoid repeated DB reads within a single request.
	 *
	 * @var array|null
	 */
	private static $settings_cache;

	/**
	 * Get global settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}

		$settings = get_option( self::SETTINGS_OPTION, array() );

		self::$settings_cache = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );

		return self::$settings_cache;
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
		$stats    = self::default_stats();
		$existing = get_option( self::STATS_OPTION, array() );

		// Preserve the original activation date.
		if ( is_array( $existing ) && ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		self::update_stats( $stats );
		\SimpleHoneypotCF7\Reporting\Event_Logger::delete_all();
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
	 * Default per-form settings.
	 *
	 * @return array
	 */
	public static function default_form_settings() {
		return array(
			'time_mode'        => 'inherit',
			'min_time_seconds' => 0,
		);
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
			self::default_form_settings()
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
			'time_mode'        => self::allowed_mode( $settings['time_mode'] ?? 'inherit' ),
			'min_time_seconds' => absint( $settings['min_time_seconds'] ?? 0 ),
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
	 * Sanitize textarea rules line by line.
	 *
	 * @param string $rules Rules text.
	 * @return string
	 */
	public static function sanitize_rules( $rules ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $rules );
		$lines = array_map( 'sanitize_text_field', $lines );
		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines, 'strlen' );
		$lines = array_values( array_unique( $lines ) );

		$normalized = array();

		foreach ( $lines as $line ) {
			// Skip comments.
			if ( 0 === strpos( $line, '#' ) ) {
				$normalized[] = $line;
				continue;
			}

			// Detect type — must be email or IP, otherwise skip.
			$type = Rules\Rules::detect_type( $line );

			if ( '' === $type ) {
				continue;
			}

			// Normalize.
			$line = preg_replace( '/\*{2,}/', '*', $line );

			if ( 'email' === $type && 0 === strpos( $line, '@' ) ) {
				$line = '*' . $line;
			}

			$normalized[] = $line;
		}

		return implode( "\n", $normalized );
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
		if ( is_multisite() ) {
			$auto_updates = get_site_option( 'auto_update_plugins', array() );

			if ( in_array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME, $auto_updates, true ) ) {
				$auto_updates = array_values( array_diff( $auto_updates, array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME ) ) );

				if ( empty( $auto_updates ) ) {
					delete_site_option( 'auto_update_plugins' );
				} else {
					update_site_option( 'auto_update_plugins', $auto_updates );
				}
			}
		} else {
			$auto_updates = get_option( 'auto_update_plugins', array() );

			if ( in_array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME, $auto_updates, true ) ) {
				$auto_updates = array_values( array_diff( $auto_updates, array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME ) ) );

				if ( empty( $auto_updates ) ) {
					delete_option( 'auto_update_plugins' );
				} else {
					update_option( 'auto_update_plugins', $auto_updates );
				}
			}
		}
	}

	/**
	 * Delete cached readme site transients for all known tags.
	 *
	 * These are stored with the pattern shp4cf7_readme_{md5} in wp_sitemeta
	 * and cannot be enumerated without a direct query.
	 *
	 * @return void
	 */
	private static function cleanup_readme_transients() {
		if ( ! is_multisite() ) {
			return;
		}

		global $wpdb;

		$transient_prefix = SIMPLE_HONEYPOT_CF7_BASE . '_readme_';
		$esc_prefix       = $wpdb->esc_like( '_site_transient_' . $transient_prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$esc_prefix
			)
		);

		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $option_name ) {
			$tag_hash = str_replace( '_site_transient_' . $transient_prefix, '', $option_name );
			delete_site_transient( $transient_prefix . $tag_hash );
		}
	}
}
