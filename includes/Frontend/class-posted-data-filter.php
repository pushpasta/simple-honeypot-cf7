<?php
/**
 * Posted data cleanup.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Frontend;

use SimpleHoneypotCF7\Settings;
use SimpleHoneypotCF7\Support\Contact_Form_7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes honeypot internals from Contact Form 7 posted data.
 */
final class Posted_Data_Filter {

	/**
	 * Filter Contact Form 7 posted data.
	 *
	 * @param array $posted_data Posted form data.
	 * @return array
	 */
	public function filter( $posted_data ) {
		if ( ! is_array( $posted_data ) ) {
			return $posted_data;
		}

		$settings     = Settings::get_settings();
		$contact_form = class_exists( '\WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_current() : null;
		$form_id      = $contact_form && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
		$prefix       = Token::form_prefix( $form_id );

		// Build the list of valid field names from form tags.
		$valid_names = $this->get_valid_field_names( $contact_form );

		// Add dynamic honeypot field names so they are not removed.
		foreach ( Token::posted_tokens( $form_id ) as $token ) {
			$data = Token::validate( $token, $form_id );

			if ( ! empty( $data['dynamic_name'] ) ) {
				$valid_names[] = sanitize_key( $data['dynamic_name'] );
			}
		}

		// Remove keys that are not recognized form fields (buttons, CF7 meta, etc.).
		if ( ! empty( $valid_names ) ) {
			foreach ( array_keys( $posted_data ) as $key ) {
				if ( ! in_array( $key, $valid_names, true ) ) {
					unset( $posted_data[ $key ] );
				}
			}
		}

		// Remove honeypot-prefixed fields.
		if ( $prefix ) {
			foreach ( array_keys( $posted_data ) as $key ) {
				if ( 0 === strpos( (string) $key, $prefix . '_' ) ) {
					unset( $posted_data[ $key ] );
				}
			}
		}

		foreach ( Token::posted_tokens( $form_id ) as $token ) {
			$data = Token::validate( $token, $form_id );

			if ( empty( $data['dynamic_name'] ) ) {
				continue;
			}

			$dynamic_name = sanitize_key( $data['dynamic_name'] );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
			$value = isset( $_POST[ $dynamic_name ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $dynamic_name ] ) ) : '';

			unset( $posted_data[ $dynamic_name ] );

			if ( ! empty( $settings['store_honeypot_value'] ) && '' !== $value ) {
				$posted_data[ 'honeypot_' . sanitize_key( $data['field_name'] ) ] = $value;
			}
		}

		return $posted_data;
	}

	/**
	 * Collect valid field names from the contact form's tags.
	 *
	 * @param mixed $contact_form Contact Form 7 form object.
	 * @return array List of valid field names.
	 */
	private function get_valid_field_names( $contact_form ) {
		return Contact_Form_7::get_field_names( $contact_form );
	}
}
