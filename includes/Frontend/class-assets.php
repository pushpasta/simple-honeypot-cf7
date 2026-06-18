<?php
/**
 * Frontend assets.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Frontend;

use SimpleHoneypotCF7\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues scripts for Proof-of-Work on the frontend.
 */
final class Assets {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wpcf7_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue PoW script when enabled in settings.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! defined( 'SIMPLE_HONEYPOT_CF7_URL' ) ) {
			return;
		}

		$settings = Settings::get_settings();

		if ( empty( $settings['pow_enabled'] ) ) {
			return;
		}

		wp_enqueue_script(
			'simple-honeypot-cf7-frontend',
			SIMPLE_HONEYPOT_CF7_URL . 'resources/frontend/js/frontend.js',
			array(),
			SIMPLE_HONEYPOT_CF7_VERSION,
			true
		);
	}
}
