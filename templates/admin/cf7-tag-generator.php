<?php
/**
 * Contact Form 7 honeypot tag generator.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( ! empty( $modern ) && ! empty( $tag ) ) : ?>
	<header class="description-box">
		<h3><?php esc_html_e( 'Honeypot', 'simple-honeypot-cf7' ); ?></h3>
	</header>

	<div class="control-box">
		<?php
		$tag->print( 'field_type', array( 'select_options' => array( 'honeypot' => __( 'Honeypot', 'simple-honeypot-cf7' ) ) ) );
		$tag->print( 'field_name' );
		$tag->print( 'class_attr' );
		?>
	</div>

	<footer class="insert-box">
		<?php $tag->print( 'insert_box_content' ); ?>
	</footer>
<?php else : ?>
	<div class="control-box">
		<fieldset>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php esc_html_e( 'Name', 'default' ); ?></label></th>
						<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /></td>
					</tr>
				</tbody>
			</table>
		</fieldset>
	</div>

	<div class="insert-box">
		<input type="text" name="honeypot" class="tag code" readonly="readonly" onfocus="this.select()" />
		<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr__( 'Insert Tag', 'contact-form-7' ); ?>" />
		</div>
	</div>
<?php endif; ?>
