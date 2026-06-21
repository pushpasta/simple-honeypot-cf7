<?php
/**
 * Forms overview table.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="widefat striped simple-honeypot-cf7-table">
	<thead>
		<tr>
			<?php /* translators: table column header for contact form name */ ?>
			<th><?php esc_html_e( 'Form', 'simple-honeypot-cf7' ); ?></th>
			<?php /* translators: table column header for timing check mode (enabled/disabled/uses global) */ ?>
			<th><?php esc_html_e( 'Timing', 'simple-honeypot-cf7' ); ?></th>
			<?php /* translators: table column header for minimum submission time in seconds */ ?>
			<th><?php esc_html_e( 'Min. Time', 'simple-honeypot-cf7' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $forms_with_overrides as $form ) : ?>
			<tr>
				<td>
					<a href="<?php echo esc_url( $form['edit_url'] ); ?>"><?php echo esc_html( $form['title'] ); ?></a>
				</td>
				<td>
					<?php if ( ! $form['time_mode_custom'] ) : ?>
						<span class="simple-honeypot-cf7-badge simple-honeypot-cf7-badge--inherited">
						<?php
						/* translators: %s: resolved mode (Enabled or Disabled) inherited from global settings */
						printf( esc_html__( 'Uses global (%s)', 'simple-honeypot-cf7' ), esc_html( 'enabled' === $form['resolved_mode'] ? __( 'Enabled', 'simple-honeypot-cf7' ) : __( 'Disabled', 'simple-honeypot-cf7' ) ) );
						?>
					</span>
				<?php elseif ( 'enabled' === $form['time_mode'] ) : ?>
					<span class="simple-honeypot-cf7-badge simple-honeypot-cf7-badge--active"><?php esc_html_e( 'Enabled', 'simple-honeypot-cf7' ); ?></span>
				<?php else : ?>
					<span class="simple-honeypot-cf7-badge simple-honeypot-cf7-badge--inactive"><?php esc_html_e( 'Disabled', 'simple-honeypot-cf7' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( ! $form['min_time_custom'] ) : ?>
						<span class="simple-honeypot-cf7-badge simple-honeypot-cf7-badge--inherited">
							<?php
							/* translators: %d: number of seconds inherited from global settings */
							printf( esc_html__( 'Uses global (%d)', 'simple-honeypot-cf7' ), absint( $form['resolved_min'] ) );
							?>
						</span>
					<?php else : ?>
						<?php
						printf(
							/* translators: %d: number of seconds */
							esc_html__( '%d seconds', 'simple-honeypot-cf7' ),
							absint( $form['min_time_seconds'] )
						);
						?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
