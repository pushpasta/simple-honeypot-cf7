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
	 * Cron hook name.
	 *
	 * @var string
	 */
	const HOOK = 'shp4cf7_purge_events';

	/**
	 * Register the cron hook callback.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * Run both purge strategies based on current settings.
	 *
	 * @return void
	 */
	public static function run() {
		$settings = Settings::get_settings();

		$purge_days = absint( $settings['purge_events_after_days'] );

		if ( $purge_days > 0 ) {
			Event_Logger::purge_aged_events( $purge_days );
		}

		Event_Logger::purge_excess_events( $settings['keep_recent_events'] );
	}
}
