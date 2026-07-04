<?php
/**
 * AJAX endpoint for fresh token and PoW challenge generation.
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
 * Serves fresh tokens and PoW challenges via AJAX.
 *
 * Tokens are never embedded in cached HTML. Instead, the client fetches
 * them on first form interaction. This ensures compatibility with
 * full-page caching plugins (WP Rocket, LiteSpeed, W3TC, etc.).
 */
final class Ajax_Token {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_shp4cf7_get_token', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_shp4cf7_get_token', array( $this, 'handle' ) );
	}

	/**
	 * Handle the AJAX token request.
	 *
	 * Returns a fresh token and optional PoW challenge for the given form.
	 * The token encodes timing data and dynamic honeypot field names for
	 * both the current and previous tick to handle cached-page edge cases.
	 *
	 * @return void
	 */
	public function handle() {
		check_ajax_referer( 'shp4cf7_token', 'nonce' );

		$form_id = absint( $_POST['form_id'] ?? 0 );

		if ( $form_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form.', 'simple-honeypot-cf7' ) ), 400 );
		}

		$contact_form = class_exists( '\WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_instance( $form_id ) : null;

		if ( ! $contact_form ) {
			wp_send_json_error( array( 'message' => __( 'Form not found.', 'simple-honeypot-cf7' ) ), 404 );
		}

		$settings = Settings::get_form_settings( $form_id );
		$max_age  = max( 10, absint( $settings['max_age_minutes'] ) ) * MINUTE_IN_SECONDS;

		$dynamic_names = $this->get_dynamic_names( $contact_form, $form_id );
		$token         = Token::generate_form_token( $form_id, $dynamic_names, $max_age );

		$result = array(
			'token' => $token,
		);

		if ( ! empty( $settings['pow_enabled'] ) && is_ssl() ) {
			$result['pow'] = Token::pow_challenge( $form_id, $settings );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Generate dynamic honeypot field names for the form.
	 *
	 * Produces names for both the current and previous tick to handle
	 * the edge case where a cached page was rendered in the previous tick.
	 *
	 * @param mixed $contact_form Contact Form 7 form object.
	 * @param int   $form_id      Form ID.
	 * @return array
	 */
	private function get_dynamic_names( $contact_form, $form_id ) {
		$tags = array();

		if ( $contact_form && method_exists( $contact_form, 'scan_form_tags' ) ) {
			$tags = $contact_form->scan_form_tags();
		}

		$honeypot_tags = array_values(
			array_filter(
				$tags,
				static function ( $tag ) {
					return isset( $tag->type ) && 'honeypot' === $tag->type;
				}
			)
		);

		if ( empty( $honeypot_tags ) ) {
			return array();
		}

		$existing_names = Contact_Form_7::get_field_names( $contact_form );
		$names          = array();

		foreach ( $honeypot_tags as $index => $tag ) {
			$names[]          = Token::dynamic_name( $form_id, $index, $existing_names );
			$existing_names[] = $names[ $index ];
		}

		return $names;
	}
}
