<?php
/**
 * Request helpers.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared request utilities.
 */
final class Request {

	/**
	 * Get the remote IP address.
	 *
	 * @return string
	 */
	public static function remote_ip() {
		$ip = function_exists( 'wp_get_request_ip' ) ? wp_get_request_ip() : null;

		if ( empty( $ip ) ) {
			$ip = empty( $_SERVER['REMOTE_ADDR'] ) ? '' : wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}

		return sanitize_text_field( $ip );
	}

	/**
	 * Get the current user agent.
	 *
	 * @return string
	 */
	public static function user_agent() {
		$ua = empty( $_SERVER['HTTP_USER_AGENT'] ) ? '' : sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		return mb_substr( $ua, 0, 256 );
	}
}
