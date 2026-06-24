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
 * Displays admin notices.
 */
final class Notices {

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

		echo '<div class="notice notice-error"><p>';
		printf(
			/* translators: %s: Contact Form 7 install link. */
			wp_kses_post( __( 'Simple Honeypot for Contact Form 7 requires %s to be installed and activated.', 'simple-honeypot-cf7' ) ),
			wp_kses_post( $link )
		);
		echo '</p></div>';
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

		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'Proof of Work requires HTTPS. It is currently enabled but this site does not appear to be served over a secure connection. PoW checks will be skipped for all submissions.', 'simple-honeypot-cf7' );
		echo '</p></div>';
	}

	/**
	 * Show a success notice after resetting per-form settings.
	 *
	 * @return void
	 */
	public function reset_form_notice() {
		$notice = get_transient( 'simple_honeypot_cf7_reset_notice' );

		if ( ! $notice || empty( $notice['form_id'] ) ) {
			return;
		}

		delete_transient( 'simple_honeypot_cf7_reset_notice' );

		echo '<div class="notice notice-success is-dismissible"><p>';
		esc_html_e( 'Simple Honeypot settings for this form have been restored to defaults.', 'simple-honeypot-cf7' );
		echo '</p></div>';
	}
}
