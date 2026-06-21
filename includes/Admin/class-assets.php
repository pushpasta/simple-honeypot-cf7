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
		if ( false === strpos( $hook, 'simple-honeypot-cf7' ) && false === strpos( $hook, 'wpcf7' ) && false === strpos( $hook, 'toplevel_page_wpcf7' ) ) {
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

		wp_localize_script(
			'simple-honeypot-cf7-admin',
			'simpleHoneypotCf7',
			array(
				'unsavedChanges' => __( 'You have unsaved changes.', 'simple-honeypot-cf7' ),
				'confirmTitle'   => __( 'Are you sure?', 'default' ),
				'confirmYes'     => __( 'Yes', 'default' ),
				'confirmNo'      => __( 'No', 'default' ),
				/* translators: %s: minimum allowed value */
				'valueTooLow'    => __( 'Value must be at least %s.', 'simple-honeypot-cf7' ),
				/* translators: %s: maximum allowed value */
				'valueTooHigh'   => __( 'Value must be at most %s.', 'simple-honeypot-cf7' ),
				/* translators: %s: comma-separated list of unrecognized rule patterns */
				'invalidRules'   => __( 'Unrecognized rule pattern: %s', 'simple-honeypot-cf7' ),
			)
		);
	}
}
