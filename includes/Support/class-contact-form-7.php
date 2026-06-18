<?php
/**
 * Contact Form 7 availability helpers.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsulates checks against Contact Form 7.
 */
final class Contact_Form_7 {

	/**
	 * Check whether Contact Form 7 is active or loaded.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( defined( 'WPCF7_VERSION' ) || class_exists( '\WPCF7_ContactForm' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );
	}
}
