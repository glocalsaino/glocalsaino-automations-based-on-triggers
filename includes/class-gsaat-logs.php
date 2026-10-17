<?php
/**
 * Automation logs and webhook logs.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; there is no core API for them.

class GSAAT_Logs {

	/**
	 * Record an automation execution.
	 *
	 * @param array $entry automation_id, automation_name, trigger_type, event, trigger_data, results, status, message.
	 */
	public static function add( array $entry ) {
		global $wpdb;
		$wpdb->insert(
			GSAAT_DB::table( 'logs' ),
			array(
				'automation_id'   => (int) $entry['automation_id'],
				'automation_name' => (string) $entry['automation_name'],
				'trigger_type'    => (string) $entry['trigger_type'],
				'event'           => (string) $entry['event'],
				'trigger_data'    => wp_json_encode( $entry['trigger_data'] ),
				'results'         => wp_json_encode( $entry['results'] ),
				'status'          => (string) $entry['status'],
				'message'         => isset( $entry['message'] ) ? (string) $entry['message'] : '',
				'created_at'      => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Record a webhook call.
	 *
	 * @param array $entry automation_id, automation_name, webhook_url, method, received_data, status_code, error_code, error_message.
	 */
	public static function add_webhook( array $entry ) {
		global $wpdb;
		$wpdb->insert(
			GSAAT_DB::table( 'webhook_logs' ),
			array(
				'automation_id'   => (int) $entry['automation_id'],
				'automation_name' => (string) $entry['automation_name'],
				'webhook_url'     => (string) $entry['webhook_url'],
				'method'          => (string) $entry['method'],
				'remote_ip'       => isset( $entry['remote_ip'] ) ? (string) $entry['remote_ip'] : '',
				'received_data'   => wp_json_encode( $entry['received_data'] ),
				'status_code'     => (int) $entry['status_code'],
				'error_code'      => isset( $entry['error_code'] ) ? (string) $entry['error_code'] : '',
				'error_message'   => isset( $entry['error_message'] ) ? (string) $entry['error_message'] : '',
				'created_at'      => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Paginated automation logs.
	 *
	 * @param array $args status, automation_id, per_page, paged, order.
	 * @return array { items: array, total: int }
	 */
	public static function query( $args = array() ) {
		return self::paginate( 'logs', $args, array( 'trigger_data', 'results' ) );
	}

	/**
	 * Paginated webhook logs.
	 *
	 * @param array $args status (success|error), automation_id, per_page, paged, order.
	 * @return array { items: array, total: int }
	 */
	public static function query_webhook( $args = array() ) {
		return self::paginate( 'webhook_logs', $args, array( 'received_data' ) );
	}

	public static function get( $id ) {
		return self::get_row( 'logs', $id, array( 'trigger_data', 'results' ) );
	}

	public static function get_webhook( $id ) {
		return self::get_row( 'webhook_logs', $id, array( 'received_data' ) );
	}

	/**
	 * IP address of the last webhook call received by an automation.
	 *
	 * @param int $automation_id Automation ID.
	 * @return string
	 */
	public static function last_webhook_ip( $automation_id ) {
		global $wpdb;
		return (string) $wpdb->get_var(
			$wpdb->prepare( 'SELECT remote_ip FROM %i WHERE automation_id = %d AND remote_ip != %s ORDER BY id DESC LIMIT 1', GSAAT_DB::table( 'webhook_logs' ), $automation_id, '' )
		);
	}

	/**
	 * Delete log rows.
	 *
	 * @param string $table logs|webhook_logs.
	 * @param int[]  $ids   Row IDs.
	 */
	public static function delete( $table, array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids || ! in_array( $table, array( 'logs', 'webhook_logs' ), true ) ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only %d placeholders are interpolated.
			$wpdb->prepare( "DELETE FROM %i WHERE id IN ({$placeholders})", array_merge( array( GSAAT_DB::table( $table ) ), $ids ) )
		);
	}

	/**
	 * Empty a log table.
	 *
	 * @param string $table logs|webhook_logs.
	 */
	public static function clear( $table ) {
		global $wpdb;
		if ( in_array( $table, array( 'logs', 'webhook_logs' ), true ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', GSAAT_DB::table( $table ) ) );
		}
	}

	/**
	 * Daily cron: drop logs older than the configured retention.
	 */
	public static function cleanup() {
		global $wpdb;
		$settings = GSAAT_Plugin::settings();
		$map      = array(
			'logs'         => (int) $settings['logs_retention_days'],
			'webhook_logs' => (int) $settings['webhook_logs_retention_days'],
		);
		foreach ( $map as $table => $days ) {
			if ( $days <= 0 ) {
				continue;
			}
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
			$wpdb->query(
				$wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', GSAAT_DB::table( $table ), $cutoff )
			);
		}
	}

	private static function get_row( $table, $id, array $json_columns ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', GSAAT_DB::table( $table ), $id ),
			ARRAY_A
		);
		return $row ? self::decode( $row, $json_columns ) : null;
	}

	private static function paginate( $table, array $args, array $json_columns ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'        => '',
				'automation_id' => 0,
				'per_page'      => 20,
				'paged'         => 1,
				'order'         => 'DESC',
			)
		);

		$where  = '1=1';
		$params = array( GSAAT_DB::table( $table ) );

		if ( 'logs' === $table && '' !== $args['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		} elseif ( 'webhook_logs' === $table && 'success' === $args['status'] ) {
			$where .= ' AND status_code < 300';
		} elseif ( 'webhook_logs' === $table && 'error' === $args['status'] ) {
			$where .= ' AND status_code >= 300';
		}
		if ( $args['automation_id'] ) {
			$where   .= ' AND automation_id = %d';
			$params[] = (int) $args['automation_id'];
		}

		$order  = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$offset = max( 0, ( (int) $args['paged'] - 1 ) * (int) $args['per_page'] );

		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where only contains placeholders.
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", $params )
		);

		$params[] = (int) $args['per_page'];
		$params[] = $offset;

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where only contains placeholders, $order is whitelisted.
			$wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY created_at {$order}, id {$order} LIMIT %d OFFSET %d", $params ),
			ARRAY_A
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::decode( $row, $json_columns );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	private static function decode( array $row, array $json_columns ) {
		foreach ( $json_columns as $column ) {
			$decoded        = json_decode( (string) $row[ $column ], true );
			$row[ $column ] = is_array( $decoded ) ? $decoded : array();
		}
		return $row;
	}
}
