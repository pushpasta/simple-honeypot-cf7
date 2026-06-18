<?php
/**
 * Plugin activation.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

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
		Settings::activate();
	}
}
