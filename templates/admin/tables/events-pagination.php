<?php
/**
 * Pagination controls for the events table.
 *
 * Expects $pagination array with keys: total, per_page, current_page, total_pages.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $pagination ) || $pagination['total_pages'] <= 1 ) {
	return;
}

$page_url = admin_url( 'admin.php?page=wpcf7&post_type=wpcf7_contact_form&tab=reports' );

$links = array();
for ( $i = 1; $i <= $pagination['total_pages']; $i++ ) {
	$links[] = add_query_arg( 'events_page', $i, $page_url );
}
?>
<div class="tablenav bottom">
	<div class="tablenav-pages">
		<?php if ( $pagination['total_pages'] <= 8 ) : ?>
			<span class="displaying-num">
				<?php
				printf(
					/* translators: number of events */
					esc_html( _n( '%s event', '%s events', $pagination['total'], 'simple-honeypot-cf7' ) ),
					esc_html( number_format_i18n( $pagination['total'] ) )
				);
				?>
			</span>
			<span class="pagination-links">
				<?php if ( $pagination['current_page'] > 1 ) : ?>
					<a class="prev-page button" href="<?php echo esc_url( $links[ $pagination['current_page'] - 2 ] ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'simple-honeypot-cf7' ); ?></span>
						<span aria-hidden="true">&laquo;</span>
					</a>
				<?php else : ?>
					<span class="prev-page button disabled" aria-disabled="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'simple-honeypot-cf7' ); ?></span>
						<span aria-hidden="true">&laquo;</span>
					</span>
				<?php endif; ?>

				<?php for ( $i = 1; $i <= $pagination['total_pages']; $i++ ) : ?>
					<?php if ( $i === $pagination['current_page'] ) : ?>
						<span class="paging-input">
							<span class="tablenav-paging-text">
								<?php
								echo esc_html( $i );
								echo ' of ';
								echo esc_html( $pagination['total_pages'] );
								?>
							</span>
						</span>
					<?php endif; ?>
				<?php endfor; ?>

				<?php if ( $pagination['current_page'] < $pagination['total_pages'] ) : ?>
					<a class="next-page button" href="<?php echo esc_url( $links[ $pagination['current_page'] ] ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'simple-honeypot-cf7' ); ?></span>
						<span aria-hidden="true">&raquo;</span>
					</a>
				<?php else : ?>
					<span class="next-page button disabled" aria-disabled="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'simple-honeypot-cf7' ); ?></span>
						<span aria-hidden="true">&raquo;</span>
					</span>
				<?php endif; ?>
			</span>
		<?php else : ?>
			<span class="displaying-num">
				<?php
				printf(
					/* translators: number of events */
					esc_html( _n( '%s event', '%s events', $pagination['total'], 'simple-honeypot-cf7' ) ),
					esc_html( number_format_i18n( $pagination['total'] ) )
				);
				?>
			</span>
			<span class="pagination-links">
				<?php if ( $pagination['current_page'] > 1 ) : ?>
					<a class="prev-page button" href="<?php echo esc_url( $links[ $pagination['current_page'] - 2 ] ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'simple-honeypot-cf7' ); ?></span>
						<span aria-hidden="true">&laquo;</span>
					</a>
				<?php else : ?>
					<span class="prev-page button disabled" aria-disabled="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'simple-honeypot-cf7' ); ?></span>
						<span aria-hidden="true">&laquo;</span>
					</span>
				<?php endif; ?>

				<span class="paging-input">
					<label for="events_page_input" class="screen-reader-text"><?php esc_html_e( 'Current Page', 'simple-honeypot-cf7' ); ?></label>
					<input id="events_page_input" class="current-page" type="number" min="1" max="<?php echo esc_attr( $pagination['total_pages'] ); ?>" value="<?php echo esc_attr( $pagination['current_page'] ); ?>" size="2" aria-describedby="events-page-description" />
					<span class="tablenav-paging-text">
						<?php
						echo ' of ';
						echo esc_html( $pagination['total_pages'] );
						?>
					</span>
				</span>

				<?php if ( $pagination['current_page'] < $pagination['total_pages'] ) : ?>
					<a class="next-page button" href="<?php echo esc_url( $links[ $pagination['current_page'] ] ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'simple-honeypot-cf7' ); ?></span>
						<span aria-hidden="true">&raquo;</span>
					</a>
				<?php else : ?>
					<span class="next-page button disabled" aria-disabled="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'simple-honeypot-cf7' ); ?></span>
						<span aria-hidden="true">&raquo;</span>
					</span>
				<?php endif; ?>
			</span>
		<?php endif; ?>
	</div>
</div>
