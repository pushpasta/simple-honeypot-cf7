<?php
/**
 * Success notice.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="notice <?php echo esc_attr( empty( $notice_type ) ? 'notice-success' : $notice_type ); ?> is-dismissible">
	<p><?php echo esc_html( $notice ); ?></p>
</div>
