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
			<p class="description"><?php esc_html_e( 'Blocks submissions sent faster than a human could reasonably fill out the form.', 'simple-honeypot-cf7' ); ?></p>
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
			<p class="description"><?php esc_html_e( 'Requires the visitor\'s browser to solve a small computational puzzle before submitting. Adds friction for bots while being imperceptible to humans. Requires JavaScript and a secure (HTTPS) connection.', 'simple-honeypot-cf7' ); ?></p>
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
						<p class="description"><?php esc_html_e( 'Automatically remove events older than this many days each time a new event is recorded. Set to 0 to disable.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<?php submit_button( __( 'Save', 'simple-honeypot-cf7' ) ); ?>

	<div class="postbox simple-honeypot-cf7-card">
		<h2 class="hndle"><span class="dashicons dashicons-upload"></span><span><?php esc_html_e( 'Import &amp; Export', 'simple-honeypot-cf7' ); ?></span></h2>
		<div class="inside">
			<p class="description"><?php esc_html_e( 'Export and import all plugin settings as a JSON file.', 'simple-honeypot-cf7' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Export settings', 'simple-honeypot-cf7' ); ?></th>
					<td>
						<a href="<?php echo esc_url( $export_url ); ?>" class="button"><?php esc_html_e( 'Export Settings', 'simple-honeypot-cf7' ); ?></a>
						<p class="description"><?php esc_html_e( 'Downloads a JSON file with all global and per-form settings.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Import settings', 'simple-honeypot-cf7' ); ?></th>
					<td>
						<input type="file" id="simple-honeypot-cf7-import-file" name="import_file" accept=".json" />
						<button type="submit" id="simple-honeypot-cf7-import-btn" name="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_action' ); ?>" value="import_settings" class="button" disabled><?php esc_html_e( 'Import Settings', 'simple-honeypot-cf7' ); ?></button>
						<p class="description"><?php esc_html_e( 'Upload a previously exported JSON file. Settings present in the file will be overwritten. Settings not mentioned will remain unchanged.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="postbox simple-honeypot-cf7-card simple-honeypot-cf7-card--danger">
		<h2 class="hndle"><span class="dashicons dashicons-warning"></span><span><?php esc_html_e( 'Danger Zone', 'simple-honeypot-cf7' ); ?></span></h2>
		<div class="inside">
			<p class="description"><?php esc_html_e( 'Destructive actions that cannot be undone. Use with caution.', 'simple-honeypot-cf7' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Clear reporting data', 'simple-honeypot-cf7' ); ?></th>
					<td>
						<button type="submit" name="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_action' ); ?>" value="reset_stats" class="button button-delete" data-confirm="<?php echo esc_attr( __( 'Are you sure you want to clear reporting data? This cannot be undone.', 'simple-honeypot-cf7' ) ); ?>"><?php esc_html_e( 'Clear Reporting Data', 'simple-honeypot-cf7' ); ?></button>
						<p class="description"><?php esc_html_e( 'Removes all recorded spam stats, breakdowns, and event logs.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Reset all settings', 'simple-honeypot-cf7' ); ?></th>
					<td>
						<button type="submit" name="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_action' ); ?>" value="reset_settings" class="button button-delete" data-confirm="<?php echo esc_attr( __( 'Are you sure you want to reset all settings to defaults? Reporting data and per-form settings will not be affected.', 'simple-honeypot-cf7' ) ); ?>"><?php esc_html_e( 'Reset All Settings', 'simple-honeypot-cf7' ); ?></button>
						<p class="description"><?php esc_html_e( 'Restores every global setting to its original default value. Your report data and individual form overrides remain untouched.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

</form>
