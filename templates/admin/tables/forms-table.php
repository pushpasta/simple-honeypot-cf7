<?php
/**
 * Form report table.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $forms ) ) :
	?>
	<div class="simple-honeypot-cf7-empty-state">
		<span class="dashicons dashicons-email-alt"></span>
		<p><?php esc_html_e( 'No form data yet. Per-form statistics will appear here.', 'simple-honeypot-cf7' ); ?></p>
	</div>
	<?php
	return;
endif;
?>
<table class="widefat striped simple-honeypot-cf7-table simple-honeypot-cf7-breakdown">
	<thead>
		<tr>
			<?php /* translators: table column header for contact form name */ ?>
			<th><?php esc_html_e( 'Form', 'simple-honeypot-cf7' ); ?></th>
			<?php /* translators: table column header for count of items */ ?>
			<th><?php esc_html_e( 'Count', 'simple-honeypot-cf7' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $forms as $form_key => $form ) : ?>
			<tr>
				<td>
				<?php
					$form_title = isset( $form['title'] ) ? $form['title'] : __( 'Unknown form', 'simple-honeypot-cf7' );
				if ( $form_key && is_numeric( $form_key ) ) {
					$edit_url = admin_url( 'admin.php?page=wpcf7&post=' . absint( $form_key ) . '&action=edit' );
					echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $form_title ) . '</a>';
				} else {
					echo esc_html( $form_title );
				}
				?>
				</td>
				<td><?php echo esc_html( number_format_i18n( absint( isset( $form['count'] ) ? $form['count'] : 0 ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
