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
		add_filter( 'wpcf7_form_hidden_fields', array( $this, 'add_token_fields' ), 10, 1 );
		add_filter( 'wpcf7_form_hidden_fields', array( $this, 'add_pow_field' ), 11, 1 );
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
	 * Per-form pre-generated tokens for cache-compatible hidden fields.
	 *
	 * @var array<int,list<string>>
	 */
	private static $pre_generated_tokens = array();

	/**
	 * Per-form pre-generated dynamic names for cache-compatible hidden fields.
	 *
	 * @var array<int,list<string>>
	 */
	private static $pre_generated_dynamic_names = array();

	/**
	 * Per-form tokens field name for cache-compatible hidden fields.
	 *
	 * @var array<int,string>
	 */
	private static $pre_generated_tokens_field = array();

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

		// Use pre-generated tokens when available (for page-cache compatibility).
		if ( isset( self::$pre_generated_tokens[ $form_id ] )
			&& isset( self::$pre_generated_tokens[ $form_id ][ $field_index ] ) ) {
			$token             = self::$pre_generated_tokens[ $form_id ][ $field_index ];
			$dynamic_name      = self::$pre_generated_dynamic_names[ $form_id ][ $field_index ];
			$tokens_field_name = self::$pre_generated_tokens_field[ $form_id ];
		} else {
			$existing_names = $this->existing_field_names( $contact_form );

			if ( isset( $this->rendered_names[ $form_id ] ) ) {
				$existing_names = array_merge( $existing_names, $this->rendered_names[ $form_id ] );
			}

			$dynamic_name                       = Token::dynamic_name( $form_id, $field_index, $existing_names );
			$this->rendered_names[ $form_id ][] = $dynamic_name;

			$max_age           = max( 10, absint( $settings['max_age_minutes'] ) ) * MINUTE_IN_SECONDS;
			$token             = Token::generate( $form_id, $field_name, $dynamic_name, $max_age );
			$tokens_field_name = Token::tokens_field_name( $form_id );
		}

		$class = method_exists( $tag, 'get_class_option' ) ? $tag->get_class_option( 'wpcf7-form-control wpcf7-text' ) : 'wpcf7-form-control wpcf7-text';

		$html = $this->template->get(
			'frontend/honeypot-field.php',
			array(
				'class'             => $class,
				'dynamic_name'      => $dynamic_name,
				'hiding_style'      => Token::hiding_style( $form_id, $field_index ),
				'tag_name'          => $tag_name,
				'token'             => $token,
				'tokens_field_name' => $tokens_field_name,
			)
		);

		return apply_filters( SIMPLE_HONEYPOT_CF7_BASE . '_html', $html, $tag );
	}

	/**
	 * Inject honeypot token fields into CF7's hidden-fields-container.
	 *
	 * Pre-generates tokens so they are part of CF7's form structure.
	 * This ensures tokens survive page caching (WP Rocket, etc.) where
	 * the cached HTML may not include dynamically rendered tag output.
	 *
	 * @param array<string,string> $hidden_fields Existing hidden fields.
	 * @return array<string,string>
	 */
	public function add_token_fields( $hidden_fields ) {
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

		$settings          = Settings::get_settings();
		$max_age           = max( 10, absint( $settings['max_age_minutes'] ) ) * MINUTE_IN_SECONDS;
		$tokens_field_name = Token::tokens_field_name( $form_id );
		$existing_names    = Contact_Form_7::get_field_names( $contact_form );
		$tokens            = array();
		$dynamic_names     = array();

		foreach ( $honeypot_tags as $index => $tag ) {
			$field_name       = sanitize_key( $tag->name );
			$dynamic_name     = Token::dynamic_name( $form_id, $index, $existing_names );
			$dynamic_names[]  = $dynamic_name;
			$existing_names[] = $dynamic_name;
			$tokens[]         = Token::generate( $form_id, $field_name, $dynamic_name, $max_age );
		}

		self::$pre_generated_tokens[ $form_id ]        = $tokens;
		self::$pre_generated_dynamic_names[ $form_id ] = $dynamic_names;
		self::$pre_generated_tokens_field[ $form_id ]  = $tokens_field_name;

		$hidden_fields[ $tokens_field_name ] = $tokens;

		return $hidden_fields;
	}

	/**
	 * Inject the proof-of-work hidden field into CF7's hidden-fields-container.
	 *
	 * @param array<string,string> $hidden_fields Existing hidden fields.
	 * @return array<string,string>
	 */
	public function add_pow_field( $hidden_fields ) {
		$settings = Settings::get_settings();

		if ( empty( $settings['pow_enabled'] ) || ! is_ssl() ) {
			return $hidden_fields;
		}

		$contact_form = class_exists( '\WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_current() : null;
		$form_id      = $contact_form && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;

		if ( ! $form_id ) {
			return $hidden_fields;
		}

		$tokens_field_name = Token::tokens_field_name( $form_id );
		$pow_challenge     = Token::pow_challenge( $form_id, $settings );

		if ( ! empty( $pow_challenge ) ) {
			$hidden_fields[ $tokens_field_name . '_pow' ] = $pow_challenge;
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
