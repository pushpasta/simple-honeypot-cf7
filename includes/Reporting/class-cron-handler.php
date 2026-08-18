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
	 * Cron hook name for event purging.
	 *
	 * @var string
	 */
	const HOOK = 'shp4cf7_purge_events';

	/**
	 * Cron hook name for stats aggregation.
	 *
	 * @var string
	 */
	const STATS_HOOK = 'shp4cf7_aggregate_stats';

	/**
	 * Register the cron hook callbacks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( self::STATS_HOOK, array( __CLASS__, 'aggregate_stats' ) );
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

	/**
	 * Aggregate per-form stats into the summary option.
	 *
	 * @return void
	 */
	public static function aggregate_stats() {
		Event_Logger::aggregate_summary();
	}
}
