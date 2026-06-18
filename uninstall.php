<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-autoloader.php';

\SimpleHoneypotCF7\Autoloader::register();
\SimpleHoneypotCF7\Settings::uninstall();
