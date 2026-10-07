<?php
/**
 * IP address with optional external lookup link.
 *
 * Expects $ip and $settings in scope. Renders the IP as a link to the
 * configured lookup URL or as plain text when linking is disabled.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ip_lookup_enabled = ! empty( $settings['ip_lookup_enabled'] ) && ! empty( $settings['ip_lookup_url'] );

if ( '' !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) && $ip_lookup_enabled ) {
	$lookup_href = str_ireplace( '%ip%', rawurlencode( $ip ), $settings['ip_lookup_url'] );
	/* translators: %s: IP address being looked up */
	$lookup_title = sprintf( __( 'Look up this IP address: %s', 'simple-honeypot-cf7' ), $ip );
	printf(
		'<a href="%s" target="_blank" rel="noopener noreferrer" title="%s">%s</a>',
		esc_url( $lookup_href ),
		esc_attr( $lookup_title ),
		esc_html( $ip )
	);
} else {
	echo esc_html( $ip );
}
