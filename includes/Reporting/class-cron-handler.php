<?php
/**
 * WP-Cron handler for deferred maintenance tasks.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Reporting;

use SimpleHoneypotCF7\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and processes scheduled cleanup tasks outside the request path.
 */
final class Cron_Handler {

	/**
	 * Register the cron hook callback.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'shp4cf7_purge_excess', array( __CLASS__, 'purge_excess' ) );
	}

	/**
	 * Read settings and run purge_excess.
	 *
	 * @return void
	 */
	public static function purge_excess() {
		$settings = Settings::get_settings();
		Event_Logger::purge_excess( $settings['keep_recent_events'] );
	}
}
