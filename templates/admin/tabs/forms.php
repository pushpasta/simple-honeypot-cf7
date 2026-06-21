<?php
/**
 * Forms tab.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="postbox simple-honeypot-cf7-card">
	<h2 class="hndle"><span class="dashicons dashicons-email-alt"></span><span><?php esc_html_e( 'Forms Overview', 'simple-honeypot-cf7' ); ?></span></h2>
	<div class="inside">
		<p class="description"><?php esc_html_e( 'Contact Form 7 forms with custom settings. Forms using only global defaults are not shown.', 'simple-honeypot-cf7' ); ?></p>
		<?php
		if ( empty( $forms_with_overrides ) ) :
			?>
			<div class="simple-honeypot-cf7-empty-state">
				<span class="dashicons dashicons-yes-alt"></span>
				<p><?php esc_html_e( 'No forms with custom settings yet. Per-form overrides will appear here once saved.', 'simple-honeypot-cf7' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			require SIMPLE_HONEYPOT_CF7_PATH . 'templates/admin/tables/forms-overview-table.php';
			?>
		<?php endif; ?>
	</div>
</div>
