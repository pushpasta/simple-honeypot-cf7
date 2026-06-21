<?php
/**
 * Reports tab.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="simple-honeypot-cf7-report-grid">
	<div class="simple-honeypot-cf7-stat">
		<div class="simple-honeypot-cf7-stat-icon">
			<span class="dashicons dashicons-calendar-alt"></span>
		</div>
		<strong><?php echo esc_html( wp_date( get_option( 'date_format' ), absint( $stats['run_since'] ) ) ); ?></strong>
		<span><?php esc_html_e( 'Active Since', 'simple-honeypot-cf7' ); ?></span>
	</div>
	<div class="simple-honeypot-cf7-stat">
		<div class="simple-honeypot-cf7-stat-icon">
			<span class="dashicons dashicons-shield"></span>
		</div>
		<strong><?php echo esc_html( number_format_i18n( absint( $stats['total'] ) ) ); ?></strong>
		<span><?php esc_html_e( 'Spam Attempts Blocked', 'simple-honeypot-cf7' ); ?></span>
	</div>
	<div class="simple-honeypot-cf7-stat">
		<div class="simple-honeypot-cf7-stat-icon">
			<span class="dashicons dashicons-forms"></span>
		</div>
		<strong><?php echo esc_html( number_format_i18n( count( $stats['forms'] ) ) ); ?></strong>
		<span><?php esc_html_e( 'Forms Protected', 'simple-honeypot-cf7' ); ?></span>
	</div>
	<div class="simple-honeypot-cf7-stat">
		<div class="simple-honeypot-cf7-stat-icon">
			<span class="dashicons dashicons-filter"></span>
		</div>
		<strong><?php echo esc_html( number_format_i18n( count( $stats['reasons'] ) ) ); ?></strong>
		<span><?php esc_html_e( 'Unique Reasons', 'simple-honeypot-cf7' ); ?></span>
	</div>
	<div class="simple-honeypot-cf7-stat">
		<div class="simple-honeypot-cf7-stat-icon">
			<span class="dashicons dashicons-shield-alt"></span>
		</div>
		<strong><?php echo empty( $parsed_rules ) ? '—' : esc_html( number_format_i18n( count( $parsed_rules ) ) ); ?></strong>
		<span>
			<?php esc_html_e( 'Rules', 'simple-honeypot-cf7' ); ?>
			<?php if ( ! empty( $parsed_rules ) ) : ?>
				<span class="simple-honeypot-cf7-badge simple-honeypot-cf7-badge--<?php echo ! empty( $settings['custom_rules_enabled'] ) ? 'active' : 'inactive'; ?>"><?php echo ! empty( $settings['custom_rules_enabled'] ) ? esc_html__( 'Active', 'simple-honeypot-cf7' ) : esc_html__( 'Inactive', 'simple-honeypot-cf7' ); ?></span>
			<?php endif; ?>
		</span>
	</div>
</div>

<h2><span class="dashicons dashicons-chart-pie"></span><?php esc_html_e( 'Reason Breakdown', 'simple-honeypot-cf7' ); ?></h2>
<div class="simple-honeypot-cf7-breakdown-wrapper">
<?php
$items = $stats['reasons'];
$label = __( 'Reason', 'simple-honeypot-cf7' );
require SIMPLE_HONEYPOT_CF7_PATH . 'templates/admin/tables/key-value-table.php';
?>
</div>

<h2><span class="dashicons dashicons-email-alt"></span><?php esc_html_e( 'Form Breakdown', 'simple-honeypot-cf7' ); ?></h2>
<div class="simple-honeypot-cf7-breakdown-wrapper">
<?php
$forms = $stats['forms'];
require SIMPLE_HONEYPOT_CF7_PATH . 'templates/admin/tables/forms-table.php';
?>
</div>

<h2><span class="dashicons dashicons-list-view"></span><?php esc_html_e( 'Recent Events', 'simple-honeypot-cf7' ); ?></h2>
<?php
$events = $stats['events'];
require SIMPLE_HONEYPOT_CF7_PATH . 'templates/admin/tables/events-table.php';
?>
