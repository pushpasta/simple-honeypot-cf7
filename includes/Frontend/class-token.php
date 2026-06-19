<?php
/**
 * Self-contained signed tokens for honeypot validation.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and validates self-contained honeypot tokens (nonce-style, no transients).
 *
 * Tokens encode timestamp, form ID, field name, dynamic name, and max age,
 * signed with wp_hash(). Validation recomputes the HMAC — no database storage needed.
 */
final class Token {

	/**
	 * Validation cache to avoid recomputing HMAC for the same token.
	 *
	 * @var array<string, array>
	 */
	private static $validate_cache = array();

	const SIGN_PREFIX     = 'shcf7|token|sign|';
	const NAME_PREFIX     = 'shcf7|token|dname|';
	const TICK_SECONDS    = HOUR_IN_SECONDS;
	const FIELD_TYPES     = array( 'text', 'email', 'tel', 'url', 'number', 'date', 'textarea' );
	const POW_TICK        = 300; // 5-minute PoW challenge window.
	const POW_SIGN_PREFIX = 'shcf7|pow|sign|';

	const HIDING_STYLES = array(
		'position:absolute!important;left:-10000px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;',
		'position:fixed!important;top:-9999px!important;left:0!important;width:1px!important;height:1px!important;overflow:hidden!important;',
		'position:absolute!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;height:1px!important;width:1px!important;overflow:hidden!important;',
		'position:absolute!important;transform:scale(0)!important;transform-origin:0 0!important;width:1px!important;height:1px!important;overflow:hidden!important;',
	);

	/**
	 * Generate a self-contained signed token.
	 *
	 * @param int    $form_id      Contact Form 7 form ID.
	 * @param string $field_name   Honeypot form tag name.
	 * @param string $dynamic_name Dynamic field name for this instance.
	 * @param int    $max_age      Token lifetime in seconds.
	 * @return string
	 */
	public static function generate( $form_id, $field_name, $dynamic_name, $max_age ) {
		$payload = implode( '.', array( time(), (int) $form_id, $field_name, $dynamic_name, (int) $max_age ) );
		return $payload . '.' . wp_hash( self::SIGN_PREFIX . $payload );
	}

	/**
	 * Token format: {created_at}.{form_id}.{field_name}.{dynamic_name}.{max_age}.{hmac_signature}
	 *
	 * Validation steps:
	 * 1. Split on '.' → expect 6 parts.
	 * 2. Recompute HMAC over the first 5 parts; compare with constant-time hash_equals().
	 * 3. Check expiration: token must not be from the future (+60s clock drift) or beyond max_age.
	 * 4. If a form_id is provided, verify token belongs to that form.
	 */

