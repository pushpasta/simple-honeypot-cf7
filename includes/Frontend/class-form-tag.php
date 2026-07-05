<?php
/**
 * Contact Form 7 honeypot form tag.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Frontend;

use SimpleHoneypotCF7\Settings;
use SimpleHoneypotCF7\Support\Contact_Form_7;
use SimpleHoneypotCF7\Support\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the [honeypot] form tag.
 */
final class Form_Tag {

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
	 * Register Contact Form 7 hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wpcf7_init', array( $this, 'register' ) );
		add_filter( 'wpcf7_form_hidden_fields', array( $this, 'add_security_fields' ), 10, 1 );
	}

	/**
	 * Register the Contact Form 7 form tag.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'wpcf7_add_form_tag' ) ) {
			return;
		}

		$settings = Settings::get_settings();

		wpcf7_add_form_tag(
			'honeypot',
			array( $this, 'render' ),
			array(
				'name-attr'    => true,
				'do-not-store' => empty( $settings['store_honeypot_value'] ),
				'not-for-mail' => true,
			)
		);
	}

	/**
	 * Per-form field index counter for unique dynamic names.
	 *
	 * @var array<int,int>
	 */
	private $field_indices = array();

	/**
	 * Per-form list of already-rendered dynamic names.
	 *
	 * @var array<int,list<string>>
	 */
	private $rendered_names = array();

	/**
	 * Render a honeypot form tag.
	 *
	 * @param mixed $tag Contact Form 7 form tag.
	 * @return string
	 */
	public function render( $tag ) {

		$tag = class_exists( '\WPCF7_FormTag' ) ? new \WPCF7_FormTag( $tag ) : $tag;

		if ( empty( $tag->name ) ) {
			return '';
		}

		$settings     = Settings::get_settings();
		$contact_form = class_exists( '\WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_current() : null;
		$form_id      = $contact_form && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
		$field_name   = sanitize_key( $tag->name );
		$tag_name     = $tag->name;

		if ( ! isset( $this->field_indices[ $form_id ] ) ) {
			$this->field_indices[ $form_id ] = 0;
		}

		$field_index = $this->field_indices[ $form_id ];
		++$this->field_indices[ $form_id ];

		$existing_names = $this->existing_field_names( $contact_form );

		if ( isset( $this->rendered_names[ $form_id ] ) ) {
			$existing_names = array_merge( $existing_names, $this->rendered_names[ $form_id ] );
		}

		$dynamic_name                       = Token::dynamic_name( $form_id, $field_index, $existing_names );
		$this->rendered_names[ $form_id ][] = $dynamic_name;

		$class = method_exists( $tag, 'get_class_option' ) ? $tag->get_class_option( 'wpcf7-form-control wpcf7-text' ) : 'wpcf7-form-control wpcf7-text';

		$html = $this->template->get(
			'frontend/honeypot-field.php',
			array(
				'class'        => $class,
				'dynamic_name' => $dynamic_name,
				'hiding_style' => Token::hiding_style( $form_id, $field_index ),
				'tag_name'     => $tag_name,
			)
		);

		return apply_filters( SIMPLE_HONEYPOT_CF7_BASE . '_html', $html, $tag );
	}

	/**
	 * Add empty token and PoW placeholders to CF7's hidden fields.
	 *
	 * The client populates these fields with fresh values via AJAX.
	 *
	 * @param array<string,string> $hidden_fields Existing hidden fields.
	 * @return array<string,string> Modified hidden fields.
	 */
	public function add_security_fields( $hidden_fields ) {
		$contact_form = class_exists( '\WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_current() : null;
		$form_id      = $contact_form && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;

		if ( ! $form_id ) {
			return $hidden_fields;
		}

		$tags = array();

		if ( method_exists( $contact_form, 'scan_form_tags' ) ) {
			$tags = $contact_form->scan_form_tags();
		}

		$honeypot_tags = array_values(
			array_filter(
				$tags,
				static function ( $tag ) {
					return isset( $tag->type ) && 'honeypot' === $tag->type;
				}
			)
		);

		if ( empty( $honeypot_tags ) ) {
			return $hidden_fields;
		}

		$tokens_field_name                   = Token::tokens_field_name( $form_id );
		$hidden_fields[ $tokens_field_name ] = '';

		$settings = Settings::get_settings();

		if ( ! empty( $settings['pow_enabled'] ) && is_ssl() ) {
			$hidden_fields[ $tokens_field_name . '_pow' ] = '';
		}

		return $hidden_fields;
	}

	/**
	 * Collect existing field names from a Contact Form 7 form.
	 *
	 * @param mixed $contact_form Contact Form 7 form.
	 * @return array
	 */
	private function existing_field_names( $contact_form ) {
		return Contact_Form_7::get_field_names( $contact_form );
	}
}
