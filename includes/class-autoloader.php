<?php
/**
 * Plugin class autoloader.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin classes from the includes directory.
 */
final class Autoloader {

	/**
	 * Cache of loaded class names to skip repeated filesystem checks.
	 *
	 * @var array<string,true>
	 */
	private static $loaded = array();

	/**
	 * Register the autoloader with PHP.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class by namespace.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load( $class_name ) {
		if ( isset( self::$loaded[ $class_name ] ) ) {
			return;
		}

		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );
		$file     = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$path     = SIMPLE_HONEYPOT_CF7_PATH . 'includes/';

		if ( ! empty( $parts ) ) {
			$path .= implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR;
		}

		if ( is_readable( $path . $file ) ) {
			self::$loaded[ $class_name ] = true;
			require_once $path . $file;
		}
	}
}
