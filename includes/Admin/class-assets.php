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
	 * Return the URL for an asset, using the minified version when available.
	 *
	 * @param string $relative_path Path relative to the plugin root, e.g. 'resources/admin/css/admin.css'.
	 * @return string The URL to the asset.
	 */
	private static function get_asset_url( $relative_path ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return SIMPLE_HONEYPOT_CF7_URL . $relative_path;
		}

		$min_path = preg_replace( '/\.(css|js)$/', '.min.$1', $relative_path );

		if ( defined( 'SIMPLE_HONEYPOT_CF7_PATH' ) && file_exists( SIMPLE_HONEYPOT_CF7_PATH . $min_path ) ) {
			return SIMPLE_HONEYPOT_CF7_URL . $min_path;
		}

		return SIMPLE_HONEYPOT_CF7_URL . $relative_path;
	}

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
			self::get_asset_url( 'resources/admin/css/admin.css' ),
			array(),
			SIMPLE_HONEYPOT_CF7_VERSION,
			'all'
		);

		wp_enqueue_script(
			'simple-honeypot-cf7-admin',
			self::get_asset_url( 'resources/admin/js/admin.js' ),
			array( 'jquery' ),
			SIMPLE_HONEYPOT_CF7_VERSION,
			true
		);

		wp_localize_script(
			'simple-honeypot-cf7-admin',
			'simpleHoneypotCf7',
			array(
				'unsavedChanges' => __( 'You have unsaved changes.', 'simple-honeypot-cf7' ),
				'confirmTitle'   => __( 'Are you sure?', 'simple-honeypot-cf7' ),
				'confirmYes'     => __( 'Yes', 'simple-honeypot-cf7' ),
				'confirmNo'      => __( 'No', 'simple-honeypot-cf7' ),
				/* translators: %s: minimum allowed value */
				'valueTooLow'    => __( 'Value must be at least %s.', 'simple-honeypot-cf7' ),
				/* translators: %s: maximum allowed value */
				'valueTooHigh'   => __( 'Value must be at most %s.', 'simple-honeypot-cf7' ),
				/* translators: %s: comma-separated list of unrecognized rule patterns */
				'invalidRules'   => __( 'Unrecognized rule pattern: %s', 'simple-honeypot-cf7' ),
				'selectFile'     => __( 'Please select a file to import.', 'simple-honeypot-cf7' ),
				'restUrl'        => rest_url( SIMPLE_HONEYPOT_CF7_BASE . '/v1/action' ),
				'restNonce'      => wp_create_nonce( 'wp_rest' ),
				'tabUrl'         => admin_url( 'admin.php?page=simple-honeypot-cf7&tab=tools' ),
			)
		);
	}
}
