<?php
/**
 * Database migration runner.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs versioned database migrations on plugin activation.
 *
 * Each migration is a static method named migrate_to_N where N is the
 * target version. The stored version is tracked via the shp4cf7_db_version
 * option so migrations only run once.
 */
final class Upgrader {

	/**
	 * Option name that stores the current database schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = SIMPLE_HONEYPOT_CF7_BASE . '_db_version';

	/**
	 * The database version this codebase expects.
	 *
	 * @var int
	 */
	const CURRENT_DB_VERSION = 2;

	/**
	 * Legacy option names that were renamed in migration 2.
	 *
	 * @var string[]
	 */
	const LEGACY_OPTIONS = array(
		'simple_honeypot_cf7_settings',
		'simple_honeypot_cf7_stats',
	);

	/**
	 * Run all pending migrations.
	 *
	 * @return void
	 */
	public static function run() {
		$stored = (int) get_option( self::DB_VERSION_OPTION, 1 );

		if ( $stored >= self::CURRENT_DB_VERSION ) {
			return;
		}

		if ( $stored < 2 ) {
			self::migrate_to_2();
		}

		// Future migrations go here.

		update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION, false );
	}

	/**
	 * Migration v2: rename all storage keys to shp4cf7_ prefix.
	 *
	 * - Option names: simple_honeypot_cf7_* → shp4cf7_*
	 * - Post meta: _simple_honeypot_cf7_settings → _shp4cf7_settings
	 * - DB table: simple_honeypot_cf7_events → shp4cf7_events
	 * - Transients: simple_honeypot_cf7_* → shp4cf7_*
	 * - Site transients: shcf7_* → shp4cf7_*
	 *
	 * @return void
	 */
	private static function migrate_to_2() {
		global $wpdb;

		// 1. Rename wp_options.
		self::rename_option( 'simple_honeypot_cf7_settings', 'shp4cf7_settings' );
		self::rename_option( 'simple_honeypot_cf7_stats', 'shp4cf7_stats' );

		// 2. Rename post meta for all CF7 forms.
		$forms = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		foreach ( $forms as $form_id ) {
			$meta = get_post_meta( $form_id, '_simple_honeypot_cf7_settings', true );

			if ( is_array( $meta ) ) {
				update_post_meta( $form_id, '_shp4cf7_settings', $meta );
			}

			delete_post_meta( $form_id, '_simple_honeypot_cf7_settings' );
		}

		// 3. Drop old events table if it exists.
		$old_table = $wpdb->prefix . 'simple_honeypot_cf7_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$old_table}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( $wpdb->prefix . 'simple_honeypot_cf7_events_db_version' );

		// 4. Delete old transients.
		delete_transient( 'simple_honeypot_cf7_reset_notice' );
		delete_transient( 'simple_honeypot_cf7_purge_notice' );

		// 5. Delete old site transients.
		delete_site_transient( 'shcf7_github_release' );

		// Cache flush to ensure stale site transients are cleared.
		wp_cache_flush();
	}

	/**
	 * Rename an option from an old key to a new key.
	 *
	 * Copies the value and meta, then deletes the old entry.
	 *
	 * @param string $old_key Previous option name.
	 * @param string $new_key New option name.
	 * @return void
	 */
	private static function rename_option( $old_key, $new_key ) {
		$value = get_option( $old_key );

		if ( false === $value ) {
			return;
		}

		// Use direct option manipulation to avoid race conditions.
		delete_option( $old_key );
		add_option( $new_key, $value, '', false );
	}
}
