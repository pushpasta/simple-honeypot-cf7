<?php
/**
 * Admin notices.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

use SimpleHoneypotCF7\Support\Contact_Form_7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders admin notices.
 */
final class Notices {

	/**
	 * Render a notice.
	 *
	 * @param string $message     Notice message.
	 * @param string $type        Notice type: success, error, warning, info.
	 * @param bool   $dismissible Whether the notice can be dismissed.
	 * @return void
	 */
	public static function render( $message, $type = 'success', $dismissible = false ) {
		if ( empty( $message ) ) {
			return;
		}

		$allowed = array( 'success', 'error', 'warning', 'info' );
		$type    = in_array( $type, $allowed, true ) ? $type : 'success';

		$classes = array(
			'notice',
			'notice-' . $type,
		);

		if ( $dismissible ) {
			$classes[] = 'is-dismissible';
		}

		printf(
			'<div class="%s"><p>%s</p></div>',
			esc_attr( implode( ' ', $classes ) ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Show a notice when Contact Form 7 is unavailable.
	 *
	 * @return void
	 */
	public function contact_form_7_missing() {
		if ( Contact_Form_7::is_active() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$link = '<a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&s=contact+form+7' ) ) . '">' . esc_html__( 'Contact Form 7', 'simple-honeypot-cf7' ) . '</a>';

		self::render(
			sprintf(
				/* translators: %s: Contact Form 7 install link. */
				wp_kses_post( __( 'Simple Honeypot for Contact Form 7 requires %s to be installed and activated.', 'simple-honeypot-cf7' ) ),
				wp_kses_post( $link )
			),
			'error'
		);
	}

	/**
	 * Show a warning when Proof of Work is enabled but the site is not on HTTPS.
	 *
	 * @return void
	 */
	public function pow_requires_ssl() {
		if ( is_ssl() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = \SimpleHoneypotCF7\Settings::get_settings();

		if ( empty( $settings['pow_enabled'] ) ) {
			return;
		}

		self::render(
			__( 'Proof of Work requires HTTPS. This site does not appear to be served over a secure connection. PoW checks will be skipped for all submissions.', 'simple-honeypot-cf7' ),
			'warning'
		);
	}

	/**
	 * Show a success notice after resetting per-form settings.
	 *
	 * @return void
	 */
	public function reset_form_notice() {
		$notice = get_transient( SIMPLE_HONEYPOT_CF7_BASE . '_reset_notice' );

		if ( ! $notice || empty( $notice['form_id'] ) ) {
			return;
		}

		delete_transient( SIMPLE_HONEYPOT_CF7_BASE . '_reset_notice' );

		self::render(
			__( 'Per-form settings for this form have been restored to defaults.', 'simple-honeypot-cf7' ),
			'success',
			true
		);
	}

	/**
	 * Show a success notice after purging old events.
	 *
	 * @return void
	 */
	public function purge_events_notice() {
		$notice = get_transient( SIMPLE_HONEYPOT_CF7_BASE . '_purge_notice' );

		if ( ! $notice || ! is_array( $notice ) ) {
			return;
		}

		delete_transient( SIMPLE_HONEYPOT_CF7_BASE . '_purge_notice' );

		$removed = isset( $notice['removed'] ) ? absint( $notice['removed'] ) : 0;
		$days    = isset( $notice['days'] ) ? absint( $notice['days'] ) : 0;

		if ( 0 === $removed ) {
			$message = sprintf(
				/* translators: %d: number of days */
				esc_html__( 'No events older than %d days were found to delete.', 'simple-honeypot-cf7' ),
				$days
			);
		} else {
			$message = sprintf(
				/* translators: 1: number of deleted events, 2: number of days */
				esc_html( _n( 'Deleted %1$d event older than %2$d days.', 'Deleted %1$d events older than %2$d days.', $removed, 'simple-honeypot-cf7' ) ),
				$removed,
				$days
			);
		}

		self::render( $message, 'success', true );
	}
}
