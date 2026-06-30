<?php
/**
 * Admin settings page.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

use SimpleHoneypotCF7\Reporting\Event_Logger;
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
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=simple-honeypot-cf7' ) ) . '">' . esc_html__( 'Settings', 'simple-honeypot-cf7' ) . '</a>';

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
			'settings' => __( 'Settings', 'simple-honeypot-cf7' ),
			'rules'    => __( 'Rules', 'simple-honeypot-cf7' ),
			'forms'    => __( 'Forms', 'simple-honeypot-cf7' ),
			'reports'  => __( 'Reports', 'simple-honeypot-cf7' ),
			'tools'    => __( 'Tools', 'simple-honeypot-cf7' ),
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
		if ( empty( $_POST[ SIMPLE_HONEYPOT_CF7_BASE . '_action' ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.
		$post = wp_unslash( $_POST );

		check_admin_referer( SIMPLE_HONEYPOT_CF7_BASE . '_save_settings', SIMPLE_HONEYPOT_CF7_BASE . '_nonce' );

		if ( ! empty( $post[ SIMPLE_HONEYPOT_CF7_BASE . '_import_settings' ] ) ) {
			$importer = new Importer();
			$result   = $importer->import();

			if ( empty( $result['success'] ) ) {
				$args = array();
				if ( ! empty( $result['error'] ) ) {
					$args['import_error'] = $result['error'];
				}
				$this->redirect( 'tools', 'import-failed', $args );
				return;
			}

			$this->redirect( 'tools', 'import-success' );
			return;
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
			$stats    = Settings::get_stats();

			$per_page     = $settings['events_per_page'];
			$total_events = Event_Logger::count();
			$total_pages  = max( 1, (int) ceil( $total_events / $per_page ) );
			$current_page = max( 1, min( $total_pages, isset( $_GET['events_page'] ) ? absint( $_GET['events_page'] ) : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$offset       = ( $current_page - 1 ) * $per_page;

			// Events live in the custom table; populate for the template.
			$stats['events'] = Event_Logger::get_recent( $per_page, $offset );

			return array(
				'stats'        => $stats,
				'settings'     => $settings,
				'parsed_rules' => \SimpleHoneypotCF7\Rules\Rules::parse( $settings['custom_rules'] ),
				'pagination'   => array(
					'total'        => $total_events,
					'per_page'     => $per_page,
					'current_page' => $current_page,
					'total_pages'  => $total_pages,
				),
				'spam_counts'  => Event_Logger::count_by_period(),
			);
		}

		if ( 'rules' === $tab ) {
			$settings = Settings::get_settings();
			return array(
				'settings'     => $settings,
				'parsed_rules' => \SimpleHoneypotCF7\Rules\Rules::parse( $settings['custom_rules'] ),
			);
		}

		if ( 'forms' === $tab ) {
			return array( 'forms_with_overrides' => $this->get_forms_with_overrides() );
		}

		if ( 'tools' === $tab ) {
			return array(
				'export_url' => $this->export_url(),
			);
		}

		return array(
			'settings'   => Settings::get_settings(),
			'export_url' => $this->export_url(),
		);
	}

	/**
	 * Get all CF7 forms that have per-form settings saved.
	 *
	 * @return array
	 */
	private function get_forms_with_overrides() {
		$forms = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'any',
			)
		);

		$global = Settings::get_settings();
		$result = array();

		foreach ( $forms as $form ) {
			$raw = get_post_meta( $form->ID, Settings::FORM_META, true );

			if ( ! is_array( $raw ) || empty( $raw ) ) {
				continue;
			}

			$settings = Settings::get_form_settings( $form->ID );

			$time_mode_custom = ( 'inherit' !== $settings['time_mode'] );
			$min_time_custom  = ( $settings['min_time_seconds'] > 0 );

			if ( ! $time_mode_custom && ! $min_time_custom ) {
				continue;
			}

			$resolved_mode = $time_mode_custom
				? $settings['time_mode']
				: ( $global['time_check_enabled'] ? 'enabled' : 'disabled' );

			$resolved_min = $min_time_custom
				? $settings['min_time_seconds']
				: $global['min_time_seconds'];

			$result[] = array(
				'id'               => $form->ID,
				'title'            => $form->post_title,
				'edit_url'         => admin_url( 'admin.php?page=wpcf7&post=' . $form->ID . '&action=edit' ),
				'time_mode'        => $settings['time_mode'],
				'time_mode_custom' => $time_mode_custom,
				'resolved_mode'    => $resolved_mode,
				'min_time_seconds' => $settings['min_time_seconds'],
				'min_time_custom'  => $min_time_custom,
				'resolved_min'     => $resolved_min,
			);
		}

		return $result;
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
			'type'    => 'success',
		);

		if ( empty( $get['updated'] ) ) {
			return $result;
		}

		$updated = sanitize_key( $get['updated'] );

		if ( 'stats-reset' === $updated ) {
			$result['message'] = __( 'Reporting data has been cleared.', 'simple-honeypot-cf7' );
			return $result;
		}

		if ( 'settings-reset' === $updated ) {
			$result['message'] = __( 'All global settings have been reset to defaults.', 'simple-honeypot-cf7' );
			return $result;
		}

		if ( 'purge-events' === $updated ) {
			$result['message'] = '';
			return $result;
		}

		if ( 'import-success' === $updated ) {
			$result['message'] = __( 'Settings imported successfully.', 'simple-honeypot-cf7' );
			return $result;
		}

		if ( 'import-failed' === $updated ) {
			$result['message'] = isset( $get['import_error'] ) ? sanitize_text_field( $get['import_error'] ) : __( 'Import failed. Please verify the file and try again.', 'simple-honeypot-cf7' );
			$result['type']    = 'error';
			return $result;
		}

		if ( 'rules' === $updated ) {
			$result['message'] = __( 'Rules have been saved.', 'simple-honeypot-cf7' );
			return $result;
		}

		if ( 'action-failed' === $updated ) {
			$result['message'] = __( 'The action could not be completed. Please try again.', 'simple-honeypot-cf7' );
			$result['type']    = 'error';
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
		return Settings::sanitize_global( $post + $settings );
	}

	/**
	 * Sanitize rule settings from POST.
	 *
	 * @param array $settings Existing settings.
	 * @param array $post     Unslashed POST data.
	 * @return array
	 */
	private function rules_from_post( array $settings, array $post ) {
		return Settings::sanitize_global( $post + $settings );
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

		check_admin_referer( SIMPLE_HONEYPOT_CF7_BASE . '_export_settings' );

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
		header( 'Content-Disposition: attachment; filename=' . SIMPLE_HONEYPOT_CF7_BASE . '_v' . SIMPLE_HONEYPOT_CF7_VERSION . '-settings.json' );

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT );

		if ( false === $json ) {
			wp_die( esc_html__( 'Failed to encode settings.', 'simple-honeypot-cf7' ) );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download; escaping would break the format.
		echo $json;
		exit;
	}

	/**
	 * Handle admin_post request to purge old events.
	 *
	 * @return void
	 */
	public function purge_events() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to purge events.', 'simple-honeypot-cf7' ) );
		}

		check_admin_referer( SIMPLE_HONEYPOT_CF7_BASE . '_purge_events' );

		$days    = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 90;
		$days    = max( 1, $days );
		$before  = Event_Logger::count();
		$removed = Event_Logger::purge_old( $days );

		set_transient(
			SIMPLE_HONEYPOT_CF7_BASE . '_purge_notice',
			array(
				'removed' => $removed,
				'days'    => $days,
			),
			60
		);

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=simple-honeypot-cf7&tab=tools' ) );
		exit;
	}

	/**
	 * Get the nonce-protected export URL.
	 *
	 * @return string
	 */
	private function export_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . SIMPLE_HONEYPOT_CF7_BASE . '_export_settings' ),
			SIMPLE_HONEYPOT_CF7_BASE . '_export_settings'
		);
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
