<?php
/**
 * Contact Form 7 form editor panel.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

use SimpleHoneypotCF7\Settings;
use SimpleHoneypotCF7\Support\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds per-form Simple Honeypot settings to Contact Form 7.
 */
final class Form_Panel {

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
	 * Register the editor panel.
	 *
	 * @param array $panels Existing panels.
	 * @return array
	 */
	public function register_panel( $panels ) {
		$panels['simple-honeypot-cf7-panel'] = array(
			'title'    => __( 'Simple Honeypot', 'simple-honeypot-cf7' ),
			'callback' => array( $this, 'render' ),
		);

		return $panels;
	}

	/**
	 * Render the per-form settings panel.
	 *
	 * @param mixed $contact_form Contact Form 7 form.
	 * @return void
	 */
	public function render( $contact_form ) {
		$form_id = method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;

		$this->template->render(
			'admin/cf7-form-panel.php',
			array(
				'form_settings' => Settings::get_form_settings( $form_id ),
			)
		);
	}

	/**
	 * Save per-form settings.
	 *
	 * @param mixed $contact_form Contact Form 7 form.
	 * @return void
	 */
	public function save( $contact_form ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 verifies the request before wpcf7_after_save.
		$post = wp_unslash( $_POST );

		if ( ! method_exists( $contact_form, 'id' ) ) {
			return;
		}

		if ( function_exists( 'wpcf7_admin_has_edit_cap' ) && ! wpcf7_admin_has_edit_cap() ) {
			return;
		}

		$form_id = (int) $contact_form->id();

		if ( empty( $post['simple_honeypot_cf7_form'] ) ) {
			return;
		}

		$form = (array) $post['simple_honeypot_cf7_form'];

		Settings::update_form_settings(
			$form_id,
			array(
				'time_mode'        => sanitize_key( isset( $form['time_mode'] ) ? $form['time_mode'] : 'inherit' ),
				'min_time_seconds' => isset( $form['min_time_seconds'] ) ? absint( $form['min_time_seconds'] ) : 0,
			)
		);
	}
}
