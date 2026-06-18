<?php
/**
 * Template rendering helper.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin templates from the templates directory.
 */
final class Template {

	/**
	 * Render a template.
	 *
	 * @param string $template Template path relative to templates directory.
	 * @param array  $context  Variables exposed to the template.
	 * @return void
	 */
	public function render( $template, array $context = array() ) {
		$file = $this->path( $template );

		if ( ! $file ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Templates intentionally receive local variables.
		extract( $context, EXTR_SKIP );
		include $file;
	}

	/**
	 * Return a rendered template as a string.
	 *
	 * @param string $template Template path relative to templates directory.
	 * @param array  $context  Variables exposed to the template.
	 * @return string
	 */
	public function get( $template, array $context = array() ) {
		ob_start();
		$this->render( $template, $context );
		return (string) ob_get_clean();
	}

	/**
	 * Resolve a template path.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	private function path( $template ) {
		$template = ltrim( str_replace( '\\', '/', $template ), '/' );
		$file     = SIMPLE_HONEYPOT_CF7_PATH . 'templates/' . $template;

		if ( is_readable( $file ) ) {
			return $file;
		}

		// phpcs:ignore WordPress.PHP.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Developer-facing diagnostics for missing templates.
		error_log( sprintf( 'Simple Honeypot CF7: template not found — %s', $template ) );

		return '';
	}
}
