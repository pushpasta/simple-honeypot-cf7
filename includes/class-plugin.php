<?php
/**
 * Main plugin coordinator.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

use SimpleHoneypotCF7\Admin\Admin;
use SimpleHoneypotCF7\Frontend\Assets as Frontend_Assets;
use SimpleHoneypotCF7\Frontend\Form_Tag;
use SimpleHoneypotCF7\Frontend\Posted_Data_Filter;
use SimpleHoneypotCF7\Frontend\Spam_Checker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires plugin services into WordPress and Contact Form 7.
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the shared plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_hooks();
		}

		return self::$instance;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		load_plugin_textdomain( 'simple-honeypot-cf7', false, dirname( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME ) . '/languages' );

		$admin              = new Admin();
		$frontend_assets    = new Frontend_Assets();
		$form_tag           = new Form_Tag();
		$spam_checker       = new Spam_Checker();
		$posted_data_filter = new Posted_Data_Filter();
		$updater            = new GitHub_Updater();

		$admin->register_hooks();
		$frontend_assets->register_hooks();
		$updater->register_hooks();

		add_action( 'wpcf7_init', array( $form_tag, 'register' ) );
		add_filter( 'wpcf7_spam', array( $spam_checker, 'check' ), 10, 2 );
		add_filter( 'wpcf7_posted_data', array( $posted_data_filter, 'filter' ), 20, 1 );
		add_filter( 'wpcf7_form_hidden_fields', array( $form_tag, 'add_pow_field' ), 10, 1 );
	}
}
