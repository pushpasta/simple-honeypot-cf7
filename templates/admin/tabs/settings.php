<?php
/**
 * Settings tab.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="" enctype="multipart/form-data">
	<?php wp_nonce_field( SIMPLE_HONEYPOT_CF7_BASE . '_save_settings', SIMPLE_HONEYPOT_CF7_BASE . '_nonce' ); ?>
	<input type="hidden" name="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_action' ); ?>" value="save" />
	<input type="hidden" name="tab" value="settings" />

	<div class="postbox simple-honeypot-cf7-card">
		<h2 class="hndle"><span class="dashicons dashicons-clock"></span><span><?php esc_html_e( 'Time Check', 'simple-honeypot-cf7' ); ?></span></h2>
		<div class="inside">
			<p class="description"><?php esc_html_e( 'Blocks submissions that arrive faster than a human could reasonably fill out the form.', 'simple-honeypot-cf7' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="time_check_enabled"><?php esc_html_e( 'Enable time check', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="time_check_enabled" name="time_check_enabled" value="1" <?php checked( $settings['time_check_enabled'], 1 ); ?> />
							<?php esc_html_e( 'Flag submissions that arrive too quickly.', 'simple-honeypot-cf7' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="min_time_seconds"><?php esc_html_e( 'Minimum time', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<input type="number" class="small-text" id="min_time_seconds" name="min_time_seconds" min="0" step="1" value="<?php echo esc_attr( $settings['min_time_seconds'] ); ?>" placeholder="4" />
						<?php esc_html_e( 'seconds', 'simple-honeypot-cf7' ); ?>
						<p class="description"><?php esc_html_e( 'Minimum time required between form submissions.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="postbox simple-honeypot-cf7-card">
		<h2 class="hndle"><span class="dashicons dashicons-lock"></span><span><?php esc_html_e( 'Token', 'simple-honeypot-cf7' ); ?></span></h2>
		<div class="inside">
			<p class="description"><?php esc_html_e( 'Each form generates a unique token to verify the submission originated from your site.', 'simple-honeypot-cf7' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="max_age_minutes"><?php esc_html_e( 'Token lifetime', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<input type="number" class="small-text" id="max_age_minutes" name="max_age_minutes" min="10" step="1" value="<?php echo esc_attr( $settings['max_age_minutes'] ); ?>" placeholder="120" />
						<?php esc_html_e( 'minutes', 'simple-honeypot-cf7' ); ?>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="postbox simple-honeypot-cf7-card">
		<h2 class="hndle"><span class="dashicons dashicons-shield"></span><span><?php esc_html_e( 'Proof of Work', 'simple-honeypot-cf7' ); ?></span></h2>
		<div class="inside">
			<p class="description"><?php esc_html_e( 'Requires the visitor\'s browser to solve a small computational puzzle before submitting. Adds friction for bots while remaining imperceptible to humans. Requires JavaScript and HTTPS.', 'simple-honeypot-cf7' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pow_enabled"><?php esc_html_e( 'Enable Proof of Work', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="pow_enabled" name="pow_enabled" value="1" <?php checked( $settings['pow_enabled'], 1 ); ?> />
							<?php esc_html_e( 'Add a client-side hashcash-style puzzle to each form submission.', 'simple-honeypot-cf7' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pow_complexity"><?php esc_html_e( 'Puzzle complexity', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<input type="number" class="small-text" id="pow_complexity" name="pow_complexity" min="4" max="20" step="1" value="<?php echo esc_attr( $settings['pow_complexity'] ); ?>" placeholder="8" />
						<?php esc_html_e( 'leading zero bits (4 = fast, 20 = slow)', 'simple-honeypot-cf7' ); ?>
						<p class="description"><?php esc_html_e( 'Each additional bit doubles the work required. Default (8) takes ~50&#8211;100ms in a modern browser. Values above 14 may take several seconds.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="postbox simple-honeypot-cf7-card">
		<h2 class="hndle"><span class="dashicons dashicons-database"></span><span><?php esc_html_e( 'Data', 'simple-honeypot-cf7' ); ?></span></h2>
		<div class="inside">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="store_honeypot_value"><?php esc_html_e( 'Store honeypot value', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="store_honeypot_value" name="store_honeypot_value" value="1" <?php checked( $settings['store_honeypot_value'], 1 ); ?> />
							<?php esc_html_e( 'Keep filled honeypot values in posted data for record plugins such as Flamingo.', 'simple-honeypot-cf7' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="postbox simple-honeypot-cf7-card">
		<h2 class="hndle"><span class="dashicons dashicons-database"></span><span><?php esc_html_e( 'Recent Events', 'simple-honeypot-cf7' ); ?></span></h2>
		<div class="inside">
			<p class="description"><?php esc_html_e( 'Control how many recent blocked submissions are retained in the database.', 'simple-honeypot-cf7' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="keep_recent_events"><?php esc_html_e( 'Events to keep', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<input type="number" class="small-text" id="keep_recent_events" name="keep_recent_events" min="10" step="1" value="<?php echo esc_attr( $settings['keep_recent_events'] ); ?>" placeholder="1000" />
						<?php esc_html_e( 'recent events', 'simple-honeypot-cf7' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="purge_events_after_days"><?php esc_html_e( 'Auto-delete events older than', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<input type="number" class="small-text" id="purge_events_after_days" name="purge_events_after_days" min="0" step="1" value="<?php echo esc_attr( $settings['purge_events_after_days'] ); ?>" placeholder="0" />
						<?php esc_html_e( 'days (0 = disabled)', 'simple-honeypot-cf7' ); ?>
						<p class="description"><?php esc_html_e( 'Automatically removes events older than the specified number of days each time a new event is recorded. Set to 0 to disable.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="events_per_page"><?php esc_html_e( 'Events per page', 'simple-honeypot-cf7' ); ?></label></th>
					<td>
						<input type="number" class="small-text" id="events_per_page" name="events_per_page" min="5" step="1" value="<?php echo esc_attr( $settings['events_per_page'] ); ?>" placeholder="20" />
						<?php esc_html_e( 'entries shown on the Reports tab', 'simple-honeypot-cf7' ); ?>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<?php submit_button( __( 'Save', 'simple-honeypot-cf7' ) ); ?>

</form>
