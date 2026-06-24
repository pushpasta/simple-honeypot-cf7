<?php
/**
 * Event storage via a dedicated database table.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7\Reporting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all event CRUD operations against a dedicated custom table.
 */
final class Event_Logger {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	const TABLE = 'simple_honeypot_cf7_events';

	/**
	 * Schema version.
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * Create or upgrade the events table.
	 *
	 * Uses dbDelta() so it is safe to call on every activation.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			form_title VARCHAR(255) NOT NULL DEFAULT '',
			ip VARCHAR(45) NOT NULL DEFAULT '',
			user_agent VARCHAR(256) NOT NULL DEFAULT '',
			reasons TEXT NOT NULL,
			time DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_time (time),
			KEY idx_form_id (form_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( $wpdb->prefix . self::TABLE . '_db_version', self::VERSION, false );
	}

	/**
	 * Check whether the events table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		return null !== $exists && $table === $exists;
	}

	/**
	 * Insert a new event.
	 *
	 * @param int    $form_id    Contact Form 7 form ID.
	 * @param string $form_title Form title.
	 * @param string $ip         Remote IP address.
	 * @param string $user_agent User agent string.
	 * @param array  $reasons    Sanitized reason arrays.
	 * @return int|false The event ID on success, false on failure.
	 */
	public static function insert( $form_id, $form_title, $ip, $user_agent, array $reasons ) {
		global $wpdb;

		$encoded = wp_json_encode( $reasons );

		if ( false === $encoded ) {
			return false;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE,
			array(
				'form_id'    => absint( $form_id ),
				'form_title' => sanitize_text_field( $form_title ),
				'ip'         => sanitize_text_field( $ip ),
				'user_agent' => sanitize_text_field( $user_agent ),
				'reasons'    => $encoded,
				'time'       => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get the most recent events.
	 *
	 * @param int $limit Maximum number of events to return. Clamped to 1–1000.
	 * @return array[] Array of event arrays (newest first).
	 */
	public static function get_recent( $limit = 100 ) {
		global $wpdb;

		$limit = max( 1, min( 1000, absint( $limit ) ) );
		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT form_id, form_title, ip, user_agent, reasons, time FROM {$table} ORDER BY time DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$events = array();

		foreach ( $rows as $row ) {
			$decoded = json_decode( $row['reasons'], true );

			$events[] = array(
				'time'       => strtotime( $row['time'] ),
				'form_id'    => (int) $row['form_id'],
				'form_title' => $row['form_title'],
				'ip'         => $row['ip'],
				'user_agent' => $row['user_agent'],
				'reasons'    => is_array( $decoded ) ? $decoded : array(),
			);
		}

		return $events;
	}

	/**
	 * Count all events.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		return absint( $count );
	}

	/**
	 * Delete events older than a given number of days.
	 *
	 * @param int $days Retention period in days.
	 * @return int Number of events deleted.
	 */
	public static function purge_old( $days ) {
		global $wpdb;

		$days   = absint( $days );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$table  = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE time < %s", $cutoff ) );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Keep only the newest N events, delete the rest.
	 *
	 * @param int $keep Number of events to keep.
	 * @return int Number of events deleted.
	 */
	public static function purge_excess( $keep ) {
		global $wpdb;

		$keep  = max( 10, absint( $keep ) );
		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id NOT IN ( SELECT id FROM ( SELECT id FROM {$table} ORDER BY time DESC LIMIT %d ) AS keep_ids )", $keep ) );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Delete all events.
	 *
	 * @return void
	 */
	public static function delete_all() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Drop the events table.
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		delete_option( $table . '_db_version', false );
	}

	/**
	 * Migrate events from the old wp_options storage to the custom table.
	 *
	 * Reads the legacy `simple_honeypot_cf7_stats` option, moves any
	 * events into the custom table, then removes the `events` key from
	 * the stats option. Safe to run multiple times — does nothing if
	 * there are no events to migrate.
	 *
	 * @param string $stats_option The legacy stats option name.
	 * @return int Number of events migrated.
	 */
	public static function migrate_from_options( $stats_option ) {
		$stats = get_option( $stats_option, array() );

		if ( ! is_array( $stats ) || empty( $stats['events'] ) || ! is_array( $stats['events'] ) ) {
			return 0;
		}

		$migrated = 0;

		// Events are stored newest-first; reverse to insert oldest first
		// so IDs are chronological.
		$events = array_reverse( $stats['events'] );

		foreach ( $events as $event ) {
			$form_id    = isset( $event['form_id'] ) ? (int) $event['form_id'] : 0;
			$form_title = isset( $event['form_title'] ) ? wp_strip_all_tags( $event['form_title'] ) : '';
			$ip         = isset( $event['ip'] ) ? sanitize_text_field( $event['ip'] ) : '';
			$user_agent = isset( $event['user_agent'] ) ? sanitize_text_field( $event['user_agent'] ) : '';
			$reasons    = isset( $event['reasons'] ) && is_array( $event['reasons'] ) ? $event['reasons'] : array();
			$time       = isset( $event['time'] ) ? gmdate( 'Y-m-d H:i:s', (int) $event['time'] ) : gmdate( 'Y-m-d H:i:s' );

			global $wpdb;

			$result = $wpdb->insert(
				$wpdb->prefix . self::TABLE,
				array(
					'form_id'    => $form_id,
					'form_title' => $form_title,
					'ip'         => $ip,
					'user_agent' => $user_agent,
					'reasons'    => wp_json_encode( $reasons ),
					'time'       => $time,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false !== $result ) {
				++$migrated;
			}
		}

		// Remove events from the legacy stats option.
		unset( $stats['events'] );
		update_option( $stats_option, $stats, false );

		return $migrated;
	}
}
