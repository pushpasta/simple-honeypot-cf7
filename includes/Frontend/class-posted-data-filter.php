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
	 * Register Contact Form 7 hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'wpcf7_posted_data', array( $this, 'filter' ), 20, 1 );
	}

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
		$tokens = Token::posted_tokens( $form_id );

		if ( ! empty( $tokens ) ) {
			$token_data = Token::validate_form_token( $tokens[0], $form_id );

			if ( ! empty( $token_data['dynamic_names'] ) ) {
				foreach ( $token_data['dynamic_names'] as $dynamic_name ) {
					$valid_names[] = sanitize_key( $dynamic_name );
				}

				/*
				 * Handle tick boundary: also add previous tick's names
				 * so they are not stripped from posted data.
				 */
				$current_tick  = (int) floor( time() / Token::TICK_SECONDS );
				$prev_tick     = $current_tick - 1;
				$honeypot_tags = $this->get_honeypot_tags( $contact_form );
				$prev_names    = Token::generate_names_for_tick( $form_id, count( $honeypot_tags ), $prev_tick, $valid_names );

				foreach ( $prev_names as $prev_name ) {
					$valid_names[] = sanitize_key( $prev_name );
				}
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

		$tokens = Token::posted_tokens( $form_id );

		if ( ! empty( $tokens ) ) {
			$token_data = Token::validate_form_token( $tokens[0], $form_id );

			if ( ! empty( $token_data['dynamic_names'] ) ) {
				$honeypot_tags = $this->get_honeypot_tags( $contact_form );
				$current_tick  = (int) floor( time() / Token::TICK_SECONDS );
				$prev_names    = Token::generate_names_for_tick( $form_id, count( $honeypot_tags ), $current_tick - 1, $valid_names );

				foreach ( $honeypot_tags as $index => $tag ) {
					$dynamic_name = isset( $token_data['dynamic_names'][ $index ] ) ? $token_data['dynamic_names'][ $index ] : '';

					if ( '' === $dynamic_name && isset( $prev_names[ $index ] ) ) {
						$dynamic_name = $prev_names[ $index ];
					}

					if ( '' === $dynamic_name ) {
						continue;
					}

					$dynamic_name = sanitize_key( $dynamic_name );

					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading Contact Form 7 submission data.
					$value = isset( $_POST[ $dynamic_name ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $dynamic_name ] ) ) : '';

					if ( mb_strlen( $value ) > 200 ) {
						$value = mb_substr( $value, 0, 200 );
					}

					unset( $posted_data[ $dynamic_name ] );

					if ( ! empty( $settings['store_honeypot_value'] ) && '' !== $value ) {
						$posted_data[ 'honeypot_' . sanitize_key( $tag->name ) ] = $value;
					}
				}
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

	/**
	 * Get honeypot tags from the contact form.
	 *
	 * @param mixed $contact_form Contact Form 7 form object.
	 * @return array List of honeypot form tags.
	 */
	private function get_honeypot_tags( $contact_form ) {
		if ( ! $contact_form || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return array();
		}

		$tags = $contact_form->scan_form_tags();

		return array_values(
			array_filter(
				$tags,
				static function ( $tag ) {
					return isset( $tag->type ) && 'honeypot' === $tag->type;
				}
			)
		);
	}
}
