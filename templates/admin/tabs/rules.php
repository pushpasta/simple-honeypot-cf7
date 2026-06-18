<?php
/**
 * Rules tab.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="">
	<?php wp_nonce_field( 'simple_honeypot_cf7_save_settings', 'simple_honeypot_cf7_nonce' ); ?>
	<input type="hidden" name="simple_honeypot_cf7_action" value="save" />
	<input type="hidden" name="tab" value="rules" />

	<div class="postbox simple-honeypot-cf7-card">
		<h2 class="hndle"><span class="dashicons dashicons-shield"></span><span><?php esc_html_e( 'Rule sources', 'simple-honeypot-cf7' ); ?></span></h2>
		<div class="inside">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Rules', 'simple-honeypot-cf7' ); ?></th>
					<td>
						<div class="simple-honeypot-cf7-custom-rules-group">
							<label class="simple-honeypot-cf7-custom-rules-toggle">
								<input type="checkbox" name="custom_rules_enabled" value="1" <?php checked( $settings['custom_rules_enabled'], 1 ); ?> />
								<?php esc_html_e( 'Enable rules.', 'simple-honeypot-cf7' ); ?>
							</label>
							<textarea class="large-text code simple-honeypot-cf7-rules" name="custom_rules" rows="16" placeholder="<?php echo esc_attr( "192.168.1.*\n10.0.0.0/24\n2001:db8::/32\n*@temporary-mail.com\nspammer@example.com" ); ?>"><?php echo esc_textarea( $settings['custom_rules'] ); ?></textarea>
							<p class="description">
								<?php
								/* translators: * characters are literal wildcard symbols and must not be translated. */
								esc_html_e( 'One rule per line. Each line is auto-detected as either an IP or email based on format. Supported: IPv4, IPv6, wildcard *, and CIDR for IP; wildcard * for email. Unrecognized lines are ignored.', 'simple-honeypot-cf7' );
								?>
							</p>
						</div>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="notice notice-info inline">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: URL to Discussion settings page. */
					__( 'Need to block specific words or patterns? Use the <a href="%1$s">WordPress Disallowed Comment Keys</a> setting instead &mdash; Contact Form 7 checks it automatically.', 'simple-honeypot-cf7' ),
					esc_url( admin_url( 'options-discussion.php' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>
	</div>

	<p class="submit">
		<?php submit_button( __( 'Save' ), 'primary', 'submit', false ); ?>
	</p>
</form>
