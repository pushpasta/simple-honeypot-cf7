<?php
/**
 * Admin settings page.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

use SimpleHoneypotCF7\Settings;
use SimpleHoneypotCF7\Support\Contact_Form_7;
use SimpleHoneypotCF7\Support\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the plugin settings screen.
 */
final class Settings_Page {

	/**
	 * Template renderer.
	 *
	 * @var Template
	 */
	private $template;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->template = new Template();
	}

	/**
	 * Add the plugin submenu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		$hook = add_submenu_page(
			Contact_Form_7::is_active() ? 'wpcf7' : 'options-general.php',
			__( 'Simple Honeypot for Contact Form 7', 'simple-honeypot-cf7' ),
			__( 'Simple Honeypot', 'simple-honeypot-cf7' ),
			'manage_options',
			'simple-honeypot-cf7',
			array( $this, 'render' )
		);
	}

	/**
	 * Add a plugin row settings link.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function settings_link( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=simple-honeypot-cf7' ) ) . '">' . esc_html__( 'Settings' ) . '</a>';

		return $links;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$tabs        = array(
			'settings' => __( 'Settings' ),
			'rules'    => __( 'Rules' ),
			'reports'  => __( 'Reports' ),
		);

		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'settings';
		}

		$notice_info = $this->update_notice();

		$this->template->render(
			'admin/page.php',
			array(
				'current_tab'  => $current_tab,
				'notice'       => $notice_info['message'],
				'notice_type'  => $notice_info['type'],
				'tabs'         => $tabs,
				'tab_context'  => $this->tab_context( $current_tab ),
				'tab_template' => 'admin/tabs/' . $current_tab . '.php',
			)
		);
	}

	/**
	 * Handle settings form posts.
	 *
	 * @return void
	 */
	public function handle_post() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified after the permission check below.
		if ( empty( $_POST['simple_honeypot_cf7_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.
		$post = wp_unslash( $_POST );

		check_admin_referer( 'simple_honeypot_cf7_save_settings', 'simple_honeypot_cf7_nonce' );

		if ( 'reset_stats' === sanitize_key( $post['simple_honeypot_cf7_action'] ) ) {
			Settings::reset_stats();
			$this->redirect( 'settings', 'stats-reset' );
		}

		if ( 'reset_settings' === sanitize_key( $post['simple_honeypot_cf7_action'] ) ) {
			Settings::reset_settings();
			$this->redirect( 'settings', 'settings-reset' );
		}

		if ( 'import_settings' === sanitize_key( $post['simple_honeypot_cf7_action'] ) ) {
			$importer = new Importer();
			$result   = $importer->import();

			if ( empty( $result['success'] ) ) {
				$args = array();
				if ( ! empty( $result['error'] ) ) {
					$args['import_error'] = $result['error'];
				}
				$this->redirect( 'settings', 'import-failed', $args );
			}

			$this->redirect( 'settings', 'import-success' );
		}

		$tab      = isset( $post['tab'] ) ? sanitize_key( $post['tab'] ) : 'settings';
		$settings = Settings::get_settings();

		if ( 'settings' === $tab ) {
			$settings = $this->settings_from_post( $settings, $post );
		} elseif ( 'rules' === $tab ) {
			$settings = $this->rules_from_post( $settings, $post );
		}

		Settings::update_settings( $settings );
		$this->redirect( $tab, 'rules' === $tab ? 'rules' : 'settings' );
	}

	/**
	 * Build context for the current tab.
	 *
	 * @param string $tab Current tab.
	 * @return array
	 */
	private function tab_context( $tab ) {
		if ( 'reports' === $tab ) {
			$settings = Settings::get_settings();
			return array(
				'stats'        => Settings::get_stats(),
				'settings'     => $settings,
				'parsed_rules' => \SimpleHoneypotCF7\Rules\Rules::parse( $settings['custom_rules'] ),
			);
		}

		if ( 'rules' === $tab ) {
			return array( 'settings' => Settings::get_settings() );
		}

		return array(
			'settings'   => Settings::get_settings(),
			'export_url' => wp_nonce_url( admin_url( 'admin-post.php?action=simple_honeypot_cf7_export_settings' ), 'simple_honeypot_cf7_export_settings' ),
		);
	}

	/**
	 * Build the saved/reset notice message.
	 *
	 * @return string
	 */
	private function update_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status parameter used for admin notices.
		$get = wp_unslash( $_GET );

		$result = array(
			'message' => '',
			'type'    => 'notice-success',
		);

		if ( empty( $get['updated'] ) ) {
			return $result;
		}

		$updated = sanitize_key( $get['updated'] );

		if ( 'stats-reset' === $updated ) {
			$result['message'] = __( 'Report data has been cleared.', 'simple-honeypot-cf7' );
			return $result;
		}

		if ( 'settings-reset' === $updated ) {
			$result['message'] = __( 'Global settings have been restored to defaults.', 'simple-honeypot-cf7' );
			return $result;
		}

		if ( 'import-success' === $updated ) {
			$result['message'] = __( 'Settings imported successfully.', 'simple-honeypot-cf7' );
			return $result;
		}

		if ( 'import-failed' === $updated ) {
			$result['message'] = isset( $get['import_error'] ) ? sanitize_text_field( $get['import_error'] ) : __( 'Import failed. Please check the file and try again.', 'simple-honeypot-cf7' );
			$result['type']    = 'notice-error';
			return $result;
		}

		if ( 'rules' === $updated ) {
			$result['message'] = __( 'Rules have been saved.', 'simple-honeypot-cf7' );
			return $result;
		}

		$result['message'] = __( 'Plugin settings have been saved.', 'simple-honeypot-cf7' );
		return $result;
	}

	/**
	 * Sanitize general settings from POST.
	 *
	 * @param array $settings Existing settings.
	 * @param array $post     Unslashed POST data.
	 * @return array
	 */
	private function settings_from_post( array $settings, array $post ) {
		$settings['time_check_enabled']   = empty( $post['time_check_enabled'] ) ? 0 : 1;
		$settings['min_time_seconds']     = max( 0, absint( isset( $post['min_time_seconds'] ) ? $post['min_time_seconds'] : $settings['min_time_seconds'] ) );
		$settings['max_age_minutes']      = max( 10, absint( isset( $post['max_age_minutes'] ) ? $post['max_age_minutes'] : $settings['max_age_minutes'] ) );
		$settings['pow_enabled']          = empty( $post['pow_enabled'] ) ? 0 : 1;
		$settings['pow_complexity']       = max( 4, min( 20, absint( isset( $post['pow_complexity'] ) ? $post['pow_complexity'] : $settings['pow_complexity'] ) ) );
		$settings['store_honeypot_value'] = empty( $post['store_honeypot_value'] ) ? 0 : 1;
		$settings['keep_recent_events']   = max( 10, absint( isset( $post['keep_recent_events'] ) ? $post['keep_recent_events'] : $settings['keep_recent_events'] ) );

		return $settings;
	}

	/**
	 * Sanitize rule settings from POST.
	 *
	 * @param array $settings Existing settings.
	 * @param array $post     Unslashed POST data.
	 * @return array
	 */
	private function rules_from_post( array $settings, array $post ) {
		$settings['custom_rules_enabled'] = empty( $post['custom_rules_enabled'] ) ? 0 : 1;
		$settings['custom_rules']         = $this->sanitize_rules( isset( $post['custom_rules'] ) ? $post['custom_rules'] : $settings['custom_rules'] );

		return $settings;
	}

	/**
	 * Sanitize textarea rules line by line.
	 *
	 * @param string $rules Rules text.
	 * @return string
	 */
	private function sanitize_rules( $rules ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $rules );
		$lines = array_map( 'sanitize_text_field', $lines );

		$lines = array_map(
			static function ( $line ) {
				$line = preg_replace( '/^(ip|email):/i', '', $line );
				return trim( $line );
			},
			$lines
		);

		return implode( "\n", $lines );
	}

	/**
	 * Export all settings (global + per-form) as a JSON file.
	 *
	 * @return void
	 */
	public function export_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export settings.', 'simple-honeypot-cf7' ) );
		}

		check_admin_referer( 'simple_honeypot_cf7_export_settings' );

		$global = Settings::get_settings();
		$forms  = array();

		if ( Contact_Form_7::is_active() ) {
			$posts = get_posts(
				array(
					'post_type'      => 'wpcf7_contact_form',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'post_status'    => 'any',
				)
			);

			foreach ( $posts as $form_id ) {
				$form_settings = Settings::get_form_settings( $form_id );
				$defaults      = Settings::default_settings();

				if ( 'inherit' !== $form_settings['time_mode'] || $form_settings['min_time_seconds'] > 0 ) {
					$forms[ $form_id ] = $form_settings;
				}
			}
		}

		$data = array(
			'version'         => SIMPLE_HONEYPOT_CF7_VERSION,
			'global_settings' => $global,
			'form_settings'   => $forms,
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=simple-honeypot-cf7-settings.json' );

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT );

		if ( false === $json ) {
			wp_die( esc_html__( 'Failed to encode settings.', 'simple-honeypot-cf7' ) );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download; escaping would break the format.
		echo $json;
		exit;
	}

	/**
	 * Redirect back to the settings page.
	 *
	 * @param string $tab        Settings tab.
	 * @param string $updated    Update status.
	 * @param array  $extra_args Optional extra query arguments.
	 * @return void
	 */
	private function redirect( $tab, $updated, $extra_args = array() ) {
		$args = array(
			'page'    => 'simple-honeypot-cf7',
			'tab'     => sanitize_key( $tab ),
			'updated' => sanitize_key( $updated ),
		);

		if ( ! empty( $extra_args ) ) {
			foreach ( $extra_args as $key => $value ) {
				$args[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
