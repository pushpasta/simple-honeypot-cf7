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
		wp_clear_scheduled_hook( Cron_Handler::HOOK );
		wp_clear_scheduled_hook( Cron_Handler::STATS_HOOK );
	}
}
