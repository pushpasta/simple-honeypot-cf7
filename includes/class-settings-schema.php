<?php
/**
 * Settings schema, defaults, and value validation.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for settings shape and sanitization.
 */
final class Settings_Schema {

	/**
	 * Define the global settings schema.
	 *
	 * Single source of truth for defaults, validation bounds, and tab
	 * placement. Integer settings may define 'min', 'max', and 'step';
	 * out-of-range or off-step integer values fall back to the 'default'
	 * (recommended) value. All consumers — saves, imports, reads, upgrades,
	 * and the backend UI — derive their behavior from this map.
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
			'ip_lookup_enabled'         => array(
				'type'    => 'bool',
				'default' => 1,
				'tab'     => 'settings',
			),
			'ip_lookup_url'             => array(
				'type'    => 'string',
				'default' => 'https://www.abuseipdb.com/check/%ip%',
				'tab'     => 'settings',
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
	 * Validate an integer setting against a step pattern and range, returning the default on failure.
	 *
	 * Non-numeric, out-of-range, off-step, and non-integer (float or
	 * scientific notation) values fall back to the recommended default
	 * rather than being silently coerced. Whole numbers with leading
	 * zeros are accepted and normalized so values that passed the import
	 * path's integer check are not silently reset.
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

		// Accept whole numbers, including leading zeros, and reject floats
		// and scientific notation before casting.
		$value = trim( (string) $value );

		if ( ! preg_match( '/^[+-]?\d+$/', $value ) ) {
			return $fallback;
		}

		$value = (int) $value;

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
	 * Validate an untrusted value against a schema descriptor.
	 *
	 * Used on import paths where values arrive from JSON rather than
	 * POST data. Type mismatches fall back to the schema default
	 * instead of being coerced: booleans must be true/false or 1/0
	 * (int or string), integers must be whole numbers within the
	 * descriptor's min/max/step bounds, and strings must actually be
	 * strings.
	 *
	 * @param mixed $value      Raw value.
	 * @param array $descriptor Schema descriptor from setting_schema().
	 * @return mixed The validated value, or the schema default when invalid.
	 */
	public static function validate_typed_value( $value, array $descriptor ) {
		if ( 'bool' === $descriptor['type'] ) {
			if ( ! in_array( $value, array( true, false, 1, 0, '1', '0' ), true ) ) {
				return $descriptor['default'];
			}

			return empty( $value ) ? 0 : 1;
		}

		if ( 'int' === $descriptor['type'] ) {
			$is_whole = is_int( $value )
				|| ( is_string( $value ) && 0 < strlen( $value ) && preg_match( '/^\d+$/', $value ) );

			if ( ! $is_whole ) {
				return $descriptor['default'];
			}

			return self::validate_step_int(
				$value,
				$descriptor['default'],
				isset( $descriptor['min'] ) ? $descriptor['min'] : 0,
				isset( $descriptor['max'] ) ? $descriptor['max'] : '',
				isset( $descriptor['step'] ) ? $descriptor['step'] : 1
			);
		}

		// string type — used by custom_rules.
		return is_string( $value ) ? $value : $descriptor['default'];
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

		$lookup_url = trim( (string) $settings['ip_lookup_url'] );

		if ( '' !== $lookup_url ) {
			$lookup_url = esc_url_raw( $lookup_url );

			if ( false === wp_http_validate_url( $lookup_url ) || false === stripos( $lookup_url, '%ip%' ) ) {
				$lookup_url = self::setting_schema()['ip_lookup_url']['default'];
			}
		}

		if ( ! empty( $settings['ip_lookup_enabled'] ) && '' === $lookup_url ) {
			$lookup_url = self::setting_schema()['ip_lookup_url']['default'];
		}

		$settings['ip_lookup_url'] = $lookup_url;

		return $settings;
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
}
