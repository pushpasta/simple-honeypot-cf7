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
	const TABLE = SIMPLE_HONEYPOT_CF7_BASE . '_events';

	/**
	 * Option prefix for per-form stats.
	 *
	 * Each form's stats are stored as shp4cf7_stat_form_{id} containing
	 * an array with 'total' and 'reasons' keys.
	 *
	 * @var string
	 */
	const STATS_PREFIX = SIMPLE_HONEYPOT_CF7_BASE . '_stat_';

	/**
	 * Option name for the aggregated stats summary.
	 *
	 * @var string
	 */
	const SUMMARY_OPTION = SIMPLE_HONEYPOT_CF7_BASE . '_stat_summary';

	/**
	 * Schema version.
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * Option name for the events table DB version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = SIMPLE_HONEYPOT_CF7_BASE . '_events_db_version';

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

		update_option( self::VERSION_OPTION, self::VERSION, false );
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
				'form_title' => sanitize_text_field( self::truncate( $form_title, 250 ) ),
				'ip'         => sanitize_text_field( $ip ),
				'user_agent' => sanitize_text_field( self::truncate( $user_agent, 250 ) ),
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
	 * Truncate a string to a byte-safe length for utf8mb4 columns.
	 *
	 * A VARCHAR(250) column holds 250 characters at up to 4 bytes each.
	 * Truncating by characters keeps the stored value within bounds even
	 * under strict-mode MySQL, where an over-long INSERT would otherwise
	 * fail silently and drop the event.
	 *
	 * @param string $value  Raw value.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private static function truncate( $value, $length ) {
		$value = (string) $value;

		if ( mb_strlen( $value, 'UTF-8' ) <= $length ) {
			return $value;
		}

		return mb_substr( $value, 0, $length, 'UTF-8' );
	}

	/**
	 * Get the most recent events.
	 *
	 * @param int $limit  Maximum number of events to return. Clamped to 1–1000.
	 * @param int $offset Number of events to skip for pagination. Default 0.
	 * @return array[] Array of event arrays (newest first).
	 */
	public static function get_recent( $limit = 100, $offset = 0 ) {
		global $wpdb;

		$limit  = max( 1, min( 1000, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );
		$table  = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT form_id, form_title, ip, user_agent, reasons, time FROM {$table} ORDER BY time DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
	 * Count events for common time periods.
	 *
	 * Returns counts for today, yesterday, last 7 days, this month,
	 * and last month — in a single query. The all-time total is not
	 * part of the result; consumers use count() or the aggregated
	 * summary instead.
	 *
	 * @return array{today: int, yesterday: int, last_7_days: int, this_month: int, last_month: int}
	 */
	public static function count_by_period() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( "SELECT SUM( time >= UTC_DATE() ) AS today, SUM( time >= UTC_DATE() - INTERVAL 1 DAY AND time < UTC_DATE() ) AS yesterday, SUM( time >= UTC_DATE() - INTERVAL 7 DAY ) AS last_7_days, SUM( time >= DATE_FORMAT( UTC_DATE(), '%Y-%m-01' ) ) AS this_month, SUM( time >= DATE_FORMAT( UTC_DATE() - INTERVAL 1 MONTH, '%Y-%m-01' ) AND time < DATE_FORMAT( UTC_DATE(), '%Y-%m-01' ) ) AS last_month, COUNT(*) AS total FROM {$table}", ARRAY_A );

		if ( ! is_array( $row ) ) {
			return array(
				'today'       => 0,
				'yesterday'   => 0,
				'last_7_days' => 0,
				'this_month'  => 0,
				'last_month'  => 0,
			);
		}

		return array(
			'today'       => absint( $row['today'] ),
			'yesterday'   => absint( $row['yesterday'] ),
			'last_7_days' => absint( $row['last_7_days'] ),
			'this_month'  => absint( $row['this_month'] ),
			'last_month'  => absint( $row['last_month'] ),
		);
	}

	/**
	 * Delete events older than a given number of days.
	 *
	 * Recalculates the aggregated summary when rows are removed so
	 * report totals stay current without waiting for the hourly cron.
	 *
	 * @param int $days Retention period in days.
	 * @return int Number of events deleted.
	 */
	public static function purge_aged_events( $days ) {
		global $wpdb;

		$days   = absint( $days );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$table  = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE time < %s", $cutoff ) );

		if ( $deleted > 0 ) {
			self::aggregate_summary();
		}

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Keep only the newest N events, delete the rest.
	 *
	 * Uses the auto-increment primary key for a fast index-only scan
	 * instead of a subquery on `time`. Recalculates the aggregated
	 * summary when rows are removed.
	 *
	 * @param int $keep Number of events to keep.
	 * @return int Number of events deleted.
	 */
	public static function purge_excess_events( $keep ) {
		global $wpdb;

		$keep  = max( 10, absint( $keep ) );
		$table = $wpdb->prefix . self::TABLE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cutoff = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(MIN(id), 0) FROM (SELECT id FROM {$table} ORDER BY id DESC LIMIT %d) AS newest",
				$keep
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$cutoff = (int) $cutoff;

		if ( $cutoff <= 0 ) {
			return 0;
		}

		// Delete everything older than the cutoff. The cutoff row and
		// all newer rows are kept.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id < %d", $cutoff ) );

		if ( $deleted > 0 ) {
			self::aggregate_summary();
		}

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Delete all events.
	 *
	 * Refreshes the aggregated summary afterwards so report totals
	 * immediately reflect the emptied log.
	 *
	 * @return void
	 */
	public static function delete_all() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		self::aggregate_summary();
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

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Build the option name for a form's stats.
	 *
	 * @param int $form_id Contact Form 7 form ID.
	 * @return string Option name.
	 */
	public static function form_option_name( $form_id ) {
		return self::STATS_PREFIX . 'form_' . absint( $form_id );
	}

	/**
	 * Record a blocked spam attempt for a specific form.
	 *
	 * Uses a read-modify-write cycle on the form's individual option.
	 * Keeping one option per form limits object cache contention, but
	 * concurrent requests can still race and lose an occasional
	 * increment; this is accepted as a negligible drift for spam
	 * statistics.
	 *
	 * @param int   $form_id Contact Form 7 form ID.
	 * @param array $reasons Spam reasons (each with 'type' key).
	 * @return void
	 */
	public static function record_form_stat( $form_id, array $reasons ) {
		$option = self::form_option_name( $form_id );
		$stat   = get_option(
			$option,
			array(
				'total'   => 0,
				'reasons' => array(),
			)
		);

		if ( ! is_array( $stat ) ) {
			$stat = array(
				'total'   => 0,
				'reasons' => array(),
			);
		}

		$stat['total'] = isset( $stat['total'] ) ? (int) $stat['total'] + 1 : 1;

		if ( ! isset( $stat['reasons'] ) || ! is_array( $stat['reasons'] ) ) {
			$stat['reasons'] = array();
		}

		foreach ( $reasons as $reason ) {
			$type = isset( $reason['type'] ) ? sanitize_key( $reason['type'] ) : '';

			if ( '' === $type ) {
				continue;
			}

			$stat['reasons'][ $type ] = isset( $stat['reasons'][ $type ] ) ? (int) $stat['reasons'][ $type ] + 1 : 1;
		}

		update_option( $option, $stat, false );
	}

	/**
	 * Get all per-form stat options.
	 *
	 * Scans the database for options matching the form stat prefix and
	 * fetches names and values in one query instead of one get_option()
	 * call per form.
	 *
	 * @return array<int, array{total: int, reasons: array<string, int>}> Keyed by form ID.
	 */
	public static function get_all_form_stats() {
		global $wpdb;

		$prefix = $wpdb->esc_like( self::STATS_PREFIX . 'form_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$prefix
			),
			ARRAY_A
		);

		$stats = array();

		if ( ! is_array( $rows ) ) {
			return $stats;
		}

		$prefix_len = strlen( self::STATS_PREFIX . 'form_' );

		foreach ( $rows as $row ) {
			$form_id = (int) substr( $row['option_name'], $prefix_len );
			$value   = maybe_unserialize( $row['option_value'] );

			if ( is_array( $value ) ) {
				$stats[ $form_id ] = $value;
			}
		}

		return $stats;
	}

	/**
	 * Aggregate all per-form stats into a single summary.
	 *
	 * Sums totals and reasons across all forms, then stores the result
	 * in the summary option with a timestamp. Called hourly by cron and
	 * on-demand via the REST API.
	 *
	 * @return array{total: int, reasons: array<string, int>, forms: array<int, array{title: string, total: int}>, by_period: array{today: int, yesterday: int, last_7_days: int, this_month: int, last_month: int}, last_calculated: string}
	 */
	public static function aggregate_summary() {
		$form_stats  = self::get_all_form_stats();
		$form_titles = get_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles', array() );
		$reasons     = array();
		$forms       = array();

		foreach ( $form_stats as $form_id => $stat ) {
			$form_total = isset( $stat['total'] ) ? absint( $stat['total'] ) : 0;

			$title = isset( $form_titles[ $form_id ] )
				? $form_titles[ $form_id ]
				: __( 'Unknown form', 'simple-honeypot-cf7' );

			$forms[ $form_id ] = array(
				'title' => $title,
				'total' => $form_total,
			);

			if ( isset( $stat['reasons'] ) && is_array( $stat['reasons'] ) ) {
				foreach ( $stat['reasons'] as $type => $count ) {
					if ( ! isset( $reasons[ $type ] ) ) {
						$reasons[ $type ] = 0;
					}
					$reasons[ $type ] += absint( $count );
				}
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$summary = array(
			'total'           => $total,
			'reasons'         => $reasons,
			'forms'           => $forms,
			'by_period'       => self::count_by_period(),
			'last_calculated' => current_time( 'mysql', true ),
		);

		update_option( self::SUMMARY_OPTION, $summary, false );

		return $summary;
	}

	/**
	 * Get the aggregated stats summary.
	 *
	 * Returns the cached summary from the last aggregation run.
	 * Falls back to an empty structure if aggregation has not run yet.
	 *
	 * @return array{total: int, reasons: array<string, int>, forms: array<int, array{title: string, total: int}>, by_period: array{today: int, yesterday: int, last_7_days: int, this_month: int, last_month: int}, last_calculated: string}
	 */
	public static function get_aggregated_stats() {
		$summary = get_option( self::SUMMARY_OPTION, array() );

		if ( ! is_array( $summary ) || empty( $summary['last_calculated'] ) ) {
			return array(
				'total'           => 0,
				'reasons'         => array(),
				'forms'           => array(),
				'by_period'       => array(
					'today'       => 0,
					'yesterday'   => 0,
					'last_7_days' => 0,
					'this_month'  => 0,
					'last_month'  => 0,
				),
				'last_calculated' => '',
			);
		}

		if ( ! isset( $summary['by_period'] ) ) {
			$summary['by_period'] = self::count_by_period();
		}

		return $summary;
	}

	/**
	 * Delete all per-form stats and the summary.
	 *
	 * @return void
	 */
	public static function reset_counters() {
		global $wpdb;

		$prefix = $wpdb->esc_like( self::STATS_PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$prefix
			)
		);

		if ( is_array( $options ) ) {
			foreach ( $options as $option_name ) {
				delete_option( $option_name );
			}
		}

		delete_option( self::SUMMARY_OPTION );
	}

	/**
	 * Migrate stats counters from the legacy database table and individual
	 * counter options to per-form options.
	 *
	 * Reads the shp4cf7_stat_counters table (if present) and the legacy
	 * shp4cf7_meta option into per-form options. A counter is only
	 * migrated when its form still exists and is a Contact Form 7 form;
	 * stats for deleted or non-CF7 forms, and unattributable aggregate
	 * reason counters, are discarded rather than guessed.
	 *
	 * After migration, per-form options are created and the old storage
	 * is cleaned up. Safe to run multiple times.
	 *
	 * @return int Number of forms migrated.
	 */
	public static function migrate_counters_to_form_options() {
		global $wpdb;

		$table  = $wpdb->prefix . SIMPLE_HONEYPOT_CF7_BASE . '_stat_counters';
		$forms  = array();
		$titles = get_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles', array() );

		if ( ! is_array( $titles ) ) {
			$titles = array();
		}

		// 1. Read per-form counters from the legacy stats table if it
		// still exists. Aggregate reason counters have no per-form
		// attribution and are discarded.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( null !== $exists && $table === $exists ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				"SELECT counter_name, counter_value FROM {$table}",
				OBJECT_K
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$name  = $row->counter_name;
					$value = absint( $row->counter_value );

					if ( 0 !== strpos( $name, 'form:' ) ) {
						continue;
					}

					$fid = (int) substr( $name, 5 );

					if ( ! self::is_migratable_form( $fid ) ) {
						continue;
					}

					if ( ! isset( $forms[ $fid ] ) ) {
						$forms[ $fid ] = array(
							'total'   => 0,
							'reasons' => array(),
						);
					}

					$forms[ $fid ]['total'] = $value;
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// 2. Individual counter options (shp4cf7_stat_total/reasons/forms)
		// hold site-wide aggregates without form attribution. They carry
		// no migratable data and are removed in the cleanup below.

		// 3. Read from legacy shp4cf7_meta option. Per-form counts and
		// titles follow the same existence rule as above; site-wide
		// aggregate reasons are unattributable and discarded.
		$meta = get_option( SIMPLE_HONEYPOT_CF7_BASE . '_meta', array() );

		if ( is_array( $meta ) && ! empty( $meta['forms'] ) && is_array( $meta['forms'] ) ) {
			foreach ( $meta['forms'] as $form_id => $form_data ) {
				$fid = (int) $form_id;

				if ( ! self::is_migratable_form( $fid ) ) {
					continue;
				}

				if ( ! isset( $forms[ $fid ] ) ) {
					$forms[ $fid ] = array(
						'total'   => 0,
						'reasons' => array(),
					);
				}

				if ( is_array( $form_data ) && ! empty( $form_data['count'] ) ) {
					$forms[ $fid ]['total'] += absint( $form_data['count'] );
				}

				if ( is_array( $form_data ) && ! empty( $form_data['title'] ) ) {
					$titles[ $fid ] = sanitize_text_field( $form_data['title'] );
				}
			}
		}

		// Update form titles first so the summary below picks them up.
		if ( ! empty( $titles ) ) {
			update_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles', $titles, false );
		}

		// Write per-form options.
		foreach ( $forms as $form_id => $stat ) {
			update_option( self::form_option_name( $form_id ), $stat, false );
		}

		// Aggregate and store summary.
		if ( ! empty( $forms ) ) {
			self::aggregate_summary();
		}

		// 4. Clean up old options.
		delete_option( self::STATS_PREFIX . 'total' );
		delete_option( self::STATS_PREFIX . 'reasons' );
		delete_option( self::STATS_PREFIX . 'forms' );
		delete_option( SIMPLE_HONEYPOT_CF7_BASE . '_stat_counters' );
		delete_option( SIMPLE_HONEYPOT_CF7_BASE . '_meta' );

		// Preserve run_since in the meta option for the activation date.
		if ( is_array( $meta ) && ! empty( $meta['run_since'] ) ) {
			update_option(
				SIMPLE_HONEYPOT_CF7_BASE . '_meta',
				array( 'run_since' => (int) $meta['run_since'] ),
				false
			);
		}

		return count( $forms );
	}

	/**
	 * Rebuild per-form reason counts from the events table.
	 *
	 * Reads every row in the events table, decodes the JSON-encoded
	 * reasons, and tallies reason types per form. For each form that
	 * still exists as a Contact Form 7 form the per-form option's
	 * reasons array is replaced with the tallied counts. The total
	 * field is preserved as-is.
	 *
	 * Intended to run once during migration v5 to restore historical
	 * reason data that was lost when migration v4 created per-form
	 * options with empty reason arrays.
	 *
	 * @return int Number of forms whose reasons were rebuilt.
	 */
	public static function rebuild_reasons_from_events() {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE;
		$counts  = array();
		$last_id = 0;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, form_id, reasons FROM {$table} WHERE id > %d ORDER BY id LIMIT 1000",
					$last_id
				),
				ARRAY_A
			);

			if ( ! is_array( $rows ) || empty( $rows ) ) {
				break;
			}

			$batch_size = count( $rows );

			foreach ( $rows as $row ) {
				$last_id = (int) $row['id'];
				$form_id = (int) $row['form_id'];
				$decoded = json_decode( $row['reasons'], true );

				if ( ! is_array( $decoded ) ) {
					continue;
				}

				if ( ! isset( $counts[ $form_id ] ) ) {
					$counts[ $form_id ] = array();
				}

				foreach ( $decoded as $reason ) {
					$type = isset( $reason['type'] ) ? sanitize_key( $reason['type'] ) : '';

					if ( '' === $type ) {
						continue;
					}

					if ( ! isset( $counts[ $form_id ][ $type ] ) ) {
						$counts[ $form_id ][ $type ] = 0;
					}

					++$counts[ $form_id ][ $type ];
				}
			}
		} while ( 1000 === $batch_size );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rebuilt = 0;

		foreach ( $counts as $form_id => $reasons ) {
			if ( ! self::is_migratable_form( $form_id ) ) {
				continue;
			}

			$option = self::form_option_name( $form_id );
			$stat   = get_option(
				$option,
				array(
					'total'   => 0,
					'reasons' => array(),
				)
			);

			if ( ! is_array( $stat ) ) {
				$stat = array(
					'total'   => 0,
					'reasons' => array(),
				);
			}

			$stat['reasons'] = $reasons;

			update_option( $option, $stat, false );
			++$rebuilt;
		}

		return $rebuilt;
	}

	/**
	 * Check whether a form still exists as a Contact Form 7 form.
	 *
	 * Used during migration so stats and titles are only carried over
	 * for forms that can actually be attributed. Deleted posts return
	 * false; trashed posts still resolve their post type and pass.
	 *
	 * @param int $form_id Candidate form ID.
	 * @return bool True if the ID belongs to an existing wpcf7_contact_form.
	 */
	private static function is_migratable_form( $form_id ) {
		return 'wpcf7_contact_form' === get_post_type( absint( $form_id ) );
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

		global $wpdb;

		foreach ( $events as $event ) {
			$form_id    = isset( $event['form_id'] ) ? (int) $event['form_id'] : 0;
			$form_title = isset( $event['form_title'] ) ? wp_strip_all_tags( $event['form_title'] ) : '';
			$ip         = isset( $event['ip'] ) ? sanitize_text_field( $event['ip'] ) : '';
			$user_agent = isset( $event['user_agent'] ) ? sanitize_text_field( $event['user_agent'] ) : '';
			$reasons    = isset( $event['reasons'] ) && is_array( $event['reasons'] ) ? $event['reasons'] : array();
			$time       = isset( $event['time'] ) ? gmdate( 'Y-m-d H:i:s', (int) $event['time'] ) : gmdate( 'Y-m-d H:i:s' );

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
