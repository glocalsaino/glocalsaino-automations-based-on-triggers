<?php
/**
 * Daily counters of the embeddable widgets, per automation and website.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; there is no core API for it.

class GSAAT_Widget_Stats {

	const KEEP_DAYS = 400;

	/**
	 * Add to today's counters.
	 *
	 * @param int    $automation_id Automation ID.
	 * @param string $domain        Website showing the widget ('' = unknown).
	 * @param string $counter       views|submissions|rejected.
	 */
	public static function bump( $automation_id, $domain, $counter ) {
		global $wpdb;
		$views       = 'views' === $counter ? 1 : 0;
		$submissions = 'submissions' === $counter ? 1 : 0;
		$rejected    = 'rejected' === $counter ? 1 : 0;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (automation_id, domain, day, views, submissions, rejected) VALUES (%d, %s, %s, %d, %d, %d) ON DUPLICATE KEY UPDATE views = views + %d, submissions = submissions + %d, rejected = rejected + %d',
				GSAAT_DB::table( 'widget_stats' ),
				$automation_id,
				substr( (string) $domain, 0, 191 ),
				current_time( 'Y-m-d' ),
				$views,
				$submissions,
				$rejected,
				$views,
				$submissions,
				$rejected
			)
		);
	}

	/**
	 * Totals per website over the last days.
	 *
	 * @param int $automation_id Automation ID.
	 * @param int $days          Number of days.
	 * @return array[] { domain, views, submissions, rejected }
	 */
	public static function summary( $automation_id, $days = 30 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT domain, SUM(views) AS views, SUM(submissions) AS submissions, SUM(rejected) AS rejected FROM %i WHERE automation_id = %d AND day >= %s GROUP BY domain ORDER BY submissions DESC, views DESC LIMIT 50',
				GSAAT_DB::table( 'widget_stats' ),
				$automation_id,
				wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS )
			),
			ARRAY_A
		);
		return (array) $rows;
	}

	public static function cleanup() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i WHERE day < %s', GSAAT_DB::table( 'widget_stats' ), wp_date( 'Y-m-d', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) )
		);
	}
}
