<?php
/**
 * Key/value report table.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $items ) ) :
	?>
	<div class="simple-honeypot-cf7-empty-state">
		<span class="dashicons dashicons-chart-pie"></span>
		<p><?php esc_html_e( 'No data yet. Spam attempts will appear here once they are blocked.', 'simple-honeypot-cf7' ); ?></p>
	</div>
	<?php
	return;
endif;

arsort( $items );
?>
<table class="widefat striped simple-honeypot-cf7-table simple-honeypot-cf7-breakdown">
	<thead>
		<tr>
			<th><?php echo esc_html( $label ); ?></th>
			<?php /* translators: table column header for number of occurrences */ ?>
			<th><?php esc_html_e( 'Count', 'simple-honeypot-cf7' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $items as $key => $count ) : ?>
			<tr>
				<td><?php echo esc_html( $key ); ?></td>
				<td><?php echo esc_html( number_format_i18n( absint( $count ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
