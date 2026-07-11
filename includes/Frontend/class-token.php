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

	const SIGN_PREFIX     = SIMPLE_HONEYPOT_CF7_BASE . '|token|sign|';
	const NAME_PREFIX     = SIMPLE_HONEYPOT_CF7_BASE . '|token|dname|';
	const TICK_SECONDS    = HOUR_IN_SECONDS;
	const FIELD_TYPES     = array( 'text', 'email', 'tel', 'url', 'number', 'date', 'textarea' );
	const POW_TICK        = 300; // 5-minute PoW challenge window.
	const POW_SIGN_PREFIX = SIMPLE_HONEYPOT_CF7_BASE . '|pow|sign|';
	const CONSUMED_OPTION = SIMPLE_HONEYPOT_CF7_BASE . '_consumed';

	const HIDING_STYLES = array(
		'position:absolute!important;left:-10000px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;',
		'position:fixed!important;top:-9999px!important;left:0!important;width:1px!important;height:1px!important;overflow:hidden!important;',
		'position:absolute!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;height:1px!important;width:1px!important;overflow:hidden!important;',
		'position:absolute!important;transform:scale(0)!important;transform-origin:0 0!important;width:1px!important;height:1px!important;overflow:hidden!important;',
	);

	/**
	 * Derive a site-specific AES key from the WordPress auth salt.
	 *
	 * @return string 16-byte key for AES-128.
	 */
	private static function encryption_key() {
		return substr( wp_hash( wp_salt( 'auth' ), 'auth' ), 0, 16 );
	}

	/**
	 * Encrypt a payload using AES-128-CTR.
	 *
	 * @param string $plaintext Data to encrypt.
	 * @return string Base64url-encoded ciphertext.
	 */
	private static function encrypt( $plaintext ) {
		$key       = self::encryption_key();
		$iv        = substr( wp_hash( $key . '|iv' ), 0, 16 );
		$encrypted = openssl_encrypt( $plaintext, 'aes-128-ctr', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used to encode encrypted ciphertext for transport.
		return rtrim( base64_encode( $encrypted ), '=' );
	}

	/**
	 * Decrypt a payload encrypted with encrypt().
	 *
	 * @param string $ciphertext Base64url-encoded ciphertext.
	 * @return string|false Decrypted plaintext, or false on failure.
	 */
	private static function decrypt( $ciphertext ) {
		$key = self::encryption_key();
		$iv  = substr( wp_hash( $key . '|iv' ), 0, 16 );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Used to decode encrypted ciphertext for decryption.
		$decoded = base64_decode( strtr( $ciphertext, '-_', '+/' ), true );

		if ( false === $decoded ) {
			return false;
		}

		return openssl_decrypt( $decoded, 'aes-128-ctr', $key, OPENSSL_RAW_DATA, $iv );
	}

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
	 * Generate a single form-level token that encodes timing data and all dynamic names.
	 *
	 * Token format: {created_at}.{form_id}.{dynamic_names_csv}.{max_age}.{hmac_signature}
	 *
	 * @param int   $form_id       Contact Form 7 form ID.
	 * @param array $dynamic_names List of dynamic field names for honeypot fields.
	 * @param int   $max_age       Token lifetime in seconds.
	 * @return string
	 */
	public static function generate_form_token( $form_id, array $dynamic_names, $max_age ) {
		$payload   = implode( '.', array( time(), (int) $form_id, implode( ',', $dynamic_names ), (int) $max_age ) );
		$encrypted = self::encrypt( $payload );

		if ( '' === $encrypted ) {
			return '';
		}

		return $encrypted . '.' . wp_hash( self::SIGN_PREFIX . $payload );
	}

	/**
	 * Validate a form-level token and return its data.
	 *
	 * @param string $token           Token string.
	 * @param int    $current_form_id Current form ID for ownership check (0 to skip).
	 * @return array Empty array on failure, or data array on success.
	 */
	public static function validate_form_token( $token, $current_form_id = 0 ) {
		$cache_key = 'form|' . $token . '|' . (int) $current_form_id;

		if ( array_key_exists( $cache_key, self::$validate_cache ) ) {
			return self::$validate_cache[ $cache_key ];
		}

		$parts = explode( '.', $token );

		if ( 2 !== count( $parts ) ) {
			self::$validate_cache[ $cache_key ] = array();
			return array();
		}

		list( $encoded_payload, $signature ) = $parts;

		$payload = self::decrypt( $encoded_payload );

		if ( false === $payload ) {
			self::$validate_cache[ $cache_key ] = array();
			return array();
		}

		$expected = wp_hash( self::SIGN_PREFIX . $payload );

		if ( ! hash_equals( $expected, $signature ) ) {
			self::$validate_cache[ $cache_key ] = array();
			return array();
		}

		$token_parts = explode( '.', $payload );

		if ( 4 !== count( $token_parts ) ) {
			self::$validate_cache[ $cache_key ] = array();
			return array();
		}

		$created_at        = (int) $token_parts[0];
		$form_id           = (int) $token_parts[1];
		$dynamic_names_csv = $token_parts[2];
		$max_age           = (int) $token_parts[3];

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
			'created_at'    => $created_at,
			'form_id'       => $form_id,
			'dynamic_names' => array_filter( explode( ',', $dynamic_names_csv ) ),
			'max_age'       => $max_age,
		);

		self::$validate_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Memoized form prefix cache.
	 *
	 * @var array<int, string>
	 */
	private static $prefix_cache = array();

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
		$form_id = (int) $form_id;

		if ( isset( self::$prefix_cache[ $form_id ] ) ) {
			return self::$prefix_cache[ $form_id ];
		}

		$hash  = wp_hash( SIMPLE_HONEYPOT_CF7_BASE . '|fprefix|' . $form_id );
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$name  = '';

		for ( $i = 0; $i < 10; $i++ ) {
			$name .= $chars[ hexdec( substr( $hash, $i * 2, 2 ) ) % 36 ];
		}

		self::$prefix_cache[ $form_id ] = $name;

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
	 * @param int   $tick           Optional tick override (defaults to current tick).
	 * @return string
	 */
	public static function dynamic_name( $form_id, $field_index = 0, array $existing_names = array(), $tick = 0 ) {
		if ( $tick <= 0 ) {
			$tick = (int) floor( time() / self::TICK_SECONDS );
		}

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
		$index = hexdec( substr( wp_hash( SIMPLE_HONEYPOT_CF7_BASE . '|hide|' . (int) $form_id . '|' . $tick . '|' . $field_index ), 0, 2 ) ) % count( self::HIDING_STYLES );
		return self::HIDING_STYLES[ $index ];
	}

	/**
	 * Generate dynamic names for a specific tick and field count.
	 *
	 * Used by validators to try the previous tick's names when the current
	 * tick's names don't match (cache boundary edge case).
	 *
	 * @param int   $form_id Contact Form 7 form ID.
	 * @param int   $count   Number of honeypot fields.
	 * @param int   $tick    Tick to generate names for.
	 * @param array $existing_names Optional list of field names already in use.
	 * @return array
	 */
	public static function generate_names_for_tick( $form_id, $count, $tick, array $existing_names = array() ) {
		$names = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$names[]          = self::dynamic_name( $form_id, $i, $existing_names, $tick );
			$existing_names[] = $names[ $i ];
		}

		return $names;
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
		$complexity = empty( $settings['pow_complexity'] ) ? 14 : max( 4, min( 20, absint( $settings['pow_complexity'] ) ) );
		$seed       = substr( wp_hash( SIMPLE_HONEYPOT_CF7_BASE . '|pow|seed|' . (int) $form_id . '|' . $tick ), 0, 16 );
		$payload    = implode( '.', array( $seed, $complexity, $tick, (int) $form_id ) );

		return $payload . '.' . wp_hash( self::POW_SIGN_PREFIX . $payload );
	}

	/**
	 * Verify a Proof-of-Work answer.
	 *
	 * @param string $challenge       The challenge string from the hidden field.
	 * @param string $answer          The PoW nonce submitted by the client.
	 * @param int    $expected_form_id Expected Contact Form 7 form ID (prevents cross-form reuse).
	 * @return bool
	 */
	public static function verify_pow( $challenge, $answer, $expected_form_id = 0 ) {
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

		// Bind the embedded form ID to the expected form ID.
		if ( $expected_form_id > 0 && (int) $form_id !== $expected_form_id ) {
			return false;
		}

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

		return self::verify_pow( $challenge, $answer, $form_id );
	}

	/**
	 * Check if the token field was submitted in the POST request.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return bool
	 */
	public static function has_token_field( $form_id = 0 ) {
		$field = self::tokens_field_name( $form_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
		return isset( $_POST[ $field ] );
	}

	/**
	 * Check if the token field was submitted but is empty.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return bool
	 */
	public static function token_field_empty( $form_id = 0 ) {
		$field = self::tokens_field_name( $form_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
		return empty( $_POST[ $field ] );
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
		if ( empty( $_POST[ $field ] ) ) {
			return array();
		}

		$raw = wp_unslash( $_POST[ $field ] );

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( is_array( $raw ) ) {
			$tokens = array_map( 'sanitize_text_field', $raw );
		} else {
			$tokens = array( sanitize_text_field( (string) $raw ) );
		}

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

	/**
	 * Mark a token as consumed to prevent replay attacks.
	 *
	 * Stores a hashed fingerprint of the token with an expiry matching
	 * the token's max_age. Once consumed, the same token cannot be used
	 * for a second submission within that window.
	 *
	 * @param string $token   Raw token string.
	 * @param int    $max_age Token lifetime in seconds.
	 * @return void
	 */
	public static function consume( $token, $max_age ) {
		$consumed = get_option( self::CONSUMED_OPTION, array() );
		$hash     = wp_hash( $token );
		$expires  = time() + $max_age;

		// Prune expired entries on write.
		$now      = time();
		$consumed = array_filter(
			$consumed,
			static function ( $entry ) use ( $now ) {
				return $entry['e'] > $now;
			}
		);

		$consumed[ $hash ] = array( 'e' => $expires );
		update_option( self::CONSUMED_OPTION, $consumed, 'no' );
	}

	/**
	 * Check whether a token has already been consumed.
	 *
	 * @param string $token Raw token string.
	 * @return bool
	 */
	public static function is_consumed( $token ) {
		$consumed = get_option( self::CONSUMED_OPTION, array() );
		$hash     = wp_hash( $token );
		$now      = time();

		// Prune expired entries on read.
		$pruned = array_filter(
			$consumed,
			static function ( $entry ) use ( $now ) {
				return $entry['e'] > $now;
			}
		);

		if ( $pruned !== $consumed ) {
			update_option( self::CONSUMED_OPTION, $pruned, 'no' );
		}

		return isset( $pruned[ $hash ] );
	}
}
