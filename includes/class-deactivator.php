<?php
/**
 * Plugin deactivation.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

use SimpleHoneypotCF7\Reporting\Cron_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps deactivation intentionally non-destructive.
 */
final class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( Cron_Handler::HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, Cron_Handler::HOOK );
		}
	}
}