	/**
	 * Validate a token and return its data.
	 *
	 * @param string $token           Token string.
	 * @param int    $current_form_id Current form ID for ownership check (0 to skip).
	 * @return array Empty array on failure, or data array on success.
	 */
	public static function validate( $token, $current_form_id = 0 ) {
		$cache_key = $token . '|' . (int) $current_form_id;

		if ( array_key_exists( $cache_key, self::$validate_cache ) ) {
			return self::$validate_cache[ $cache_key ];
		}

		$parts = explode( '.', $token );

		if ( count( $parts ) !== 6 ) {
			self::$validate_cache[ $cache_key ] = array();
			return array();
		}

		list( $created_at, $form_id, $field_name, $dynamic_name, $max_age, $signature ) = $parts;

		$payload  = implode( '.', array( $created_at, $form_id, $field_name, $dynamic_name, $max_age ) );
		$expected = wp_hash( self::SIGN_PREFIX . $payload );

		if ( ! hash_equals( $expected, $signature ) ) {
			self::$validate_cache[ $cache_key ] = array();
			return array();
		}

		$created_at = (int) $created_at;
		$max_age    = (int) $max_age;
		$form_id    = (int) $form_id;

		$now = time();

		if ( $created_at > $now + 60 || $now - $created_at > $max_age ) {
			self::$validate_cache[ $cache_key ] = array();
			return array();
		}

		if ( $current_form_id && $form_id && $form_id !== $current_form_id ) {
			self::$validate_cache[ $cache_key ] = array();
			return array();
		}

		$result = array(
			'created_at'   => $created_at,
			'form_id'      => $form_id,
			'field_name'   => $field_name,
			'dynamic_name' => $dynamic_name,
			'max_age'      => $max_age,
		);

		self::$validate_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Get the form-specific field prefix.
	 *
	 * A fully random-looking alphanumeric string derived from the site salt
	 * and form ID — no static prefix. Varies per site and form, so the same
	 * plugin on different sites produces different prefixes for the same form.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return string
	 */
	public static function form_prefix( $form_id ) {
		$hash  = wp_hash( 'shcf7|fprefix|' . (int) $form_id );
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$name  = '';

		for ( $i = 0; $i < 10; $i++ ) {
			$name .= $chars[ hexdec( substr( $hash, $i * 2, 2 ) ) % 36 ];
		}

		return $name;
	}

	/**
	 * The prefix (10 chars) + '_t' form the hidden array input name.
	 * The PoW hidden input uses the same prefix + '_t_pow'.
	 * Both are stripped from submitted data by Posted_Data_Filter via prefix-match.
	 */

	/**
	 * Get the hidden tokens POST field name for a form.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return string
	 */
	public static function tokens_field_name( $form_id ) {
		return self::form_prefix( $form_id ) . '_t';
	}

	/**
	 * Generate a deterministic field name that looks like a CF7 auto-named field.
	 *
	 * Produces names in CF7's native `{type}-{number}` format (e.g. `text-14837`),
	 * changing every TICK_SECONDS so the HTML stays cacheable. Avoids collisions
	 * with existing field names on the form.
	 *
	 * @param int   $form_id        Contact Form 7 form ID.
	 * @param int   $field_index    Index of this honeypot field within the form (0-based).
	 * @param array $existing_names Optional list of field names already in use.
	 * @return string
	 */
	public static function dynamic_name( $form_id, $field_index = 0, array $existing_names = array() ) {
		$tick = (int) floor( time() / self::TICK_SECONDS );
		$hash = wp_hash( self::NAME_PREFIX . (int) $form_id . '|' . $tick . '|' . $field_index );

		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$offset = $attempt * 6;
			$type   = self::FIELD_TYPES[ hexdec( substr( $hash, $offset, 2 ) ) % count( self::FIELD_TYPES ) ];
			$num    = hexdec( substr( $hash, $offset + 2, 4 ) ) % 90000 + 10000;
			$name   = $type . '-' . $num;

			if ( ! in_array( $name, $existing_names, true ) ) {
				return $name;
			}
		}

		return 'input-' . $tick;
	}

	/**
	 * Return a CSS hiding style for a form, rotated per time tick and field index.
	 *
	 * @param int $form_id     Contact Form 7 form ID.
	 * @param int $field_index Index of this honeypot field within the form (0-based).
	 * @return string
	 */
	public static function hiding_style( $form_id, $field_index = 0 ) {
		$tick  = (int) floor( time() / self::TICK_SECONDS );
		$index = hexdec( substr( wp_hash( 'shcf7|hide|' . (int) $form_id . '|' . $tick . '|' . $field_index ), 0, 2 ) ) % count( self::HIDING_STYLES );
		return self::HIDING_STYLES[ $index ];
	}

	/**
	 * Challenge format: {seed}.{complexity}.{tick}.{form_id}.{hmac_signature}
	 * The seed is deterministic within POW_TICK, making the challenge cacheable.
	 * The client appends the answer as a 6th dot-delimited part when submitting.
	 */

	/**
	 * Generate a Proof-of-Work challenge for a form.
	 *
	 * The challenge is deterministic within the POW_TICK window so the HTML
	 * stays cacheable. The client JS must compute a hash with leading zero
	 * bits matching the required complexity.
	 *
	 * @param int   $form_id    Contact Form 7 form ID.
	 * @param array $settings   Plugin settings.
	 * @return string
	 */
	public static function pow_challenge( $form_id, array $settings = array() ) {
		$tick       = (int) floor( time() / self::POW_TICK );
		$complexity = empty( $settings['pow_complexity'] ) ? 8 : max( 4, min( 20, absint( $settings['pow_complexity'] ) ) );
		$seed       = substr( wp_hash( 'shcf7|pow|seed|' . (int) $form_id . '|' . $tick ), 0, 16 );
		$payload    = implode( '.', array( $seed, $complexity, $tick, (int) $form_id ) );

		return $payload . '.' . wp_hash( self::POW_SIGN_PREFIX . $payload );
	}

