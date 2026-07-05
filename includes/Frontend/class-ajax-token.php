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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public endpoint returns short-lived signed form data.
		$form_id = absint( $_POST['form_id'] ?? 0 );

		if ( $form_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form.', 'simple-honeypot-cf7' ) ), 400 );
		}

		$contact_form = class_exists( '\WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_instance( $form_id ) : null;

		if ( ! $contact_form ) {
			wp_send_json_error( array( 'message' => __( 'Form not found.', 'simple-honeypot-cf7' ) ), 404 );
		}

		$settings = Settings::get_settings();
		$max_age  = max( 10, absint( $settings['max_age_minutes'] ) ) * MINUTE_IN_SECONDS;

		$honeypot_tags = $this->get_honeypot_tags( $contact_form );
		$dynamic_names = $this->get_dynamic_names( $contact_form, $form_id, count( $honeypot_tags ) );
		$token         = Token::generate_form_token( $form_id, $dynamic_names, $max_age );
		$token_field   = Token::tokens_field_name( $form_id );
		$expires_at    = time() + $max_age;

		$result = array(
			'dynamic_names'  => $dynamic_names,
			'honeypot_names' => wp_list_pluck( $honeypot_tags, 'name' ),
			'token'          => $token,
			'token_field'    => $token_field,
		);

		if ( ! empty( $settings['pow_enabled'] ) && is_ssl() ) {
			$result['pow']       = Token::pow_challenge( $form_id, $settings );
			$result['pow_field'] = $token_field . '_pow';
			$pow_expires_at      = ( (int) floor( time() / Token::POW_TICK ) + 2 ) * Token::POW_TICK;
			$expires_at          = min( $expires_at, $pow_expires_at );
		}

		$result['expires_in'] = max( 1, $expires_at - time() - 5 );

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
	 * @param int   $field_count  Number of honeypot fields.
	 * @return array
	 */
	private function get_dynamic_names( $contact_form, $form_id, $field_count ) {
		if ( $field_count <= 0 ) {
			return array();
		}

		$existing_names = Contact_Form_7::get_field_names( $contact_form );
		$names          = array();

		for ( $index = 0; $index < $field_count; $index++ ) {
			$names[]          = Token::dynamic_name( $form_id, $index, $existing_names );
			$existing_names[] = $names[ $index ];
		}

		return $names;
	}

	/**
	 * Get the form's honeypot tags.
	 *
	 * @param mixed $contact_form Contact Form 7 form object.
	 * @return array
	 */
	private function get_honeypot_tags( $contact_form ) {
		$tags = array();

		if ( $contact_form && method_exists( $contact_form, 'scan_form_tags' ) ) {
			$tags = $contact_form->scan_form_tags();
		}

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
