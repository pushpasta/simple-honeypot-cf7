<?php
/**
 * Recent events report table.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $events ) ) :
	?>
	<div class="simple-honeypot-cf7-empty-state">
		<span class="dashicons dashicons-list-view"></span>
		<p><?php esc_html_e( 'No events recorded yet. Recent spam attempts will be listed here.', 'simple-honeypot-cf7' ); ?></p>
	</div>
	<?php
	return;
endif;
?>
<table class="widefat striped simple-honeypot-cf7-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Date' ); ?></th>
			<th><?php esc_html_e( 'Form' ); ?></th>
			<th><?php esc_html_e( 'IP Address' ); ?></th>
			<th><?php esc_html_e( 'User Agent' ); ?></th>
			<th><?php esc_html_e( 'Reason' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $events as $event ) : ?>
			<tr>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), absint( isset( $event['time'] ) ? $event['time'] : 0 ) ) ); ?></td>
				<td>
				<?php
				if ( ! empty( $event['form_id'] ) ) {
					$form_edit_url = admin_url( 'admin.php?page=wpcf7&post=' . absint( $event['form_id'] ) . '&action=edit' );
					echo '<a href="' . esc_url( $form_edit_url ) . '">' . esc_html( isset( $event['form_title'] ) ? $event['form_title'] : __( 'Unknown form', 'simple-honeypot-cf7' ) ) . '</a>';
				} else {
					echo esc_html( isset( $event['form_title'] ) ? $event['form_title'] : __( 'Unknown form', 'simple-honeypot-cf7' ) );
				}
				?>
				</td>
				<td>
				<?php
				$ip = isset( $event['ip'] ) ? $event['ip'] : '';
				if ( '' !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					printf(
						'<a href="https://www.abuseipdb.com/check/%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_attr( $ip ),
						esc_html( $ip )
					);
				} else {
					echo esc_html( $ip );
				}
				?>
			</td>
				<td><?php echo esc_html( isset( $event['user_agent'] ) ? $event['user_agent'] : '' ); ?></td>
				<td>
					<?php
					$reasons = (array) ( isset( $event['reasons'] ) ? $event['reasons'] : array() );
					if ( ! empty( $reasons ) ) :
						?>
						<ul class="simple-honeypot-cf7-reasons-list">
							<?php foreach ( $reasons as $reason ) : ?>
								<li><?php echo esc_html( isset( $reason['message'] ) ? $reason['message'] : '' ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						&mdash;
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
