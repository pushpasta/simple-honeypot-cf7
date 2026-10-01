<?php
/**
 * Plugin rules module.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Rules;

use SimpleHoneypotCF7\Support\Reason_Factory;
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
	 * Maximum subject length passed to wildcard matching.
	 *
	 * IPv6 addresses are at most 45 characters and email addresses at most
	 * 254 (RFC 5321), so anything longer cannot be a legitimate match.
	 *
	 * @var int
	 */
	const MAX_MATCH_LENGTH = 254;

	/**
	 * Option name prefix for the cached parse of the custom rules.
	 *
	 * Suffixed with the hash of the rules text to make the entry
	 * content-addressed. Kept short because WordPress limits option
	 * names to 191 characters.
	 *
	 * @var string
	 */
	const PARSED_CACHE_PREFIX = 'shp4cf7_parsed_rules_';

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

		foreach ( self::parsed_rules( $settings['custom_rules'] ) as $rule ) {
			if ( self::matches( $rule, $ip, $email_values ) ) {
				$reasons[] = Reason_Factory::create(
					'custom_rule_' . $rule['type'],
					sprintf(
						/* translators: 1: rule type, 2: rule pattern. */
						__( 'Submission matched custom rule for %1$s: %2$s', 'simple-honeypot-cf7' ),
						$rule['type'],
						$rule['label']
					),
					'',
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

			// Strip consecutive wildcards (e.g. "192.168.**.1" → "192.168.*.1").
			$line = preg_replace( '/\*{2,}/', '*', $line );

			// Auto-prepend * to bare email domain patterns (e.g. "@example.com" → "*@example.com").
			if ( 'email' === $type && 0 === strpos( $line, '@' ) ) {
				$line = '*' . $line;
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
	 * Get the parsed custom rules, reusing a cached parse when possible.
	 *
	 * Parsing runs detect_type() and sanitize_textarea_field() once per rule
	 * line. The result is a pure function of the rules text, and that text
	 * only changes when an admin saves the Rules tab, so re-parsing on every
	 * submission repeats a large amount of work for a constant result.
	 *
	 * The cache key is the hash of the rules text. Editing a rule changes the
	 * hash, so a stale entry is never looked up again rather than having to be
	 * invalidated in place, and a cache hit is always correct because the key
	 * covers the entire input. Superseded entries are removed by their expiry
	 * rather than explicitly, which avoids coupling Settings to this class for
	 * housekeeping alone.
	 *
	 * @param string $raw Raw rules textarea content.
	 * @return array Parsed rules.
	 */
	private static function parsed_rules( $raw ) {
		$raw    = (string) $raw;
		$option = self::PARSED_CACHE_PREFIX . md5( $raw );

		$cached = get_transient( $option );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$parsed = self::parse( $raw );

		set_transient( $option, $parsed, DAY_IN_SECONDS );

		return $parsed;
	}

	/**
	 * Detect rule type from pattern format.
	 *
	 * @param string $pattern Rule line.
	 * @return string 'ip', 'email', or ''.
	 */
	public static function detect_type( $pattern ) {
		if ( false !== strpos( $pattern, '@' ) ) {
			return 'email';
		}

		// IPv4-like: strip CIDR and wildcards, then validate with filter_var.
		$parts = explode( '/', $pattern, 2 );
		$base  = str_replace( '*', '0', $parts[0] );

		if ( false !== strpos( $pattern, '.' ) && filter_var( $base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return 'ip';
		}

		// IPv6-like: same approach — $base already computed above.
		if ( substr_count( $pattern, ':' ) >= 2 && filter_var( $base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
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
		list( $network, $bits ) = array_pad( explode( '/', $cidr, 2 ), 2, '' );

		if ( '' === $network || '' === $ip ) {
			return false;
		}

		$network_packed = filter_var( $network, FILTER_VALIDATE_IP ) ? inet_pton( $network ) : false;
		$ip_packed      = filter_var( $ip, FILTER_VALIDATE_IP ) ? inet_pton( $ip ) : false;

		if ( false === $network_packed || false === $ip_packed ) {
			return false;
		}

		$size = strlen( $network_packed ) * 8;

		if ( '' === $bits || ! preg_match( '/^\d+$/', $bits ) ) {
			return false;
		}

		$bits = (int) $bits;

		if ( $bits < 1 || $bits > $size ) {
			return false;
		}

		$mask = str_repeat( "\xff", (int) ( $bits / 8 ) );

		if ( $bits % 8 ) {
			$mask .= chr( 0xff << ( 8 - $bits % 8 ) );
		}

		$mask = str_pad( $mask, $size / 8, "\0" );

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
	 * Wildcards are resolved by walking the literal segments in order
	 * instead of compiling a `.*` regex. Compiled `.*` runs can backtrack
	 * catastrophically against long subjects — a pattern such as
	 * `*a*a*a*a*a*` is the classic example — whereas this walk has no
	 * backtracking engine and is O( target length * segments ) for every
	 * input.
	 *
	 * Semantics are identical to the anchored regex this replaced
	 * (`/^preg_quote( pattern, with * replaced by .* )$/i`): the first
	 * and last literal segments are anchored to the start and end of the
	 * target, and any segments in between must appear in order.
	 *
	 * @param string $pattern Pattern containing optional * wildcards.
	 * @param string $target  String to check.
	 * @return bool
	 */
	private static function matches_wildcard( $pattern, $target ) {
		if ( '' === $pattern || '' === $target ) {
			return false;
		}

		// IPv6 addresses are at most 45 characters and email addresses at
		// most 254 (RFC 5321), so a longer target cannot be a legitimate
		// match. Bounding it also bounds the work per rule.
		if ( strlen( $target ) > self::MAX_MATCH_LENGTH ) {
			return false;
		}

		// Without a wildcard the pattern is a plain case-insensitive
		// comparison. Real blocklists are mostly exact IPs and addresses,
		// so this is the common path and it avoids the regex engine.
		if ( false === strpos( $pattern, '*' ) ) {
			return 0 === strcasecmp( $pattern, $target );
		}

		$segments = explode( '*', $pattern );
		$first    = array_shift( $segments );
		$last     = array_pop( $segments );
		$offset   = strlen( $first );

		// The anchored first and last segments must fit inside the target.
		// This check is what removes the degenerate a*a*a*a family: such a
		// pattern is rejected on length before any searching happens, so
		// there is nothing left to backtrack over.
		if ( strlen( $target ) < $offset + strlen( $last ) ) {
			return false;
		}

		if ( '' !== $first && 0 !== strncasecmp( $target, $first, $offset ) ) {
			return false;
		}

		if ( '' !== $last && 0 !== strcasecmp( substr( $target, - strlen( $last ) ), $last ) ) {
			return false;
		}

		// Segments in between must appear in order, each one starting where
		// the previous ended and finishing before the trailing segment
		// begins. Overlap is not allowed: in "*a*a" the two a's are distinct
		// characters, so the pattern does not match "a".
		$limit = strlen( $target ) - strlen( $last );

		foreach ( $segments as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			$found = stripos( $target, $segment, $offset );

			if ( false === $found || $found + strlen( $segment ) > $limit ) {
				return false;
			}

			$offset = $found + strlen( $segment );
		}

		return true;
	}
}
