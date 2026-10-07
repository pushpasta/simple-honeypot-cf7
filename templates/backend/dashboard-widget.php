<?php
/**
 * CF7 dashboard widget content.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="shp4cf7-dashboard-widget">
	<div class="shp4cf7-dashboard-widget-stat">
		<span class="dashicons dashicons-calendar-alt shp4cf7-dashboard-widget-stat-icon" aria-hidden="true"></span>
		<div class="shp4cf7-dashboard-widget-stat-body">
			<strong class="shp4cf7-dashboard-widget-stat-value"><?php echo esc_html( wp_date( get_option( 'date_format' ), absint( $stats['run_since'] ) ) ); ?></strong>
			<span class="shp4cf7-dashboard-widget-stat-label"><?php esc_html_e( 'Active Since', 'simple-honeypot-cf7' ); ?></span>
		</div>
	</div>
	<div class="shp4cf7-dashboard-widget-stat">
		<span class="dashicons dashicons-shield shp4cf7-dashboard-widget-stat-icon" aria-hidden="true"></span>
		<div class="shp4cf7-dashboard-widget-stat-body">
			<strong class="shp4cf7-dashboard-widget-stat-value"><?php echo esc_html( number_format_i18n( absint( $stats['total'] ) ) ); ?></strong>
			<span class="shp4cf7-dashboard-widget-stat-label"><?php esc_html_e( 'Spam Attempts Blocked', 'simple-honeypot-cf7' ); ?></span>
		</div>
	</div>
	<?php if ( 0 === absint( $stats['total'] ) ) : ?>
		<p class="shp4cf7-dashboard-widget-note"><?php esc_html_e( 'No spam blocked yet. Stats will appear as attempts are blocked.', 'simple-honeypot-cf7' ); ?></p>
	<?php endif; ?>
	<p class="shp4cf7-dashboard-widget-footer">
		<a href="<?php echo esc_url( $reports_url ); ?>"><?php esc_html_e( 'View full reports', 'simple-honeypot-cf7' ); ?></a>
	</p>
</div>