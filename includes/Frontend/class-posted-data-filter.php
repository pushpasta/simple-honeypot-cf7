<?php
/**
 * Posted data cleanup.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Frontend;

use SimpleHoneypotCF7\Settings;

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

		foreach ( $posted_data as $key => $value ) {
			if ( $prefix && 0 === strpos( (string) $key, $prefix . '_' ) ) {
				unset( $posted_data[ $key ] );
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
}
