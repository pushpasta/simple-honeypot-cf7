<?php
/**
 * Stored plugin report data.
 *
 * @package Simple_Honeypot_CF7
 */

namespace SimpleHoneypotCF7;

use SimpleHoneypotCF7\Reporting\Event_Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, resets, and migrates aggregate report counters.
 */
final class Settings_Reports {

	/**
	 * Default report counters.
	 *
	 * @return array
	 */
	public static function default_meta() {
		return array(
			'total'           => 0,
			'run_since'       => time(),
			'last_calculated' => '',
			'reasons'         => array(),
			'forms'           => array(),
			'by_ip'           => array(),
			'by_period'       => array(
				'today'       => 0,
				'yesterday'   => 0,
				'last_7_days' => 0,
				'this_month'  => 0,
				'last_month'  => 0,
			),
		);
	}

	/**
	 * Get report data merged with defaults.
	 *
	 * Reads the pre-aggregated summary calculated by cron or on-demand.
	 * Falls back to the legacy option for pre-upgrade data. Run-since
	 * is preserved in the meta option because it is not a counter.
	 *
	 * @return array
	 */
	public static function get_meta() {
		$existing = get_option( Settings::META_OPTION, array() );
		$stats    = self::default_meta();

		if ( is_array( $existing ) && ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		$summary = Event_Logger::get_aggregated_stats();

		if ( ! empty( $summary['last_calculated'] ) ) {
			return self::stats_from_summary( $stats, $summary );
		}

		// No aggregated summary yet — check for legacy data.
		return self::meta_from_legacy( $stats, $existing );
	}

	/**
	 * Get report data current for the local day.
	 *
	 * Same as get_meta(), but re-aggregates once when the cached summary
	 * was calculated on a previous local day so the by-period buckets
	 * reflect the current Today / Yesterday / This month windows. Shared
	 * by the Reports tab and the dashboard widget so both screens always
	 * show the same figures.
	 *
	 * @return array
	 */
	public static function get_reports_stats() {
		$stats = self::get_meta();

		if ( ! empty( $stats['last_calculated'] ) && Event_Logger::summary_predates_local_day( $stats['last_calculated'] ) ) {
			Event_Logger::aggregate_summary();
			$stats = self::get_meta();
		}

		return $stats;
	}

	/**
	 * Merge an aggregated summary into the report structure.
	 *
	 * Both the cached read and the on-demand cold-cache warm-up map the
	 * summary the same way, so the first view after an import or reset
	 * matches steady state (all breakdowns included).
	 *
	 * @param array $stats   Report structure, including run_since.
	 * @param array $summary Aggregated summary from Event_Logger.
	 * @return array
	 */
	private static function stats_from_summary( array $stats, array $summary ) {
		$stats['total']           = $summary['total'];
		$stats['reasons']         = $summary['reasons'];
		$stats['forms']           = $summary['forms'];
		$stats['by_ip']           = isset( $summary['by_ip'] ) ? $summary['by_ip'] : array();
		$stats['by_period']       = isset( $summary['by_period'] ) ? $summary['by_period'] : array();
		$stats['last_calculated'] = $summary['last_calculated'];

		return $stats;
	}

	/**
	 * Reset all reporting data.
	 *
	 * @return void
	 */
	public static function reset_meta() {
		Event_Logger::reset_counters();
		Event_Logger::delete_all();

		delete_option( SIMPLE_HONEYPOT_CF7_BASE . '_form_titles' );

		// Preserve the original activation date.
		$existing = get_option( Settings::META_OPTION, array() );
		$stats    = self::default_meta();

		if ( is_array( $existing ) && ! empty( $existing['run_since'] ) ) {
			$stats['run_since'] = (int) $existing['run_since'];
		}

		update_option( Settings::META_OPTION, $stats, false );
	}

	/**
	 * Build stats from the legacy option.
	 *
	 * On the first read after upgrade the aggregated summary may not
	 * exist yet. This method migrates any remaining legacy data into
	 * per-form options so it is not lost. When per-form options exist
	 * the summary is aggregated once and the full result is returned so
	 * the first view matches steady state; later reads come from cache.
	 *
	 * @param array $stats    Base report structure, including run_since.
	 * @param array $existing The legacy stats option value.
	 * @return array
	 */
	private static function meta_from_legacy( array $stats, array $existing ) {
		// Check if per-form options exist from a partial migration.
		$form_stats = Event_Logger::get_all_form_stats();

		if ( ! empty( $form_stats ) ) {
			// Per-form options exist but the summary hasn't been calculated.
			// Aggregate once on cold cache and return the full summary.
			return self::stats_from_summary( $stats, Event_Logger::aggregate_summary() );
		}

		// Return whatever legacy data is available.
		if ( ! empty( $existing['total'] ) ) {
			$stats['total']   = absint( $existing['total'] );
			$stats['reasons'] = isset( $existing['reasons'] ) && is_array( $existing['reasons'] ) ? $existing['reasons'] : array();
			$stats['forms']   = isset( $existing['forms'] ) && is_array( $existing['forms'] ) ? $existing['forms'] : array();
		}

		return $stats;
	}
}
