<?php
/**
 * Admin hook coordinator.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

use SimpleHoneypotCF7\Support\Contact_Form_7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all admin-facing hooks.
 */
final class Admin {

	/**
	 * Register WordPress admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$assets        = new Assets();
		$notices       = new Notices();
		$settings_page = new Settings_Page();
		$form_panel    = new Form_Panel();
		$tag_generator = new Tag_Generator();
		$rest_api      = new Rest_Api();

		add_action( 'admin_enqueue_scripts', array( $assets, 'enqueue' ) );
		add_action( 'admin_notices', array( $notices, 'contact_form_7_missing' ) );
		add_action( 'admin_notices', array( $notices, 'pow_requires_ssl' ) );
		add_action( 'admin_notices', array( $notices, 'reset_form_notice' ) );
		add_action( 'admin_notices', array( $notices, 'purge_events_notice' ) );
		add_action( 'admin_menu', array( $settings_page, 'register_menu' ) );
		add_action( 'admin_init', array( $settings_page, 'handle_post' ) );
		add_action( 'admin_post_' . SIMPLE_HONEYPOT_CF7_BASE . '_export_settings', array( $settings_page, 'export_settings' ) );
		add_action( 'rest_api_init', array( $rest_api, 'register_routes' ) );

		add_filter( 'plugin_action_links_' . SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME, array( $settings_page, 'settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		add_filter( 'plugin_auto_update_setting_html', array( $this, 'auto_update_toggle' ), 10, 3 );

		if ( Contact_Form_7::is_active() ) {
			add_filter( 'wpcf7_editor_panels', array( $form_panel, 'register_panel' ) );
			add_action( 'wpcf7_after_save', array( $form_panel, 'save' ) );
			add_action( 'wpcf7_admin_init', array( $tag_generator, 'register' ), 20, 0 );
			add_action( 'admin_post_' . SIMPLE_HONEYPOT_CF7_BASE . '_reset_form_settings', array( $form_panel, 'reset_form_settings' ) );
		}
	}

	/**
	 * Add a donation link to the plugin row meta on the plugins screen.
	 *
	 * @param array  $links Existing row meta links.
	 * @param string $file  Plugin basename.
	 * @return array
	 */
	public function row_meta( $links, $file ) {
		if ( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME === $file ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://github.com/pushpasta/simple-honeypot-cf7/?sponsor' ),
				esc_html__( 'Donate', 'simple-honeypot-cf7' )
			);
		}

		return $links;
	}

	/**
	 * Render the auto-update toggle for this plugin.
	 *
	 * Third-party plugins don't get the toggle from WordPress core.
	 * This filter provides it by checking the auto_update_plugins option.
	 *
	 * @param string $html        Default HTML.
	 * @param string $plugin_file Plugin basename.
	 * @return string
	 */
	public function auto_update_toggle( $html, $plugin_file ) {
		if ( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME !== $plugin_file ) {
			return $html;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return $html;
		}

		$auto_updates = get_site_option( 'auto_update_plugins', array() );
		$enabled      = in_array( SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME, $auto_updates, true );

		if ( $enabled ) {
			$action = 'disable-auto-update';
			$label  = __( 'Disable auto-updates', 'simple-honeypot-cf7' );
		} else {
			$action = 'enable-auto-update';
			$label  = __( 'Enable auto-updates', 'simple-honeypot-cf7' );
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'plugin' => SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME,
				),
				self_admin_url( 'plugins.php' )
			),
			$action . '-plugin_' . SIMPLE_HONEYPOT_CF7_PLUGIN_BASENAME
		);

		return sprintf(
			'<a href="%s" class="toggle-auto-update" data-wp-action="%s"><span class="dashicons dashicons-update spin hidden" aria-hidden="true"></span><span class="label">%s</span></a>',
			esc_url( $url ),
			esc_attr( $enabled ? 'disable' : 'enable' ),
			esc_html( $label )
		);
	}
}
