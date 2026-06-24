<?php
/**
 * Hidden honeypot field markup.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<span class="wpcf7-form-control-wrap" data-name="<?php echo esc_attr( $tag_name ); ?>" style="<?php echo esc_attr( $hiding_style ); ?>">
	<input type="hidden" name="<?php echo esc_attr( $tokens_field_name ); ?>[]" value="<?php echo esc_attr( $token ); ?>" />
	<input size="40" class="<?php echo esc_attr( $class ); ?>" aria-invalid="false" type="text" name="<?php echo esc_attr( $dynamic_name ); ?>" value="" tabindex="-1" />
</span>
