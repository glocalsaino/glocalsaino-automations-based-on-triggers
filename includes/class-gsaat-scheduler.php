<?php
/**
 * Queue of actions scheduled by "Wait" steps. Each job holds the actions that come
 * after a wait, the tokens at that point and when to run. WP-Cron runs it.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; there is no core API for it.

class GSAAT_Scheduler {

	const JOB_HOOK   = 'glocalsaino_aat_run_job';
	const SWEEP_HOOK = 'glocalsaino_aat_sweep_jobs';

	public static function init() {
		add_action( self::JOB_HOOK, array( __CLASS__, 'run_job' ) );
		add_action( self::SWEEP_HOOK, array( __CLASS__, 'sweep' ) );
	}

	/**
	 * Units of the "Wait" action: key => [ label, seconds ].
	 *
	 * @return array
	 */
	public static function units() {
		return array(
			'minutes' => array( __( 'minutes', 'glocalsaino-actions-automation-based-on-trigger' ), MINUTE_IN_SECONDS ),
			'hours'   => array( __( 'hours', 'glocalsaino-actions-automation-based-on-trigger' ), HOUR_IN_SECONDS ),
			'days'    => array( __( 'days', 'glocalsaino-actions-automation-based-on-trigger' ), DAY_IN_SECONDS ),
			'weeks'   => array( __( 'weeks', 'glocalsaino-actions-automation-based-on-trigger' ), WEEK_IN_SECONDS ),
		);
	}

	/**
	 * Seconds of a "Wait" action.
	 *
	 * @param array $action Wait action.
	 * @return int
	 */
	public static function delay_seconds( array $action ) {
		$units = self::units();
		$unit  = isset( $action['unit'], $units[ $action['unit'] ] ) ? $action['unit'] : 'minutes';
		return max( 1, (int) $action['amount'] ) * $units[ $unit ][1];
	}

	/**
	 * Queue the actions that follow a wait.
	 *
	 * @param array $automation Automation.
	 * @param array $items      [ [ index, action ], … ] still to run.
	 * @param int   $run_at     Unix timestamp.
	 * @param array $tokens     Tokens at the time of the wait.
	 * @param array $context    Event context.
	 * @param int   $log_id     Log entry that scheduled them.
	 * @return int Job ID.
	 */
	public static function create( array $automation, array $items, $run_at, array $tokens, array $context, $log_id ) {
		global $wpdb;

		unset( $context['browser'] ); // Nobody is waiting in a browser when the job runs.

		$wpdb->insert(
			GSAAT_DB::table( 'jobs' ),
			array(
				'automation_id' => (int) $automation['id'],
				'log_id'        => (int) $log_id,
				'items'         => wp_json_encode( $items ),
				'tokens'        => wp_json_encode( $tokens ),
				'context'       => wp_json_encode( $context ),
				'run_at'        => gmdate( 'Y-m-d H:i:s', $run_at ),
				'status'        => 'pending',
				'result_log_id' => 0,
				'created_at'    => current_time( 'mysql', true ),
			)
		);
		$job_id = (int) $wpdb->insert_id;

		if ( $job_id ) {
			wp_schedule_single_event( $run_at, self::JOB_HOOK, array( $job_id ) );
		}
		return $job_id;
	}

	/**
	 * Run a job (WP-Cron, sweep or "Run now"). Safe against double execution.
	 *
	 * @param int $job_id Job ID.
	 * @return int|null Result log ID.
	 */
	public static function run_job( $job_id ) {
		global $wpdb;

		// Claim the job: only one process can move it out of "pending".
		$claimed = $wpdb->query(
			$wpdb->prepare( 'UPDATE %i SET status = %s WHERE id = %d AND status = %s', GSAAT_DB::table( 'jobs' ), 'running', $job_id, 'pending' )
		);
		if ( 1 !== (int) $claimed ) {
			return null;
		}

		$job        = self::get( $job_id );
		$automation = $job ? GSAAT_Automations::get( $job['automation_id'] ) : null;

		if ( ! $automation ) {
			self::finish( $job_id, 'cancelled', 0 );
			return null;
		}

		$event = sprintf(
			/* translators: %d: log entry ID. */
			__( 'Actions scheduled by #%d (after a wait)', 'glocalsaino-actions-automation-based-on-trigger' ),
			$job['log_id']
		);

		if ( 'active' !== $automation['status'] ) {
			$log_id = GSAAT_Logs::add(
				array(
					'automation_id'   => $automation['id'],
					'automation_name' => $automation['name'],
					'trigger_type'    => $automation['trigger_type'],
					'event'           => $event,
					'trigger_data'    => array(),
					'results'         => array(),
					'status'          => 'skipped',
					'message'         => __( 'The scheduled actions were not run because the automation is inactive.', 'glocalsaino-actions-automation-based-on-trigger' ),
					'parent_id'       => $job['log_id'],
				)
			);
			self::finish( $job_id, 'cancelled', $log_id );
			return $log_id;
		}

		$log_id = GSAAT_Engine::run_scheduled( $automation, $job['items'], $job['tokens'], $job['context'], $event, $job['log_id'] );
		self::finish( $job_id, 'done', $log_id );
		return $log_id;
	}

	/**
	 * Hourly safety net: run overdue jobs whose single cron event was lost.
	 */
	public static function sweep() {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE status = %s AND run_at <= %s ORDER BY run_at ASC LIMIT 20',
				GSAAT_DB::table( 'jobs' ),
				'pending',
				gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS )
			)
		);
		foreach ( (array) $ids as $id ) {
			self::run_job( (int) $id );
		}
	}

	public static function cancel( $job_id ) {
		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare( 'UPDATE %i SET status = %s WHERE id = %d AND status = %s', GSAAT_DB::table( 'jobs' ), 'cancelled', $job_id, 'pending' )
		);
		wp_clear_scheduled_hook( self::JOB_HOOK, array( (int) $job_id ) );
		return 1 === (int) $updated;
	}

	/**
	 * Cancel the pending jobs of an automation (e.g. when it is deleted).
	 *
	 * @param int $automation_id Automation ID.
	 */
	public static function cancel_for_automation( $automation_id ) {
		foreach ( self::pending( $automation_id ) as $job ) {
			self::cancel( $job['id'] );
		}
	}

	public static function get( $job_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', GSAAT_DB::table( 'jobs' ), $job_id ),
			ARRAY_A
		);
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Pending jobs, soonest first.
	 *
	 * @param int $automation_id Optional automation filter (0 = all).
	 * @return array[]
	 */
	public static function pending( $automation_id = 0 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND ( %d = 0 OR automation_id = %d ) ORDER BY run_at ASC LIMIT 500',
				GSAAT_DB::table( 'jobs' ),
				'pending',
				$automation_id,
				$automation_id
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	/**
	 * Jobs scheduled by a log entry.
	 *
	 * @param int $log_id Log entry ID.
	 * @return array[]
	 */
	public static function for_log( $log_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE log_id = %d ORDER BY id ASC', GSAAT_DB::table( 'jobs' ), $log_id ),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	public static function count_pending() {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', GSAAT_DB::table( 'jobs' ), 'pending' )
		);
	}

	/**
	 * Delete finished jobs older than the automation log retention.
	 *
	 * @param int $days Retention in days (0 = keep).
	 */
	public static function cleanup( $days ) {
		global $wpdb;
		if ( $days <= 0 ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE status IN ( %s, %s ) AND created_at < %s',
				GSAAT_DB::table( 'jobs' ),
				'done',
				'cancelled',
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
	}

	private static function finish( $job_id, $status, $log_id ) {
		global $wpdb;
		$wpdb->update(
			GSAAT_DB::table( 'jobs' ),
			array(
				'status'        => $status,
				'result_log_id' => (int) $log_id,
			),
			array( 'id' => (int) $job_id )
		);
	}

	private static function decode( array $row ) {
		foreach ( array( 'items', 'tokens', 'context' ) as $column ) {
			$decoded        = json_decode( (string) $row[ $column ], true );
			$row[ $column ] = is_array( $decoded ) ? $decoded : array();
		}
		foreach ( array( 'id', 'automation_id', 'log_id', 'result_log_id' ) as $column ) {
			$row[ $column ] = (int) $row[ $column ];
		}
		return $row;
	}
}
