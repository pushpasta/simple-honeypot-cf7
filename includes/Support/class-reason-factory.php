<?php
/**
 * Shared reason factory for spam detection results.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds consistent reason arrays used by Spam_Checker and Rules.
 */
final class Reason_Factory {

	/**
	 * Build a reason entry.
	 *
	 * @param string $type    Reason type.
	 * @param string $message Human-readable message.
	 * @param string $field   Field name (optional).
	 * @param string $value   Matched or submitted value (optional).
	 * @return array{type: string, message: string, field: string, value: string}
	 */
	public static function create( $type, $message, $field = '', $value = '' ) {
		return array(
			'type'    => sanitize_key( $type ),
			'message' => wp_strip_all_tags( $message ),
			'field'   => sanitize_key( $field ),
			'value'   => String_Helper::truncate( $value ),
		);
	}
}
