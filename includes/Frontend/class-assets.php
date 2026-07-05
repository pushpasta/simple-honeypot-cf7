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
 * Enqueues frontend scripts for token fetching and proof-of-work.
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
	 * Enqueue frontend scripts.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! defined( 'SIMPLE_HONEYPOT_CF7_URL' ) ) {
			return;
		}

		wp_enqueue_script(
			'simple-honeypot-cf7-token-fetch',
			SIMPLE_HONEYPOT_CF7_URL . 'resources/frontend/js/token-fetch.js',
			array(),
			SIMPLE_HONEYPOT_CF7_VERSION,
			true
		);

		wp_localize_script(
			'simple-honeypot-cf7-token-fetch',
			'shp4cf7',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}
}
