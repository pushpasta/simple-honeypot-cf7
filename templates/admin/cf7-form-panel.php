<?php
/**
 * Contact Form 7 per-form settings panel.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="simple-honeypot-cf7-form-panel-box">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_form_time_mode' ); ?>"><?php esc_html_e( 'Timing check', 'simple-honeypot-cf7' ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_form_time_mode' ); ?>" name="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_form[time_mode]' ); ?>">
					<option value="inherit" <?php selected( $form_settings['time_mode'], 'inherit' ); ?>><?php esc_html_e( 'Use global setting', 'simple-honeypot-cf7' ); ?></option>
					<option value="enabled" <?php selected( $form_settings['time_mode'], 'enabled' ); ?>><?php esc_html_e( 'Enabled for this form', 'simple-honeypot-cf7' ); ?></option>
					<option value="disabled" <?php selected( $form_settings['time_mode'], 'disabled' ); ?>><?php esc_html_e( 'Disabled for this form', 'simple-honeypot-cf7' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Controls whether submissions to this form are checked for speed. When enabled, submissions sent faster than the minimum time are blocked.', 'simple-honeypot-cf7' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_form_min_time' ); ?>"><?php esc_html_e( 'Minimum submission time', 'simple-honeypot-cf7' ); ?></label></th>
			<td>
				<input type="number" class="small-text" id="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_form_min_time' ); ?>" name="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_form[min_time_seconds]' ); ?>" min="0" step="1" value="<?php echo esc_attr( $form_settings['min_time_seconds'] ); ?>" placeholder="0" />
				<?php esc_html_e( 'seconds', 'simple-honeypot-cf7' ); ?>
				<p class="description"><?php esc_html_e( 'Leave empty or set to 0 to use the global minimum time. Only applies when the timing check is enabled (either globally or per form).', 'simple-honeypot-cf7' ); ?></p>
			</td>
		</tr>
	</table>
	<p class="simple-honeypot-cf7-form-panel-reset">
		<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . SIMPLE_HONEYPOT_CF7_BASE . '_reset_form_settings&form_id=' . $form_id ), SIMPLE_HONEYPOT_CF7_BASE . '_reset_form_settings' ) ); ?>" class="button simple-honeypot-cf7-reset-form-settings" data-confirm="<?php echo esc_attr__( 'This will restore this form\'s settings to defaults.', 'simple-honeypot-cf7' ); ?>">
			<?php esc_html_e( 'Restore to defaults', 'simple-honeypot-cf7' ); ?>
		</a>
	</p>
</div>
