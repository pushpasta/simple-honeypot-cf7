<?php
/**
 * Contact Form 7 honeypot tag generator.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<header class="description-box">
	<h3><?php esc_html_e( 'honeypot field form-tag generator', 'simple-honeypot-cf7' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Generates a form-tag for a hidden honeypot field that helps catch automated spam.', 'simple-honeypot-cf7' ); ?></p>
</header>

<div class="control-box">
	<?php
	$tag->print( 'field_type', array( 'select_options' => array( 'honeypot' => __( 'honeypot', 'simple-honeypot-cf7' ) ) ) );
	$tag->print( 'field_name' );
	?>
</div>

<footer class="insert-box">
	<?php $tag->print( 'insert_box_content' ); ?>
	<p class="mail-tag-tip">
		<?php
		printf(
			/* translators: %s: autocomplete="new-password" attribute. */
			esc_html__( 'Honeypot fields use %s to reduce unwanted browser autofill. Multiple honeypot fields usually provide no meaningful additional protection and may make the form\'s anti-spam mechanism easier for bots to identify.', 'simple-honeypot-cf7' ),
			'<code>autocomplete="new-password"</code>'
		);
		?>
	</p>
</footer>
