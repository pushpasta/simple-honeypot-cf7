<?php
/**
 * Contact Form 7 tag generator.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Admin;

use SimpleHoneypotCF7\Support\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a honeypot tag generator to the Contact Form 7 form editor.
 */
final class Tag_Generator {

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
	 * Register the Contact Form 7 tag generator.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! class_exists( '\WPCF7_TagGenerator' ) ) {
			return;
		}

		$tag_generator = \WPCF7_TagGenerator::get_instance();
		$tag_generator->add( 'honeypot', __( 'Honeypot', 'simple-honeypot-cf7' ), array( $this, 'render' ), array( 'version' => 2 ) );
	}

	/**
	 * Render the tag generator.
	 *
	 * @param mixed $contact_form Contact Form 7 form.
	 * @param array $args         Generator args.
	 * @return void
	 */
	public function render( $contact_form, $args = array() ) {
		$args = wp_parse_args( $args, array( 'content' => 'honeypot' ) );

		if ( class_exists( '\WPCF7_TagGeneratorGenerator' ) && defined( 'WPCF7_VERSION' ) && version_compare( WPCF7_VERSION, '6.0', '>=' ) ) {
			$this->template->render(
				'admin/cf7-tag-generator.php',
				array(
					'args'   => $args,
					'modern' => true,
					'tag'    => new \WPCF7_TagGeneratorGenerator( $args['content'] ),
				)
			);
			return;
		}

		$this->template->render(
			'admin/cf7-tag-generator.php',
			array(
				'args'   => $args,
				'modern' => false,
			)
		);
	}
}
