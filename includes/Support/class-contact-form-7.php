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

	/**
	 * Collect field names from a Contact Form 7 form.
	 *
	 * @param mixed $contact_form Contact Form 7 form object.
	 * @return array List of field names.
	 */
	public static function get_field_names( $contact_form ) {
		if ( ! $contact_form || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return array();
		}

		$names = array();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( ! empty( $tag->name ) ) {
				$names[] = $tag->name;
			}
		}

		return $names;
	}
}
