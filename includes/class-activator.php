<?php
/**
 * Plugin activation.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

use SimpleHoneypotCF7\Reporting\Event_Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates initial plugin data.
 */
final class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		Upgrader::run();
		Settings::activate();
		self::setup_events_table();
		self::opt_in_auto_updates();
	}

	/**
	 * Create the events table and migrate legacy data if present.
	 *
	 * @return void
	 */
	private static function setup_events_table() {
		Event_Logger::create_table();
		Event_Logger::migrate_from_options( Settings::STATS_OPTION );
	}

	/**
	 * Enable auto-updates by default for this plugin.
	 *
	 * Adds the plugin to the auto_update_plugins option so the WordPress
	 * UI shows it as enabled while still letting users toggle it off.
	 *
	 * @return void
	 */
	private static function opt_in_auto_updates() {
		$auto_updates = get_site_option( 'auto_update_plugins', array() );

		if ( in_array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME, $auto_updates, true ) ) {
			return;
		}

		$auto_updates[] = SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME;
		update_site_option( 'auto_update_plugins', $auto_updates );
	}
}
