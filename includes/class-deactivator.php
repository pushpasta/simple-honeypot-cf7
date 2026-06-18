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
	}
}
