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
	 * Return the URL for an asset, using the minified version when available.
	 *
	 * @param string $relative_path Path relative to the plugin root, e.g. 'resources/frontend/js/token-fetch.js'.
	 * @return string The URL to the asset.
	 */
	private static function get_asset_url( $relative_path ) {
		$min_path = preg_replace( '/\.(css|js)$/', '.min.$1', $relative_path );

		if ( defined( 'SIMPLE_HONEYPOT_CF7_PATH' ) && file_exists( SIMPLE_HONEYPOT_CF7_PATH . $min_path ) ) {
			return SIMPLE_HONEYPOT_CF7_URL . $min_path;
		}

		return SIMPLE_HONEYPOT_CF7_URL . $relative_path;
	}

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
			self::get_asset_url( 'resources/frontend/js/token-fetch.js' ),
			array(),
			SIMPLE_HONEYPOT_CF7_VERSION,
			true
		);

		wp_localize_script(
			'simple-honeypot-cf7-token-fetch',
			'shp4cf7',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'prefix'  => SIMPLE_HONEYPOT_CF7_BASE,
			)
		);
	}
}
