<?php
/**
 * Plugin deactivation.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

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
		$timestamp = wp_next_scheduled( 'shp4cf7_purge_excess' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'shp4cf7_purge_excess' );
		}
	}
}
