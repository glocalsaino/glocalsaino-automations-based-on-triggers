<?php
/**
 * Automations repository.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; there is no core API for them.

class GSAAT_Automations {

	const REST_NAMESPACE = 'glocalsaino-aat/v1';

	/**
	 * Available trigger types.
	 *
	 * @return array
	 */
	public static function trigger_types() {
		return wp_list_pluck( GSAAT_Registry::trigger_types(), 'label' );
	}

	/**
	 * Fetch one automation.
	 *
	 * @param int $id Automation ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', GSAAT_DB::table( 'automations' ), $id ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Fetch the automation that owns a webhook key.
	 *
	 * @param string $key Webhook key.
	 * @return array|null
	 */
	public static function get_by_webhook_key( $key ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE webhook_key = %s LIMIT 1', GSAAT_DB::table( 'automations' ), $key ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Active automations for one trigger type.
	 *
	 * @param string $trigger_type Trigger type.
	 * @return array
	 */
	public static function get_active_by_trigger( $trigger_type ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND trigger_type = %s ORDER BY id ASC',
				GSAAT_DB::table( 'automations' ),
				'active',
				$trigger_type
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Paginated list.
	 *
	 * @param array $args search, orderby, order, per_page, paged.
	 * @return array { items: array, total: int }
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'paged'    => 1,
			)
		);

		$orderby = in_array( $args['orderby'], array( 'name', 'run_count', 'status', 'created_at', 'trigger_type' ), true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$table   = GSAAT_DB::table( 'automations' );
		$offset  = max( 0, ( (int) $args['paged'] - 1 ) * (int) $args['per_page'] );

		$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		$per_page = (int) $args['per_page'];

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE name LIKE %s', $table, $like )
		);

		if ( 'ASC' === $order ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE name LIKE %s ORDER BY %i ASC, id DESC LIMIT %d OFFSET %d', $table, $like, $orderby, $per_page, $offset ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE name LIKE %s ORDER BY %i DESC, id DESC LIMIT %d OFFSET %d', $table, $like, $orderby, $per_page, $offset ),
				ARRAY_A
			);
		}

		return array(
			'items' => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Insert or update an automation. $data must already be sanitized.
	 *
	 * @param array $data Automation fields.
	 * @param int   $id   Existing ID, 0 to insert.
	 * @return int Automation ID (0 on failure).
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$row = array(
			'name'           => $data['name'],
			'status'         => $data['status'],
			'trigger_type'   => $data['trigger_type'],
			'trigger_config' => wp_json_encode( $data['trigger_config'] ),
			'conditions'     => wp_json_encode( $data['conditions'] ),
			'actions'        => wp_json_encode( $data['actions'] ),
			'max_runs'       => (int) $data['max_runs'],
			'start_date'     => $data['start_date'],
			'end_date'       => $data['end_date'],
			'webhook_key'    => $data['webhook_key'],
			'meta'           => wp_json_encode( isset( $data['meta'] ) ? $data['meta'] : array() ),
			'updated_at'     => $now,
		);

		if ( $id ) {
			$updated = $wpdb->update( GSAAT_DB::table( 'automations' ), $row, array( 'id' => (int) $id ) );
			return false === $updated ? 0 : (int) $id;
		}

		$row['created_at'] = $now;
		$row['run_count']  = isset( $data['run_count'] ) ? (int) $data['run_count'] : 0;
		$inserted          = $wpdb->insert( GSAAT_DB::table( 'automations' ), $row );
		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	public static function delete( $id ) {
		global $wpdb;
		GSAAT_Scheduler::cancel_for_automation( (int) $id );
		return (bool) $wpdb->delete( GSAAT_DB::table( 'automations' ), array( 'id' => (int) $id ) );
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		return false !== $wpdb->update(
			GSAAT_DB::table( 'automations' ),
			array(
				'status'     => 'active' === $status ? 'active' : 'inactive',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
	}

	public static function increment_runs( $id ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare( 'UPDATE %i SET run_count = run_count + 1 WHERE id = %d', GSAAT_DB::table( 'automations' ), $id )
		);
	}

	/**
	 * Duplicate an automation (inactive, counter reset, new webhook key).
	 *
	 * @param int $id Source automation ID.
	 * @return int New automation ID (0 on failure).
	 */
	public static function duplicate( $id ) {
		$source = self::get( $id );
		if ( ! $source ) {
			return 0;
		}
		/* translators: %s: original automation name. */
		$source['name']        = sprintf( __( '%s (copy)', 'glocalsaino-automations-based-on-triggers' ), $source['name'] );
		$source['status']      = 'inactive';
		$source['run_count']   = 0;
		$source['webhook_key'] = self::generate_webhook_key();
		return self::save( $source );
	}

	public static function generate_webhook_key() {
		do {
			$key = wp_generate_password( 32, false, false );
		} while ( self::webhook_key_exists( $key ) );
		return $key;
	}

	public static function webhook_key_exists( $key, $exclude_id = 0 ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE webhook_key = %s AND id != %d LIMIT 1',
				GSAAT_DB::table( 'automations' ),
				$key,
				$exclude_id
			)
		);
	}

	/**
	 * Public URL of a webhook key.
	 *
	 * @param string $key Webhook key.
	 * @return string
	 */
	public static function webhook_url( $key ) {
		return rest_url( self::REST_NAMESPACE . '/webhook/' . $key );
	}

	/**
	 * Effective availability, combining the status switch with run limits and dates.
	 *
	 * @param array $automation Automation.
	 * @return string active|inactive|scheduled|expired|limit_reached
	 */
	public static function availability( array $automation ) {
		if ( 'active' !== $automation['status'] ) {
			return 'inactive';
		}
		if ( $automation['max_runs'] > 0 && $automation['run_count'] >= $automation['max_runs'] ) {
			return 'limit_reached';
		}
		$now = current_time( 'mysql', true );
		if ( $automation['start_date'] && $now < $automation['start_date'] ) {
			return 'scheduled';
		}
		if ( $automation['end_date'] && $now > $automation['end_date'] ) {
			return 'expired';
		}
		return 'active';
	}

	/**
	 * Decode a database row into a usable automation array.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$conditions = json_decode( (string) $row['conditions'], true );
		$conditions = wp_parse_args(
			is_array( $conditions ) ? $conditions : array(),
			array(
				'logic' => 'all',
				'rules' => array(),
			)
		);

		$trigger_config = json_decode( (string) $row['trigger_config'], true );
		$actions        = json_decode( (string) $row['actions'], true );
		$meta           = json_decode( isset( $row['meta'] ) ? (string) $row['meta'] : '', true );

		return array(
			'id'             => (int) $row['id'],
			'name'           => (string) $row['name'],
			'status'         => (string) $row['status'],
			'trigger_type'   => (string) $row['trigger_type'],
			'trigger_config' => is_array( $trigger_config ) ? $trigger_config : array(),
			'conditions'     => $conditions,
			'actions'        => is_array( $actions ) ? $actions : array(),
			'max_runs'       => (int) $row['max_runs'],
			'run_count'      => (int) $row['run_count'],
			'start_date'     => $row['start_date'] ? (string) $row['start_date'] : null,
			'end_date'       => $row['end_date'] ? (string) $row['end_date'] : null,
			'webhook_key'    => (string) $row['webhook_key'],
			'meta'           => is_array( $meta ) ? $meta : array(),
			'created_at'     => (string) $row['created_at'],
			'updated_at'     => (string) $row['updated_at'],
		);
	}
}
