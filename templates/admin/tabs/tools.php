<?php
/**
 * Tools tab.
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
	<input type="hidden" name="tab" value="tools" />

	<div class="postbox shp4cf7-card" id="shp4cf7-import-export">
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
						<input type="file" id="shp4cf7-import-file" name="import_file" accept=".json" class="shp4cf7-import-file-input" />
						<label for="shp4cf7-import-file" class="button shp4cf7-import-file-label"><?php esc_html_e( 'Choose File', 'simple-honeypot-cf7' ); ?></label>
						<button type="submit" id="shp4cf7-import-btn" name="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_import_settings' ); ?>" value="1" class="button button-primary" disabled><?php esc_html_e( 'Import Settings', 'simple-honeypot-cf7' ); ?></button>
						<p class="description"><?php esc_html_e( 'Upload a previously exported JSON file. Settings in the file will overwrite current values. Settings not in the file remain unchanged.', 'simple-honeypot-cf7' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>
</form>

<div class="postbox shp4cf7-card" id="shp4cf7-updates">
	<h2 class="hndle"><span class="dashicons dashicons-update"></span><span><?php esc_html_e( 'Updates', 'simple-honeypot-cf7' ); ?></span></h2>
	<div class="inside">
		<p class="description"><?php esc_html_e( 'Force WordPress to check for plugin updates immediately instead of waiting for the next scheduled check.', 'simple-honeypot-cf7' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Force update check', 'simple-honeypot-cf7' ); ?></th>
				<td>
					<button type="button" class="button" data-action="force_update_check" data-confirm="<?php echo esc_attr__( 'This will clear the WordPress update cache and force a fresh check for all plugin updates.', 'simple-honeypot-cf7' ); ?>"><?php esc_html_e( 'Force Update Check', 'simple-honeypot-cf7' ); ?></button>
				</td>
			</tr>
		</table>
	</div>
</div>

<div class="postbox shp4cf7-card shp4cf7-card--danger" id="shp4cf7-danger-zone">
	<h2 class="hndle"><span class="dashicons dashicons-warning"></span><span><?php esc_html_e( 'Danger Zone', 'simple-honeypot-cf7' ); ?></span></h2>
	<div class="inside">
		<p class="description"><?php esc_html_e( 'Destructive actions that cannot be undone. Use with caution.', 'simple-honeypot-cf7' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Purge old events', 'simple-honeypot-cf7' ); ?></th>
				<td>
					<label for="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_purge_days' ); ?>"><?php esc_html_e( 'Delete events older than', 'simple-honeypot-cf7' ); ?></label>
					<input type="number" id="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_purge_days' ); ?>" class="small-text" min="1" step="1" value="90" placeholder="90" />
					<?php esc_html_e( 'days', 'simple-honeypot-cf7' ); ?>
					<?php /* translators: %d: number of days */ ?>
					<button type="button" class="button button-delete" data-action="purge_events" data-confirm="<?php echo esc_attr__( 'This will permanently delete event data older than <strong>%d</strong> day(s).', 'simple-honeypot-cf7' ); ?>" data-confirm-days="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_purge_days' ); ?>" data-confirm-danger="1"><?php esc_html_e( 'Purge Old Events', 'simple-honeypot-cf7' ); ?></button>
					<p class="description"><?php esc_html_e( 'Delete events older than the specified number of days.', 'simple-honeypot-cf7' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Clear reporting data', 'simple-honeypot-cf7' ); ?></th>
				<td>
					<button type="button" class="button button-delete" data-action="reset_stats" data-confirm="<?php echo esc_attr__( 'This will clear all spam stats, breakdowns, and event logs. This cannot be undone.', 'simple-honeypot-cf7' ); ?>" data-confirm-danger="1"><?php esc_html_e( 'Clear Reporting Data', 'simple-honeypot-cf7' ); ?></button>
					<p class="description"><?php esc_html_e( 'Clear all spam stats, breakdowns, and event logs. This cannot be undone.', 'simple-honeypot-cf7' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Reset all settings', 'simple-honeypot-cf7' ); ?></th>
				<td>
					<button type="button" class="button button-delete" data-action="reset_settings" data-confirm="<?php echo esc_attr__( 'This will reset all global settings to defaults. Reporting data and per-form settings will not be affected.', 'simple-honeypot-cf7' ); ?>" data-confirm-danger="1"><?php esc_html_e( 'Reset All Settings', 'simple-honeypot-cf7' ); ?></button>
					<p class="description"><?php esc_html_e( 'Reset all global settings to their defaults. Reporting data and per-form settings are not affected.', 'simple-honeypot-cf7' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
</div>
