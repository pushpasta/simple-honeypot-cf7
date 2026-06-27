<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'SIMPLE_HONEYPOT_CF7_PATH' ) ) {
	define( 'SIMPLE_HONEYPOT_CF7_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME' ) ) {
	define( 'SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'SIMPLE_HONEYPOT_CF7_BASE' ) ) {
	define( 'SIMPLE_HONEYPOT_CF7_BASE', 'shp4cf7' );
}

require_once SIMPLE_HONEYPOT_CF7_PATH . 'includes/class-autoloader.php';

\SimpleHoneypotCF7\Autoloader::register();
\SimpleHoneypotCF7\Settings::uninstall();
