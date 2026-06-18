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
	 * @return string
	 */
	protected function short_value( $value ) {
		return self::truncate( $value );
	}

	/**
	 * Sanitize and truncate a value to 160 characters.
	 *
	 * @param string $value Value to truncate.
	 * @return string
	 */
	public static function truncate( $value ) {
		$value = sanitize_textarea_field( (string) $value );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 160 );
		}

		return substr( $value, 0, 160 );
	}
}
