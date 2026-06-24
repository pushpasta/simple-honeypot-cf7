<?php
/**
 * Plugin bootstrap.
 *
 * @package Simple_Honeypot_CF7
 *
 * @wordpress-plugin
 * Plugin Name:       Simple Honeypot for Contact Form 7
 * Plugin URI:        https://github.com/pushpasta/simple-honeypot-cf7
 * Description:       Lightweight honeypot, timing, proof-of-work, and rule-based spam protection for Contact Form 7.
 * Version:           1.3.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Requires Plugins:  contact-form-7
 * Author:            pushpasta
 * Author URI:        https://github.com/pushpasta/
 * License:           GNU GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       simple-honeypot-cf7
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_HONEYPOT_CF7_VERSION', '1.3.0' );
define( 'SIMPLE_HONEYPOT_CF7_PLUGIN_FILE', __FILE__ );
define( 'SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SIMPLE_HONEYPOT_CF7_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_HONEYPOT_CF7_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPLE_HONEYPOT_CF7_PATH . 'includes/class-autoloader.php';

\SimpleHoneypotCF7\Autoloader::register();

register_activation_hook( __FILE__, array( '\SimpleHoneypotCF7\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\SimpleHoneypotCF7\Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( '\SimpleHoneypotCF7\Plugin', 'instance' ) );
