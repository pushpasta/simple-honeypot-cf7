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
	 * Minimum supported Contact Form 7 version.
	 *
	 * @var string
	 */
	const MIN_VERSION = '6.0';

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
	 * Get the loaded Contact Form 7 version.
	 *
	 * @return string Version number, or an empty string when CF7 is not loaded.
	 */
	public static function get_version() {
		if ( defined( 'WPCF7_VERSION' ) ) {
			return WPCF7_VERSION;
		}

		return '';
	}

	/**
	 * Check whether the loaded Contact Form 7 version meets the minimum.
	 *
	 * @return bool
	 */
	public static function has_minimum_version() {
		$version = self::get_version();

		return '' !== $version && version_compare( $version, self::MIN_VERSION, '>=' );
	}

	/**
	 * Check whether Contact Form 7 is active and meets the minimum version.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return self::is_active() && self::has_minimum_version();
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
