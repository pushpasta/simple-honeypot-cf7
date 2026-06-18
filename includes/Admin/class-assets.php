<?php
/**
 * Admin assets.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues styles and scripts on plugin admin screens.
 */
final class Assets {

	/**
	 * Enqueue admin assets when needed.
	 *
	 * @param string $hook Current admin screen hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( $hook, 'simple-honeypot-cf7' ) && false === strpos( $hook, 'wpcf7' ) ) {
			return;
		}

		wp_enqueue_style(
			'simple-honeypot-cf7-admin',
			SIMPLE_HONEYPOT_CF7_URL . 'resources/admin/css/admin.css',
			array(),
			SIMPLE_HONEYPOT_CF7_VERSION,
			'all'
		);

		wp_enqueue_script(
			'simple-honeypot-cf7-admin',
			SIMPLE_HONEYPOT_CF7_URL . 'resources/admin/js/admin.js',
			array( 'jquery' ),
			SIMPLE_HONEYPOT_CF7_VERSION,
			true
		);
	}
}
