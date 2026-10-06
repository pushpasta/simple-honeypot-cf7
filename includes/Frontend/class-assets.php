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
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wpcf7_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wpcf7_after_form', array( $this, 'add_noscript_notice' ), 10, 1 );
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
				'prefix'  => '_' . SIMPLE_HONEYPOT_CF7_BASE,
			)
		);
	}

	/**
	 * Add a noscript notice informing visitors that JavaScript is required.
	 *
	 * Only shown when JavaScript is disabled; the plugin requires JS to fetch
	 * tokens for forms that use the honeypot field.
	 *
	 * @param \WPCF7_ContactForm|null $contact_form Contact Form 7 form instance.
	 * @return void
	 */
	public function add_noscript_notice( $contact_form = null ) {
		$notice = __( 'JavaScript is required to submit this form. Please enable JavaScript in your browser and try again.', 'simple-honeypot-cf7' );
		echo '<noscript><p class="wpcf7-response-output wpcf7-validation-errors" role="alert">' . esc_html( $notice ) . '</p></noscript>';
	}
}
