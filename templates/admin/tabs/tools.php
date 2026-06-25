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
						<button type="submit" id="simple-honeypot-cf7-import-btn" name="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_import_settings' ); ?>" value="1" class="button" disabled><?php esc_html_e( 'Import Settings', 'simple-honeypot-cf7' ); ?></button>
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

	<?php submit_button( __( 'Save', 'simple-honeypot-cf7' ) ); ?>
</form>

<div class="postbox simple-honeypot-cf7-card simple-honeypot-cf7-card--danger">
	<h2 class="hndle"><span class="dashicons dashicons-trash"></span><span><?php esc_html_e( 'Purge Old Events', 'simple-honeypot-cf7' ); ?></span></h2>
	<div class="inside">
		<p class="description"><?php esc_html_e( 'Permanently delete old event data from the database.', 'simple-honeypot-cf7' ); ?></p>
		<label for="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_purge_days' ); ?>"><?php esc_html_e( 'Delete events older than', 'simple-honeypot-cf7' ); ?></label>
		<input type="number" id="<?php echo esc_attr( SIMPLE_HONEYPOT_CF7_BASE . '_purge_days' ); ?>" class="small-text" min="1" step="1" value="90" placeholder="90" />
		<?php esc_html_e( 'days', 'simple-honeypot-cf7' ); ?>
		<?php
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . SIMPLE_HONEYPOT_CF7_BASE . '_purge_events' ),
			SIMPLE_HONEYPOT_CF7_BASE . '_purge_events'
		);
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="button button-delete simple-honeypot-cf7-purge-events-btn" data-confirm="<?php echo esc_attr__( 'Are you sure? This will permanently delete old event data.', 'simple-honeypot-cf7' ); ?>">
			<?php esc_html_e( 'Purge Old Events', 'simple-honeypot-cf7' ); ?>
		</a>
	</div>
</div>
