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
	const META_OPTION      = SIMPLE_HONEYPOT_CF7_BASE . '_meta';
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

		if ( false === get_option( self::META_OPTION, false ) ) {
			add_option( self::META_OPTION, self::default_meta(), '', false );
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

		\SimpleHoneypotCF7\Reporting\Event_Logger::drop_table();

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
		self::cleanup_readme_transients();

		// Delete per-form post meta.
		self::delete_form_meta_settings();

		// Remove from auto_update_plugins.
		self::remove_auto_update_opt_in();
	}

	/**
	 * Define the global settings schema.
	 *
	 * Single source of truth for defaults, validation bounds, and tab
	 * placement. Integer settings may define 'min', 'max', and 'step';
	 * out-of-range or off-step integer values fall back to the 'default'
	 * (recommended) value. All consumers — saves, imports, reads, upgrades,
	 * and the admin UI — derive their behavior from this map.
	 *
	 * @return array<string, array{type: string, default: mixed, tab: string, min?: int, max?: int, step?: int}>
	 */
	public static function setting_schema() {
		return array(
			'time_check_enabled'        => array(
				'type'    => 'bool',
				'default' => 1,
				'tab'     => 'settings',
			),
			'min_time_seconds'          => array(
				'type'    => 'int',
				'default' => 4,
				'tab'     => 'settings',
				'min'     => 0,
				'max'     => 3600,
				'step'    => 1,
			),
			'max_age_minutes'           => array(
				'type'    => 'int',
				'default' => 15,
				'tab'     => 'settings',
				'min'     => 10,
				'max'     => 60,
				'step'    => 5,
			),
			'token_rate_limit'          => array(
				'type'    => 'int',
				'default' => 10,
				'tab'     => 'settings',
				'min'     => 0,
				'max'     => 30,
				'step'    => 5,
			),
			'custom_rules_enabled'      => array(
				'type'    => 'bool',
				'default' => 0,
				'tab'     => 'rules',
			),
			'custom_rules'              => array(
				'type'    => 'string',
				'default' => '',
				'tab'     => 'rules',
			),
			'pow_enabled'               => array(
				'type'    => 'bool',
				'default' => 0,
				'tab'     => 'settings',
			),
			'pow_complexity'            => array(
				'type'    => 'int',
				'default' => 15,
				'tab'     => 'settings',
				'min'     => 5,
				'max'     => 30,
				'step'    => 5,
			),
			'store_honeypot_value'      => array(
				'type'    => 'bool',
				'default' => 0,
				'tab'     => 'settings',
			),
			'honeypot_value_max_length' => array(
				'type'    => 'int',
				'default' => 100,
				'tab'     => 'settings',
				'min'     => 10,
				'max'     => 200,
				'step'    => 10,
			),
			'keep_recent_events'        => array(
				'type'    => 'int',
				'default' => 1000,
				'tab'     => 'settings',
				'min'     => 10,
				'max'     => 100000,
				'step'    => 1,
			),
			'purge_events_after_days'   => array(
				'type'    => 'int',
				'default' => 0,
				'tab'     => 'settings',
				'min'     => 0,
				'max'     => 3650,
				'step'    => 1,
			),
			'events_per_page'           => array(
				'type'    => 'int',
				'default' => 20,
				'tab'     => 'settings',
				'min'     => 5,
				'max'     => 200,
				'step'    => 1,
			),
		);
	}

	/**
	 * Default global settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		$defaults = array();

		foreach ( self::setting_schema() as $key => $descriptor ) {
			$defaults[ $key ] = $descriptor['default'];
		}

		return $defaults;
	}

	/**
	 * Setting keys accepted on the Settings tab.
	 *
	 * @return string[]
	 */
	public static function settings_tab_keys() {
		return self::schema_keys_for_tab( 'settings' );
	}

	/**
	 * Setting keys accepted on the Rules tab.
	 *
	 * @return string[]
	 */
	public static function rules_tab_keys() {
		return self::schema_keys_for_tab( 'rules' );
	}

	/**
	 * Keys belonging to a given settings tab.
	 *
	 * @param string $tab Tab name.
	 * @return string[]
	 */
	private static function schema_keys_for_tab( $tab ) {
		$keys = array();

		foreach ( self::setting_schema() as $key => $descriptor ) {
			if ( $tab === $descriptor['tab'] ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Default report counters.
	 *
	 * @return array
	 */
	public static function default_meta() {
		return array(
			'total'           => 0,
			'run_since'       => time(),
			'last_updated'    => '',
			'last_calculated' => '',
			'reasons'         => array(),
			'forms'           => array(),
		);
	}

	/**
	 * Cached settings to avoid repeated DB reads within a single request.
	 *
	 * @var array|null
	 */
	private static $settings_cache;

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

		$settings = get_option( self::SETTINGS_OPTION, array() );

		self::$settings_cache = self::normalize_settings( is_array( $settings ) ? $settings : array() );

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
		update_option( self::SETTINGS_OPTION, self::sanitize_global( $settings ), false );

		self::$settings_cache = null;
	}

	/**
	 * Normalize a settings array against the schema.
	 *
	 * Fills missing keys with defaults and validates present values,
	 * falling back to the recommended default for out-of-range or
	 * off-step integers. Used on read and repair so stored values can
	 * never bypass the schema bounds.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function normalize_settings( array $settings ) {
		$normalized = array();

		foreach ( self::setting_schema() as $key => $descriptor ) {
			$value              = array_key_exists( $key, $settings ) ? $settings[ $key ] : $descriptor['default'];
			$normalized[ $key ] = self::normalize_value( $value, $descriptor );
		}

		// Cross-field: the minimum submission time cannot exceed the token
		// lifetime, or every submission is flagged as too fast once the
		// token expires. Applied on every read and save so stored values
		// can never bypass the token lifetime.
		$max_min_time = $normalized['max_age_minutes'] * 60;

		if ( $normalized['min_time_seconds'] > $max_min_time ) {
			$normalized['min_time_seconds'] = $max_min_time;
		}

		return $normalized;
	}

	/**
	 * Apply a schema descriptor to a single value.
	 *
	 * @param mixed $value      Raw value.
	 * @param array $descriptor Schema descriptor.
	 * @return mixed
	 */
	private static function normalize_value( $value, array $descriptor ) {
		if ( 'bool' === $descriptor['type'] ) {
			return empty( $value ) ? 0 : 1;
		}

		if ( 'int' === $descriptor['type'] ) {
			return self::validate_step_int(
				$value,
				$descriptor['default'],
				isset( $descriptor['min'] ) ? $descriptor['min'] : 0,
				isset( $descriptor['max'] ) ? $descriptor['max'] : '',
				isset( $descriptor['step'] ) ? $descriptor['step'] : 1
			);
		}

		return is_string( $value ) ? $value : (string) $value;
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
		$current  = get_option( self::SETTINGS_OPTION, array() );
		$repaired = self::sanitize_global( is_array( $current ) ? $current : array() );

		if ( $repaired !== $current ) {
			update_option( self::SETTINGS_OPTION, $repaired, false );
		}

		self::$settings_cache = null;
	}

	/**
	 * Get report data merged with defaults.
	 *
	 * Reads the pre-aggregated summary calculated by cron or on-demand.
	 * Falls back to the legacy option for pre-upgrade data. Run-since
	 * is preserved in the meta option because it is not a counter.
	 *
	 * @return array
	 */
	public static function get_meta() {
		$existing = get_option( self::META_OPTION, array() );
		$stats    = self::default_meta();

		if ( is_array( $existing ) && ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		$summary = \SimpleHoneypotCF7\Reporting\Event_Logger::get_aggregated_stats();

		if ( ! empty( $summary['last_calculated'] ) ) {
			$stats['total']           = $summary['total'];
			$stats['reasons']         = $summary['reasons'];
			$stats['forms']           = $summary['forms'];
			$stats['last_calculated'] = $summary['last_calculated'];

			return $stats;
		}

		// No aggregated summary yet — check for legacy data.
		return self::meta_from_legacy( $existing );
	}

	/**
	 * Reset all reporting data.
	 *
	 * @return void
	 */
	public static function reset_meta() {
		\SimpleHoneypotCF7\Reporting\Event_Logger::reset_counters();
		\SimpleHoneypotCF7\Reporting\Event_Logger::delete_all();

		delete_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles' );

		// Preserve the original activation date.
		$existing = get_option( self::META_OPTION, array() );
		$stats    = self::default_meta();

		if ( is_array( $existing ) && ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		update_option( self::META_OPTION, $stats, false );
	}

	/**
	 * Build stats from the legacy option.
	 *
	 * On the first read after upgrade the aggregated summary may not
	 * exist yet. This method migrates any remaining legacy data into
	 * per-form options so it is not lost.
	 *
	 * @param array $existing The legacy stats option value.
	 * @return array
	 */
	private static function meta_from_legacy( array $existing ) {
		$stats = self::default_meta();

		if ( ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		// Check if per-form options exist from a partial migration.
		$form_stats = \SimpleHoneypotCF7\Reporting\Event_Logger::get_all_form_stats();

		if ( ! empty( $form_stats ) ) {
			// Per-form options exist but summary hasn't been calculated.
			// Run aggregation now.
			$summary                  = \SimpleHoneypotCF7\Reporting\Event_Logger::aggregate_summary();
			$stats['total']           = $summary['total'];
			$stats['reasons']         = $summary['reasons'];
			$stats['forms']           = $summary['forms'];
			$stats['last_calculated'] = $summary['last_calculated'];

			return $stats;
		}

		// Return whatever legacy data is available.
		if ( ! empty( $existing['total'] ) ) {
			$stats['total']   = absint( $existing['total'] );
			$stats['reasons'] = isset( $existing['reasons'] ) && is_array( $existing['reasons'] ) ? $existing['reasons'] : array();
			$stats['forms']   = isset( $existing['forms'] ) && is_array( $existing['forms'] ) ? $existing['forms'] : array();
		}

		return $stats;
	}

	/**
	 * Reset Settings-tab values to defaults.
	 * Rules-tab values, per-form settings, and reporting data are preserved.
	 *
	 * @return void
	 */
	public static function reset_settings() {
		$current  = get_option( self::SETTINGS_OPTION, array() );
		$defaults = self::default_settings();

		foreach ( self::settings_tab_keys() as $key ) {
			$current[ $key ] = $defaults[ $key ];
		}

		update_option( self::SETTINGS_OPTION, $current, false );

		self::$settings_cache = null;
	}

	/**
	 * Get per-form settings.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return array
	 */
	public static function get_form_settings( $form_id ) {
		$settings = $form_id ? get_post_meta( $form_id, self::FORM_META, true ) : array();

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
		update_post_meta( $form_id, self::FORM_META, self::normalize_form_settings( $settings ) );
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
	 * admin input bounds and per-form clamping.
	 *
	 * @return int
	 */
	public static function get_max_min_time_seconds() {
		$settings = self::get_settings();
		$schema   = self::setting_schema();

		return min( absint( $settings['max_age_minutes'] ) * 60, $schema['min_time_seconds']['max'] );
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
	 * Validate an integer setting against a step pattern and range, returning the default on failure.
	 *
	 * Non-numeric, negative, and non-integer (float/scientific) values fall
	 * back to the recommended default rather than being silently coerced.
	 *
	 * @param mixed      $value    Raw value to validate.
	 * @param int        $fallback Fallback when the value is invalid.
	 * @param int        $min      Minimum allowed value (inclusive).
	 * @param int|string $max      Maximum allowed value (inclusive), or empty for no upper bound.
	 * @param int        $step     Required step increment (e.g. 5 means only 0, 5, 10 … are valid).
	 * @return int Validated value.
	 */
	public static function validate_step_int( $value, $fallback, $min = 0, $max = '', $step = 1 ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		// Reject floats and scientific notation — only whole numbers pass.
		$int_value = filter_var( $value, FILTER_VALIDATE_INT );

		if ( false === $int_value ) {
			return $fallback;
		}

		$value = $int_value;

		// Guard against division by zero.
		if ( 1 > $step ) {
			$step = 1;
		}

		if ( $value < $min || ( '' !== $max && $value > $max ) || ( $value % $step ) !== 0 ) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Sanitize global settings from untrusted input.
	 *
	 * Applies the schema bounds to every key, then runs the line-based
	 * rules sanitizer on the custom rules textarea.
	 *
	 * @param array $settings Unslashed settings data.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_global( array $settings ) {
		// normalize_settings() clamps min_time_seconds to the token lifetime.
		$settings = self::normalize_settings( $settings );

		$settings['custom_rules'] = self::sanitize_rules( $settings['custom_rules'] );

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
