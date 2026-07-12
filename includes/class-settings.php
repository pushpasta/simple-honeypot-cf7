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
		delete_option( \SimpleHoneypotCF7\Frontend\Token::CONSUMED_OPTION );
		delete_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles' );
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
			'time_check_enabled'        => 1,
			'min_time_seconds'          => 4,
			'max_age_minutes'           => 30,
			'token_rate_limit'          => 10,
			'custom_rules_enabled'      => 0,
			'custom_rules'              => '',
			'pow_enabled'               => 0,
			'pow_complexity'            => 14,
			'store_honeypot_value'      => 0,
			'honeypot_value_max_length' => 100,
			'keep_recent_events'        => 1000,
			'purge_events_after_days'   => 0,
			'events_per_page'           => 20,
		);
	}

	/**
	 * Setting keys accepted on the Settings tab.
	 *
	 * @return string[]
	 */
	public static function settings_tab_keys() {
		return array(
			'time_check_enabled',
			'min_time_seconds',
			'max_age_minutes',
			'token_rate_limit',
			'pow_enabled',
			'pow_complexity',
			'store_honeypot_value',
			'honeypot_value_max_length',
			'keep_recent_events',
			'purge_events_after_days',
			'events_per_page',
		);
	}

	/**
	 * Setting keys accepted on the Rules tab.
	 *
	 * @return string[]
	 */
	public static function rules_tab_keys() {
		return array(
			'custom_rules_enabled',
			'custom_rules',
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

		$merged = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );

		self::$settings_cache = array_intersect_key( $merged, self::default_settings() );

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
	 * Reads counters from the atomic stats table and falls back to the
	 * legacy option for pre-upgrade data. Run-since is preserved in the
	 * option because it is not a counter.
	 *
	 * @return array
	 */
	public static function get_stats() {
		$existing = get_option( self::STATS_OPTION, array() );
		$stats    = self::default_stats();

		if ( is_array( $existing ) && ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		$counters = \SimpleHoneypotCF7\Reporting\Event_Logger::get_counters();

		if ( empty( $counters ) ) {
			return self::stats_from_legacy( $existing );
		}

		// Build total.
		if ( isset( $counters['total'] ) ) {
			$stats['total'] = $counters['total'];
		}

		// Build reasons breakdown.
		foreach ( $counters as $name => $value ) {
			if ( 0 === strpos( $name, 'reason:' ) ) {
				$stats['reasons'][ substr( $name, 7 ) ] = $value;
			}
		}

		// Build forms breakdown with titles.
		$form_titles = get_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles', array() );

		foreach ( $counters as $name => $value ) {
			if ( 0 === strpos( $name, 'form:' ) ) {
				$form_id                    = substr( $name, 5 );
				$title                      = isset( $form_titles[ $form_id ] ) ? $form_titles[ $form_id ] : __( 'Unknown form', 'simple-honeypot-cf7' );
				$stats['forms'][ $form_id ] = array(
					'title' => $title,
					'count' => $value,
				);
			}
		}

		return $stats;
	}

	/**
	 * Legacy stats saver — kept for backward compatibility.
	 *
	 * No longer called by the plugin; all counters are now atomically
	 * incremented via the dedicated database table.
	 *
	 * @param array $stats Stats.
	 * @return void
	 */
	public static function update_stats( array $stats ) {
		update_option( self::STATS_OPTION, wp_parse_args( $stats, self::default_stats() ), false );
	}

	/**
	 * Reset all reporting data.
	 *
	 * @return void
	 */
	public static function reset_stats() {
		\SimpleHoneypotCF7\Reporting\Event_Logger::reset_counters();
		\SimpleHoneypotCF7\Reporting\Event_Logger::delete_all();

		delete_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles' );

		// Preserve the original activation date.
		$existing = get_option( self::STATS_OPTION, array() );
		$stats    = self::default_stats();

		if ( is_array( $existing ) && ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		update_option( self::STATS_OPTION, $stats, false );
	}

	/**
	 * Build stats from the legacy option.
	 *
	 * On the first read after upgrade the counter table is empty.
	 * This method migrates any existing data into the counter table
	 * so subsequent reads are served from there.
	 *
	 * @param array $existing The legacy stats option value.
	 * @return array
	 */
	private static function stats_from_legacy( array $existing ) {
		$stats = self::default_stats();

		if ( ! empty( $existing['total'] ) ) {
			// Migrate legacy counters to the atomic table.
			$logger = '\SimpleHoneypotCF7\Reporting\Event_Logger';

			$logger::set_counter( 'total', absint( $existing['total'] ) );

			if ( ! empty( $existing['reasons'] ) && is_array( $existing['reasons'] ) ) {
				foreach ( $existing['reasons'] as $type => $count ) {
					$logger::set_counter( 'reason:' . sanitize_key( $type ), absint( $count ) );
				}
			}

			if ( ! empty( $existing['forms'] ) && is_array( $existing['forms'] ) ) {
				$form_titles = array();

				foreach ( $existing['forms'] as $form_id => $form_data ) {
					if ( is_array( $form_data ) && ! empty( $form_data['count'] ) ) {
						$logger::set_counter( 'form:' . sanitize_key( (string) $form_id ), absint( $form_data['count'] ) );

						if ( ! empty( $form_data['title'] ) ) {
							$form_titles[ $form_id ] = sanitize_text_field( $form_data['title'] );
						}
					}
				}

				if ( ! empty( $form_titles ) ) {
					update_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles', $form_titles, false );
				}
			}

			// Return the legacy data. The next call to get_stats() will
			// serve from the counter table if migration succeeded.
			$stats['total']   = absint( $existing['total'] );
			$stats['reasons'] = isset( $existing['reasons'] ) && is_array( $existing['reasons'] ) ? $existing['reasons'] : array();
			$stats['forms']   = isset( $existing['forms'] ) && is_array( $existing['forms'] ) ? $existing['forms'] : array();

			return $stats;
		}

		// No legacy data — return the option as-is (likely defaults).
		$stats['total']   = isset( $existing['total'] ) ? absint( $existing['total'] ) : 0;
		$stats['reasons'] = isset( $existing['reasons'] ) && is_array( $existing['reasons'] ) ? $existing['reasons'] : array();
		$stats['forms']   = isset( $existing['forms'] ) && is_array( $existing['forms'] ) ? $existing['forms'] : array();

		if ( ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		return $stats;
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
	 * Sanitize global settings from untrusted input.
	 *
	 * @param array $settings Unslashed settings data.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_global( array $settings ) {
		$settings['time_check_enabled']        = empty( $settings['time_check_enabled'] ) ? 0 : 1;
		$settings['min_time_seconds']          = max( 0, absint( $settings['min_time_seconds'] ) );
		$settings['max_age_minutes']           = max( 10, min( 90, absint( $settings['max_age_minutes'] ) ) );
		$settings['token_rate_limit']          = max( 0, min( 50, absint( $settings['token_rate_limit'] ) ) );
		$settings['custom_rules_enabled']      = empty( $settings['custom_rules_enabled'] ) ? 0 : 1;
		$settings['custom_rules']              = self::sanitize_rules( $settings['custom_rules'] ?? '' );
		$settings['pow_enabled']               = empty( $settings['pow_enabled'] ) ? 0 : 1;
		$settings['pow_complexity']            = max( 4, min( 20, absint( $settings['pow_complexity'] ) ) );
		$settings['store_honeypot_value']      = empty( $settings['store_honeypot_value'] ) ? 0 : 1;
		$settings['honeypot_value_max_length'] = max( 10, min( 200, absint( $settings['honeypot_value_max_length'] ) ) );
		$settings['keep_recent_events']        = max( 10, absint( $settings['keep_recent_events'] ) );
		$settings['purge_events_after_days']   = max( 0, absint( $settings['purge_events_after_days'] ) );
		$settings['events_per_page']           = max( 5, min( 200, absint( $settings['events_per_page'] ) ) );

		return $settings;
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
		$is_multisite = is_multisite();
		$auto_updates = $is_multisite
			? get_site_option( 'auto_update_plugins', array() )
			: get_option( 'auto_update_plugins', array() );

		if ( ! in_array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME, $auto_updates, true ) ) {
			return;
		}

		$auto_updates = array_values(
			array_diff( $auto_updates, array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME ) )
		);

		if ( empty( $auto_updates ) ) {
			$is_multisite ? delete_site_option( 'auto_update_plugins' ) : delete_option( 'auto_update_plugins' );
		} else {
			$is_multisite ? update_site_option( 'auto_update_plugins', $auto_updates ) : update_option( 'auto_update_plugins', $auto_updates );
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
