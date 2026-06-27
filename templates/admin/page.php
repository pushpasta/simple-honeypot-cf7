<?php
/**
 * Admin page shell.
 *
 * @package Simple_Honeypot_CF7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$version_tooltip = sprintf(
	/* translators: %s: date */
	'%s ' . wp_date( get_option( 'date_format' ), filemtime( SIMPLE_HONEYPOT_CF7_PLUGIN_FILE ) ),
	__( 'Last updated:', 'simple-honeypot-cf7' )
);
?>
<div class="wrap simple-honeypot-cf7-admin">
	<h1><img src="<?php echo esc_url( SIMPLE_HONEYPOT_CF7_URL . 'resources/admin/img/shp-icon.png' ); ?>" alt="" style="width:36px;height:36px;border-radius:4px;margin-right:10px;vertical-align:middle;"><strong style="font-weight:700;">Simple Honeypot</strong> <?php esc_html_e( 'for Contact Form 7', 'simple-honeypot-cf7' ); ?> <span style="font-weight:400;font-size:13px;color:#646970;" title="<?php echo esc_attr( $version_tooltip ); ?>">v<?php echo esc_html( SIMPLE_HONEYPOT_CF7_VERSION ); ?></span></h1>
	<p class="description"><?php esc_html_e( 'Configure honeypot fields, timing checks, proof-of-work, custom rules, and spam reporting.', 'simple-honeypot-cf7' ); ?></p>

	<?php if ( ! empty( $notice ) ) : ?>
		<?php
		$notice_type = isset( $notice_type ) ? $notice_type : 'success';
		\SimpleHoneypotCF7\Admin\Notices::render( $notice, $notice_type );
		?>
	<?php endif; ?>

	<nav class="nav-tab-wrapper simple-honeypot-cf7-nav-tabs simple-honeypot-cf7-nav-tabs--<?php echo esc_attr( $current_tab ); ?>">
		<?php
		$tab_icons = array(
			'settings' => 'dashicons-admin-settings',
			'rules'    => 'dashicons-shield',
			'forms'    => 'dashicons-email-alt',
			'reports'  => 'dashicons-chart-bar',
		);
		foreach ( $tabs as $slug => $label ) :
			?>
			<?php
			$url  = add_query_arg(
				array(
					'page' => 'simple-honeypot-cf7',
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$icon = isset( $tab_icons[ $slug ] ) ? $tab_icons[ $slug ] : 'dashicons-admin-generic';
			?>
			<a class="nav-tab <?php echo esc_attr( $current_tab === $slug ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( $url ); ?>">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="simple-honeypot-cf7-layout">
		<div class="simple-honeypot-cf7-main">
		<?php
		if ( ! empty( $tab_context ) && is_array( $tab_context ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template data is intentionally exposed to the tab template.
			extract( $tab_context, EXTR_SKIP );
		}

		require SIMPLE_HONEYPOT_CF7_PATH . 'templates/' . $tab_template;
		?>
		</div>

		<aside class="simple-honeypot-cf7-sidebar">
			<div class="postbox simple-honeypot-cf7-card">
				<h2 class="hndle"><span class="dashicons dashicons-sos"></span><span><?php esc_html_e( 'Do you need help?', 'simple-honeypot-cf7' ); ?></span></h2>
				<div class="inside">
					<ul class="simple-honeypot-cf7-sidebar-links">
						<li><a href="https://github.com/pushpasta/simple-honeypot-cf7/issues" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-warning"></span><?php esc_html_e( 'Report a bug', 'simple-honeypot-cf7' ); ?></a></li>
						<li><a href="https://github.com/pushpasta/simple-honeypot-cf7/discussions" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-format-chat"></span><?php esc_html_e( 'Ask a question', 'simple-honeypot-cf7' ); ?></a></li>
						<li><a href="https://github.com/pushpasta/simple-honeypot-cf7/releases" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-archive"></span><?php esc_html_e( 'View releases', 'simple-honeypot-cf7' ); ?></a></li>
					</ul>
				</div>
			</div>
		</aside>
	</div>

	<hr style="margin-top:32px;">
	<p class="description">
		<?php esc_html_e( 'If Simple Honeypot helps protect your site, please support its development.', 'simple-honeypot-cf7' ); ?>
		<a href="<?php echo esc_url( 'https://github.com/pushpasta/simple-honeypot-cf7/?sponsor' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Donate', 'simple-honeypot-cf7' ); ?></a>
	</p>

	<dialog id="simple-honeypot-cf7-confirm-dialog" class="simple-honeypot-cf7-dialog">
		<div class="simple-honeypot-cf7-dialog-inner">
			<p class="simple-honeypot-cf7-confirm-message"></p>
			<div class="simple-honeypot-cf7-dialog-actions">
				<button type="button" class="button simple-honeypot-cf7-confirm-yes"><?php esc_html_e( 'Yes', 'simple-honeypot-cf7' ); ?></button>
				<button type="button" class="button simple-honeypot-cf7-confirm-no"><?php esc_html_e( 'No', 'simple-honeypot-cf7' ); ?></button>
			</div>
		</div>
	</dialog>
</div>
