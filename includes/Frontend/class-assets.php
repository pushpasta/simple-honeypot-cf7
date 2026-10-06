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
		add_filter( 'wpcf7_form_response_output', array( $this, 'add_noscript_notice' ), 10, 1 );
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
	 * Append a noscript notice informing visitors that JavaScript is required.
	 *
	 * Only shown when JavaScript is disabled; the plugin requires JS to fetch
	 * tokens for forms that use the honeypot field. The notice uses a
	 * plugin-owned class because Contact Form 7 renders the init class on the
	 * form server-side, which keeps wpcf7-response-output hidden by CSS even
	 * without JavaScript.
	 *
	 * @param string $output Contact Form 7 response output markup.
	 * @return string
	 */
	public function add_noscript_notice( $output ) {
		$contact_form = class_exists( '\WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_current() : null;

		if ( ! $contact_form || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return $output;
		}

		$honeypot_tags = array_filter(
			$contact_form->scan_form_tags(),
			static function ( $tag ) {
				return isset( $tag->type ) && 'honeypot' === $tag->type;
			}
		);

		if ( empty( $honeypot_tags ) ) {
			return $output;
		}

		$notice = __( 'JavaScript is required to submit this form. Please enable JavaScript in your browser and try again.', 'simple-honeypot-cf7' );

		return $output . '<noscript><p class="shp4cf7-noscript-notice" role="alert">' . esc_html( $notice ) . '</p></noscript>';
	}
}
