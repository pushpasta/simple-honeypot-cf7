<?php
/**
 * CF7 dashboard widget.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Backend;

use SimpleHoneypotCF7\Settings;
use SimpleHoneypotCF7\Support\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a stats overview widget to the Contact Form 7 dashboard.
 */
final class Dashboard_Widget {

	/**
	 * Register the dashboard setup hook.
	 *
	 * Inert on Contact Form 7 versions before 6.2, where the
	 * wpcf7_dashboard_setup action is never fired.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wpcf7_dashboard_setup', array( $this, 'add_widget' ) );
	}

	/**
	 * Register the widget on the Contact Form 7 Dashboard screen.
	 *
	 * @return void
	 */
	public function add_widget() {
		if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			SIMPLE_HONEYPOT_CF7_BASE . '_dashboard_overview',
			__( 'Simple Honeypot Overview', 'simple-honeypot-cf7' ),
			array( $this, 'render' ),
			null,
			null,
			'side',
			'default'
		);
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * Limited to users who can manage the plugin settings, matching
	 * the access model of the plugin's own admin screens.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$template = new Template();

		$template->render(
			'backend/dashboard-widget.php',
			array(
				'stats'       => Settings::get_meta(),
				'reports_url' => admin_url( 'admin.php?page=simple-honeypot-cf7&tab=reports' ),
			)
		);
	}
}
