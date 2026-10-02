<?php
/**
 * Stateless string helpers.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared string helpers used across the plugin.
 *
 * A class rather than a trait: every helper here is stateless and static,
 * so there is no behaviour to share with a using class. This was previously
 * a trait, which made it possible to call a static method through the trait
 * name, a construct PHP 8.5 deprecated. Keeping the helpers on a class makes
 * the call site explicit and gives callers one documented truncation rule.
 */
final class String_Helper {

	/**
	 * Truncate a string to a character length.
	 *
	 * Deliberately pure: callers sanitize their own input before truncating,
	 * so the same helper serves both log-value bounds and utf8mb4 column
	 * bounds without hiding a sanitizer inside a utility.
	 *
	 * Truncating by characters rather than bytes matters for storage. A
	 * VARCHAR(250) column holds 250 characters at up to 4 bytes each, so an
	 * over-long INSERT under strict-mode MySQL would otherwise fail and drop
	 * the value. The encoding is passed explicitly rather than inherited from
	 * mb_internal_encoding().
	 *
	 * @param string $value  Value to truncate.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	public static function truncate( $value, $length ) {
		$value = (string) $value;

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $value, 'UTF-8' ) <= $length ) {
				return $value;
			}

			return mb_substr( $value, 0, $length, 'UTF-8' );
		}

		return substr( $value, 0, $length );
	}
}
