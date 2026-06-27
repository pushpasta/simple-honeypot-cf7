<?php
/**
 * REST API endpoints for danger zone actions.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

use SimpleHoneypotCF7\Reporting\Event_Logger;
use SimpleHoneypotCF7\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles REST API routes for destructive actions.
 */
final class Rest_Api {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = SIMPLE_HONEYPOT_CF7_BASE;

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/v1/action',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_action' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'action' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'reset_stats', 'reset_settings', 'purge_events' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'days'   => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 90,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Check if the current user can perform danger zone actions.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle danger zone action requests.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function handle_action( $request ) {
		$action = $request->get_param( 'action' );

		switch ( $action ) {
			case 'reset_stats':
				Settings::reset_stats();
				$this->set_notice_transient( 'stats-reset' );
				break;

			case 'reset_settings':
				Settings::reset_settings();
				$this->set_notice_transient( 'settings-reset' );
				break;

			case 'purge_events':
				$days    = $request->get_param( 'days' );
				$days    = max( 1, $days );
				$removed = Event_Logger::purge_old( $days );

				set_transient(
					SIMPLE_HONEYPOT_CF7_BASE . '_purge_notice',
					array(
						'removed' => $removed,
						'days'    => $days,
					),
					60
				);
				break;

			default:
				return new \WP_REST_Response(
					array(
						'success' => false,
						'error'   => __( 'Unknown action.', 'simple-honeypot-cf7' ),
					),
					400
				);
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Set a notice transient for redirect-based feedback.
	 *
	 * @param string $type Notice type (e.g. 'stats-reset', 'settings-reset').
	 * @return void
	 */
	private function set_notice_transient( $type ) {
		set_transient( SIMPLE_HONEYPOT_CF7_BASE . '_redirect_notice', $type, 60 );
	}
}
