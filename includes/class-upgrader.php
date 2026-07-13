<?php
/**
 * Database migration runner.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

use SimpleHoneypotCF7\Reporting\Event_Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs versioned database migrations on plugin activation.
 *
 * Each migration is a static method named migrate_to_N where N is the
 * target version. The stored version is tracked via the shp4cf7_migration_version
 * option so migrations only run once.
 */
final class Upgrader {

	/**
	 * Option name that stores the current migration version.
	 *
	 * @var string
	 */
	const MIGRATION_VERSION_OPTION = SIMPLE_HONEYPOT_CF7_BASE . '_migration_version';

	/**
	 * Transient name that caches the last successfully applied migration version.
	 *
	 * Used to skip migration checks on admin page loads when nothing
	 * has changed.
	 *
	 * @var string
	 */
	const MIGRATION_CACHE_OPTION = SIMPLE_HONEYPOT_CF7_BASE . '_migration_cache';

	/**
	 * The database version this codebase expects.
	 *
	 * @var int
	 */
	const CURRENT_DB_VERSION = 3;

	/**
	 * Legacy option names that were renamed in migration 2.
	 *
	 * @var string[]
	 */
	const LEGACY_OPTIONS = array(
		'simple_honeypot_cf7_settings',
		'simple_honeypot_cf7_stats',
		'shp4cf7_stats',
		'shp4cf7_db_version',
	);

	/**
	 * Run all pending migrations.
	 *
	 * @return void
	 */
	public static function run() {
		$stored = (int) get_option( self::MIGRATION_VERSION_OPTION, 1 );

		if ( $stored >= self::CURRENT_DB_VERSION ) {
			return;
		}

		if ( $stored < 2 ) {
			self::migrate_to_2();
		}

		if ( $stored < 3 ) {
			self::migrate_to_3();
		}

		update_option( self::MIGRATION_VERSION_OPTION, self::CURRENT_DB_VERSION, false );
	}

	/**
	 * Conditionally run migrations based on transient cache.
	 *
	 * Skips all work when the transient matches the current version,
	 * avoiding redundant dbDelta() calls and option lookups on every
	 * admin page load.
	 *
	 * @return void
	 */
	public static function maybe_run() {
		$cached = get_transient( self::MIGRATION_CACHE_OPTION );

		if ( false !== $cached && self::CURRENT_DB_VERSION === (int) $cached ) {
			return;
		}

		self::run();
		Settings::activate();
		Event_Logger::create_table();
		Event_Logger::create_stats_table();
		Event_Logger::migrate_from_options( Settings::META_OPTION );

		// Record the update date for the admin header tooltip.
		$meta = get_option( Settings::META_OPTION, array() );

		if ( is_array( $meta ) ) {
			$meta['last_updated'] = gmdate( 'Y-m-d' );
			update_option( Settings::META_OPTION, $meta, false );
		}

		set_transient( self::MIGRATION_CACHE_OPTION, self::CURRENT_DB_VERSION, 7 * DAY_IN_SECONDS );
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

		// 3. Migrate data from the old events table before dropping it.
		// The new table uses a different prefix (shp4cf7_events instead of
		// simple_honeypot_cf7_events), but the schema is identical.
		$new_table = $wpdb->prefix . Event_Logger::TABLE;
		$old_table = $wpdb->prefix . 'simple_honeypot_cf7_events';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		);

		if ( null !== $table_exists && $old_table === $table_exists ) {
			$old_row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$old_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $old_row_count > 0 ) {
				// Ensure the new table exists (idempotent — dbDelta handles already-created).
				Event_Logger::create_table();

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are internal and cannot be prepared.
				$wpdb->query(
					"INSERT INTO {$new_table} (form_id, form_title, ip, user_agent, reasons, `time`)
					 SELECT form_id, form_title, ip, user_agent, reasons, `time`
					 FROM {$old_table}"
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

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
	 * Migration v3: rename storage keys, reschedule cron.
	 *
	 * - Options: shp4cf7_stats → shp4cf7_meta, shp4cf7_db_version → shp4cf7_migration_version,
	 *            shp4cf7_consumed → shp4cf7_consumed_tokens
	 * - Transients: shp4cf7_upgrader_version → shp4cf7_migration_cache
	 * - Cron: reschedule shp4cf7_purge_excess (hourly) → shp4cf7_purge_events (daily)
	 *
	 * @return void
	 */
	private static function migrate_to_3() {
		// 1. Rename options.
		self::rename_option( 'shp4cf7_stats', 'shp4cf7_meta' );
		self::rename_option( 'shp4cf7_db_version', 'shp4cf7_migration_version' );
		self::rename_option( 'shp4cf7_consumed', 'shp4cf7_consumed_tokens' );

		// 2. Delete old transients.
		delete_transient( 'shp4cf7_upgrader_version' );
		delete_transient( 'shp4cf7_purge_old' );
		delete_transient( 'shp4cf7_purge_throttle' );

		// 3. Reschedule cron from hourly to daily with new hook name.
		$old_timestamp = wp_next_scheduled( 'shp4cf7_purge_excess' );

		if ( $old_timestamp ) {
			wp_unschedule_event( $old_timestamp, 'shp4cf7_purge_excess' );
		}

		if ( ! wp_next_scheduled( \SimpleHoneypotCF7\Reporting\Cron_Handler::HOOK ) ) {
			wp_schedule_event( time(), 'daily', \SimpleHoneypotCF7\Reporting\Cron_Handler::HOOK );
		}
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
