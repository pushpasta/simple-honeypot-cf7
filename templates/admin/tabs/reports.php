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
<div class="postbox simple-honeypot-cf7-card">
	<h2 class="hndle"><span class="dashicons dashicons-chart-bar"></span><span><?php esc_html_e( 'Overview', 'simple-honeypot-cf7' ); ?></span></h2>
	<div class="inside">
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
						<span class="simple-honeypot-cf7-badge simple-honeypot-cf7-badge--<?php echo esc_attr( ! empty( $settings['custom_rules_enabled'] ) ? 'active' : 'inactive' ); ?>"><?php echo ! empty( $settings['custom_rules_enabled'] ) ? esc_html__( 'Active', 'simple-honeypot-cf7' ) : esc_html__( 'Inactive', 'simple-honeypot-cf7' ); ?></span>
					<?php endif; ?>
				</span>
			</div>
		</div>
	</div>
</div>

<div class="postbox simple-honeypot-cf7-card">
	<h2 class="hndle"><span class="dashicons dashicons-chart-pie"></span><span><?php esc_html_e( 'Breakdown', 'simple-honeypot-cf7' ); ?></span></h2>
	<div class="inside">
		<?php if ( 0 === $spam_counts['total'] ) : ?>
			<div class="simple-honeypot-cf7-empty-state">
				<span class="dashicons dashicons-chart-pie"></span>
				<p><?php esc_html_e( 'No data yet. Spam attempts will appear here once they are blocked.', 'simple-honeypot-cf7' ); ?></p>
			</div>
		<?php else : ?>
			<div class="simple-honeypot-cf7-breakdown-grid">
				<div class="simple-honeypot-cf7-breakdown-box">
					<h3><?php esc_html_e( 'By Time Period', 'simple-honeypot-cf7' ); ?></h3>
					<p class="description"><?php esc_html_e( 'How many spam attempts were blocked each day.', 'simple-honeypot-cf7' ); ?></p>
					<dl class="simple-honeypot-cf7-sidebar-stats">
						<dt><?php esc_html_e( 'Today', 'simple-honeypot-cf7' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( $spam_counts['today'] ) ); ?></dd>
						<dt><?php esc_html_e( 'Yesterday', 'simple-honeypot-cf7' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( $spam_counts['yesterday'] ) ); ?></dd>
						<dt><?php esc_html_e( 'Last 7 days', 'simple-honeypot-cf7' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( $spam_counts['last_7_days'] ) ); ?></dd>
						<dt><?php esc_html_e( 'This month', 'simple-honeypot-cf7' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( $spam_counts['this_month'] ) ); ?></dd>
						<dt><?php esc_html_e( 'Last month', 'simple-honeypot-cf7' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( $spam_counts['last_month'] ) ); ?></dd>
						<dt><strong><?php esc_html_e( 'Total', 'simple-honeypot-cf7' ); ?></strong></dt>
						<dd><strong><?php echo esc_html( number_format_i18n( $spam_counts['total'] ) ); ?></strong></dd>
					</dl>
				</div>
				<div class="simple-honeypot-cf7-breakdown-box">
					<h3><?php esc_html_e( 'By Reason', 'simple-honeypot-cf7' ); ?></h3>
					<p class="description"><?php esc_html_e( 'What triggered the spam detection most often.', 'simple-honeypot-cf7' ); ?></p>
					<?php arsort( $stats['reasons'] ); ?>
					<dl class="simple-honeypot-cf7-sidebar-stats">
						<?php foreach ( $stats['reasons'] as $reason => $count ) : ?>
							<dt><?php echo esc_html( $reason ); ?></dt>
							<dd><?php echo esc_html( number_format_i18n( absint( $count ) ) ); ?></dd>
						<?php endforeach; ?>
					</dl>
				</div>
				<div class="simple-honeypot-cf7-breakdown-box">
					<h3><?php esc_html_e( 'By Form', 'simple-honeypot-cf7' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Which forms received the most spam.', 'simple-honeypot-cf7' ); ?></p>
					<dl class="simple-honeypot-cf7-sidebar-stats">
						<?php foreach ( $stats['forms'] as $form_id => $form ) : ?>
							<dt>
								<?php
								$form_title = isset( $form['title'] ) ? $form['title'] : __( 'Unknown form', 'simple-honeypot-cf7' );
								if ( $form_id && is_numeric( $form_id ) ) {
									$edit_url = admin_url( 'admin.php?page=wpcf7&post=' . absint( $form_id ) . '&action=edit' );
									printf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html( $form_title ) );
								} else {
									echo esc_html( $form_title );
								}
								?>
							</dt>
							<dd><?php echo esc_html( number_format_i18n( absint( isset( $form['count'] ) ? $form['count'] : 0 ) ) ); ?></dd>
						<?php endforeach; ?>
					</dl>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="postbox simple-honeypot-cf7-card">
	<h2 class="hndle"><span class="dashicons dashicons-list-view"></span><span><?php esc_html_e( 'Recent Events', 'simple-honeypot-cf7' ); ?></span></h2>
	<div class="inside">
		<?php
		$events = $stats['events'];
		require SIMPLE_HONEYPOT_CF7_PATH . 'templates/admin/tables/events-table.php';
		require SIMPLE_HONEYPOT_CF7_PATH . 'templates/admin/tables/events-pagination.php';
		?>
	</div>
</div>
