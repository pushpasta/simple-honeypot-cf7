<?php
/**
 * String utility helpers.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared string formatting helpers for use across the plugin.
 */
trait String_Helper {

	/**
	 * Shorten a value for logs and reports.
	 *
	 * @param string $value Value to truncate.
	 * @param int    $length Maximum length. Defaults to setting or 100.
	 * @return string
	 */
	protected function short_value( $value, $length = 0 ) {
		if ( $length <= 0 ) {
			$settings = \SimpleHoneypotCF7\Settings::get_settings();
			$length   = max( 10, min( 200, absint( $settings['honeypot_value_max_length'] ) ) );
		}

		$value = sanitize_textarea_field( (string) $value );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}

	/**
	 * Sanitize and truncate a value to 200 characters.
	 *
	 * @param string $value Value to truncate.
	 * @return string
	 */
	public static function truncate( $value ) {
		$value = sanitize_textarea_field( (string) $value );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 200 );
		}

		return substr( $value, 0, 200 );
	}
}
