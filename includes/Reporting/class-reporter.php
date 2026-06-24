<?php
/**
 * Spam reporting storage.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Reporting;

use SimpleHoneypotCF7\Settings;
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
		$settings   = Settings::get_settings();
		$stats      = Settings::get_stats();
		$form_id    = $contact_form && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
		$form_title = $contact_form && method_exists( $contact_form, 'title' ) ? wp_strip_all_tags( $contact_form->title() ) : __( 'Unknown form', 'simple-honeypot-cf7' );
		$form_key   = (string) $form_id;

		$stats['total'] = absint( $stats['total'] ) + 1;

		foreach ( $reasons as $reason ) {
			$type = sanitize_key( $reason['type'] );

			if ( empty( $stats['reasons'][ $type ] ) ) {
				$stats['reasons'][ $type ] = 0;
			}

			++$stats['reasons'][ $type ];
		}

		if ( empty( $stats['forms'][ $form_key ] ) || ! is_array( $stats['forms'][ $form_key ] ) ) {
			$stats['forms'][ $form_key ] = array(
				'title' => $form_title,
				'count' => 0,
			);
		}

		$stats['forms'][ $form_key ]['title'] = $form_title;
		$stats['forms'][ $form_key ]['count'] = absint( $stats['forms'][ $form_key ]['count'] ) + 1;

		Settings::update_stats( $stats );

		Event_Logger::insert(
			$form_id,
			$form_title,
			Request::remote_ip(),
			Request::user_agent(),
			array_map( array( $this, 'sanitize_reason' ), $reasons )
		);

		$purge_days = absint( $settings['purge_events_after_days'] );

		if ( $purge_days > 0 ) {
			Event_Logger::purge_old( $purge_days );
		}

		Event_Logger::purge_excess( $settings['keep_recent_events'] );
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
