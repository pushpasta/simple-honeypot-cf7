<?php
/**
 * Spam reporting storage.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Reporting;

use SimpleHoneypotCF7\Support\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records blocked submissions for the admin report screen.
 */
final class Reporter {

	/**
	 * Store one blocked submission.
	 *
	 * @param mixed $contact_form Contact Form 7 form object.
	 * @param array $reasons      Spam reasons.
	 * @return void
	 */
	public function record_spam_attempt( $contact_form, array $reasons ) {
		$form_id    = $contact_form && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
		$form_title = $contact_form && method_exists( $contact_form, 'title' ) ? wp_strip_all_tags( $contact_form->title() ) : __( 'Unknown form', 'simple-honeypot-cf7' );

		// Atomic increments — concurrent requests never overwrite each other.
		Event_Logger::increment_counter( 'total' );

		foreach ( $reasons as $reason ) {
			Event_Logger::increment_counter( 'reason:' . sanitize_key( $reason['type'] ) );
		}

		Event_Logger::increment_counter( 'form:' . $form_id );

		// Keep form titles for the display layer.
		$form_titles = get_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles', array() );

		if ( ! isset( $form_titles[ $form_id ] ) || $form_titles[ $form_id ] !== $form_title ) {
			$form_titles[ $form_id ] = $form_title;
			update_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles', $form_titles, false );
		}

		Event_Logger::insert(
			$form_id,
			$form_title,
			Request::remote_ip(),
			Request::user_agent(),
			array_map( array( $this, 'sanitize_reason' ), $reasons )
		);
	}

	/**
	 * Sanitize a reason before storage.
	 *
	 * @param array $reason Spam reason.
	 * @return array
	 */
	private function sanitize_reason( array $reason ) {
		return array(
			'type'    => sanitize_key( isset( $reason['type'] ) ? $reason['type'] : '' ),
			'message' => wp_strip_all_tags( isset( $reason['message'] ) ? $reason['message'] : '' ),
			'field'   => sanitize_key( isset( $reason['field'] ) ? $reason['field'] : '' ),
			'value'   => sanitize_text_field( isset( $reason['value'] ) ? $reason['value'] : '' ),
		);
	}
}
