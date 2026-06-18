<?php
/**
 * Plugin rules module.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Rules;

use SimpleHoneypotCF7\Support\String_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom rule parsing and matching engine.
 */
final class Rules {
	use String_Helper;

	/**
	 * Check posted data against user-defined rules.
	 *
	 * @param array $settings     Plugin settings.
	 * @param array $posted_data  Submitted form data.
	 * @param mixed $ip           Visitor IP address.
	 * @param array $email_fields Known email-type field names.
	 * @return array
	 */
	public static function check( array $settings, array $posted_data, $ip, array $email_fields = array() ) {
		$reasons = array();

		if ( empty( $settings['custom_rules_enabled'] ) || empty( $settings['custom_rules'] ) ) {
			return $reasons;
		}

		$email_values = self::get_email_values( $posted_data, $email_fields );

		foreach ( self::parse( $settings['custom_rules'] ) as $rule ) {
			if ( self::matches( $rule, $ip, $email_values ) ) {
				$reasons[] = self::reason(
					'custom_rule_' . $rule['type'],
					sprintf(
						/* translators: 1: rule type, 2: rule pattern. */
						__( 'Submission matched custom rule for %1$s: %2$s', 'simple-honeypot-cf7' ),
						$rule['type'],
						$rule['label']
					),
					$rule['label']
				);
			}
		}

		return $reasons;
	}

	/**
	 * Parse rules text into an array of typed rules.
	 *
	 * @param string $rules Raw rules textarea content.
	 * @return array
	 */
	public static function parse( $rules ) {
		$parsed = array();
		$lines  = preg_split( '/\r\n|\r|\n/', (string) $rules );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$type = self::detect_type( $line );

			if ( '' === $type ) {
				continue;
			}

			$parsed[] = array(
				'type'    => $type,
				'pattern' => $line,
				'label'   => self::truncate( $line ),
			);
		}

		return $parsed;
	}

	/**
	 * Detect rule type from pattern format.
	 *
	 * @param string $pattern Rule line.
	 * @return string
	 */
	private static function detect_type( $pattern ) {
		if ( false !== strpos( $pattern, '@' ) ) {
			return 'email';
		}

		// IPv4-like: must start with a digit and contain a dot.
		if ( preg_match( '/^\d[\d\.\*\/]+$/', $pattern ) && false !== strpos( $pattern, '.' ) ) {
			return 'ip';
		}

		// IPv6-like: hex chars, colons, slashes; must have at least two colons.
		if ( preg_match( '/^[0-9a-fA-F:\*\/]+$/', $pattern ) && substr_count( $pattern, ':' ) >= 2 ) {
			return 'ip';
		}

		return '';
	}

	/**
	 * Extract email values from posted data.
	 *
	 * @param array $posted_data  Submitted form data.
	 * @param array $email_fields Known email-type field names.
	 * @return array
	 */
	private static function get_email_values( array $posted_data, array $email_fields ) {
		$values = array();

		if ( ! empty( $email_fields ) ) {
			foreach ( $email_fields as $field ) {
				if ( isset( $posted_data[ $field ] ) ) {
					$field_values = (array) $posted_data[ $field ];

					foreach ( $field_values as $v ) {
						if ( is_scalar( $v ) ) {
							$values[] = sanitize_text_field( (string) $v );
						}
					}
				}
			}
		} else {
			array_walk_recursive(
				$posted_data,
				static function ( $value ) use ( &$values ) {
					if ( is_scalar( $value ) && is_email( $value ) ) {
						$values[] = sanitize_text_field( (string) $value );
					}
				}
			);
		}

		return array_unique( $values );
	}

	/**
	 * Check if a rule matches current submission data.
	 *
	 * @param array $rule         Parsed rule.
	 * @param mixed $ip           Visitor IP address.
	 * @param array $email_values Extracted email values.
	 * @return bool
	 */
	private static function matches( array $rule, $ip, array $email_values ) {
		switch ( $rule['type'] ) {
			case 'ip':
				return self::matches_ip( $rule['pattern'], $ip );
			case 'email':
				return self::matches_email_rule( $rule['pattern'], $email_values );
		}

		return false;
	}

	/**
	 * Match IP pattern against visitor IP.
	 *
	 * @param string $pattern IP rule pattern.
	 * @param mixed  $ip      Visitor IP address.
	 * @return bool
	 */
	private static function matches_ip( $pattern, $ip ) {
		if ( '' === $pattern || '' === $ip ) {
			return false;
		}

		if ( false !== strpos( $pattern, '/' ) ) {
			return self::matches_cidr( $pattern, $ip );
		}

		return self::matches_wildcard( $pattern, $ip );
	}

	/**
	 * Match CIDR range against visitor IP.
	 *
	 * Supports both IPv4 and IPv6 CIDR notation.
	 *
	 * @param string $cidr CIDR notation (e.g. "192.168.0.0/24" or "2001:db8::/32").
	 * @param mixed  $ip   Visitor IP address.
	 * @return bool
	 */
	private static function matches_cidr( $cidr, $ip ) {
		list( $network, $bits ) = array_pad( explode( '/', $cidr, 2 ), 2, null );

		if ( '' === $network || '' === $ip ) {
			return false;
		}

		$network_packed = filter_var( $network, FILTER_VALIDATE_IP ) ? inet_pton( $network ) : false;
		$ip_packed      = filter_var( $ip, FILTER_VALIDATE_IP ) ? inet_pton( $ip ) : false;

		if ( false === $network_packed || false === $ip_packed ) {
			return false;
		}

		$bits = absint( $bits );
		$size = strlen( $network_packed ) * 8;

		if ( $bits < 0 || $bits > $size ) {
			return false;
		}

		if ( 0 === $bits ) {
			return true;
		}

		$mask = str_repeat( "\xff", (int) ( $bits / 8 ) );

		if ( $bits % 8 ) {
			$mask .= chr( 0xff << ( 8 - $bits % 8 ) );
		}

		$mask = str_pad( $mask, $size, "\0" );

		return substr( $ip_packed & $mask, 0, strlen( $mask ) ) === substr( $network_packed & $mask, 0, strlen( $mask ) );
	}

	/**
	 * Match email pattern against extracted email values.
	 *
	 * @param string $pattern      Email rule pattern.
	 * @param array  $email_values Extracted email values.
	 * @return bool
	 */
	private static function matches_email_rule( $pattern, array $email_values ) {
		if ( '' === $pattern || empty( $email_values ) ) {
			return false;
		}

		foreach ( $email_values as $email ) {
			if ( self::matches_wildcard( $pattern, $email ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match pattern with wildcard support against target.
	 *
	 * @param string $pattern Pattern containing optional * wildcards.
	 * @param string $target  String to check.
	 * @return bool
	 */
	private static function matches_wildcard( $pattern, $target ) {
		if ( '' === $pattern || '' === $target ) {
			return false;
		}

		$regex = preg_quote( $pattern, '/' );
		$regex = str_replace( '\*', '.*', $regex );
		$regex = '/^' . $regex . '$/i';

		return 1 === preg_match( $regex, $target );
	}

	/**
	 * Build a reason entry for matched rules.
	 *
	 * @param string $type    Reason type.
	 * @param string $message Human-readable message.
	 * @param string $value   Matched value.
	 * @return array
	 */
	private static function reason( $type, $message, $value = '' ) {
		return array(
			'type'    => sanitize_key( $type ),
			'message' => wp_strip_all_tags( $message ),
			'field'   => '',
			'value'   => self::truncate( $value ),
		);
	}
}