	/**
	 * Verify a Proof-of-Work answer.
	 *
	 * @param string $challenge The challenge string from the hidden field.
	 * @param string $answer    The PoW nonce submitted by the client.
	 * @return bool
	 */
	public static function verify_pow( $challenge, $answer ) {
		$parts = explode( '.', $challenge );

		if ( count( $parts ) !== 5 ) {
			return false;
		}

		list( $seed, $complexity, $tick, $form_id, $signature ) = $parts;

		$payload  = implode( '.', array( $seed, $complexity, $tick, $form_id ) );
		$expected = wp_hash( self::POW_SIGN_PREFIX . $payload );

		if ( ! hash_equals( $expected, $signature ) ) {
			return false;
		}

		$tick       = (int) $tick;
		$complexity = (int) $complexity;
		$current    = (int) floor( time() / self::POW_TICK );

		// Allow current and previous tick (grace for cached pages).
		if ( $tick !== $current && $tick !== $current - 1 ) {
			return false;
		}

		if ( $complexity < 4 || $complexity > 20 ) {
			return false;
		}

		$answer = preg_replace( '/[^0-9]/', '', (string) $answer );

		if ( '' === $answer ) {
			return false;
		}

		$input = $challenge . '.' . $answer;
		$hash  = hash( 'sha256', $input, false );
		$bits  = self::leading_zero_bits( $hash );

		return $bits >= $complexity;
	}

	/**
	 * Count leading zero bits in a hex hash string.
	 *
	 * @param string $hash Hex-encoded hash.
	 * @return int
	 */
	private static function leading_zero_bits( $hash ) {
		$bits = 0;
		$len  = strlen( $hash );

		for ( $i = 0; $i < $len; $i++ ) {
			$byte = hexdec( $hash[ $i ] );

			if ( 0 === $byte ) {
				$bits += 4;
				continue;
			}

			$nibble = $byte;

			if ( $nibble & 8 ) {
				return $bits;
			}
			if ( $nibble & 4 ) {
				return $bits + 1;
			}
			if ( $nibble & 2 ) {
				return $bits + 2;
			}
			if ( $nibble & 1 ) {
				return $bits + 3;
			}

			return $bits + 4;
		}

		return $bits;
	}

	/**
	 * Validate the Proof-of-Work challenge from POST data for a form.
	 *
	 * Reads the PoW hidden field from $_POST, extracts the challenge
	 * and answer, and delegates to verify_pow().
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return bool
	 */
	public static function check_pow( $form_id ) {
		$field = self::tokens_field_name( $form_id ) . '_pow';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
		if ( empty( $_POST[ $field ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_pow = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		$parts   = explode( '.', $raw_pow );

		if ( count( $parts ) !== 6 ) {
			return false;
		}

		$challenge = implode( '.', array_slice( $parts, 0, 5 ) );
		$answer    = (string) $parts[5];

		return self::verify_pow( $challenge, $answer );
	}

	/**
	 * Return sanitised token values from the current POST request.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return array
	 */
	public static function posted_tokens( $form_id = 0 ) {
		$field = self::tokens_field_name( $form_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
		if ( empty( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) ) {
			return array();
		}

		$tokens = array_map( 'wp_unslash', (array) $_POST[ $field ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return array_values(
			array_filter(
				array_map(
					static function ( $token ) {
						return self::sanitize( $token );
					},
					$tokens
				)
			)
		);
	}

	/**
	 * Sanitize and validate a raw posted token value.
	 *
	 * @param string $raw Raw token from POST data.
	 * @return string Empty string if invalid.
	 */
	public static function sanitize( $raw ) {
		$token = sanitize_text_field( (string) $raw );

		if ( strlen( $token ) < 40 || strlen( $token ) > 300 ) {
			return '';
		}

		return $token;
	}
}
