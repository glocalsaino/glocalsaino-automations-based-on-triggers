<?php
/**
 * Automation logs list table.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GSAAT_Logs_Table extends WP_List_Table {

	/**
	 * Latest retry of the listed entries (retried ID => { id, status }).
	 *
	 * @var array
	 */
	private $retries = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'created_at' => __( 'Date', 'glocalsaino-automations-based-on-triggers' ),
			'automation' => __( 'Automation', 'glocalsaino-automations-based-on-triggers' ),
			'event'      => __( 'Event', 'glocalsaino-automations-based-on-triggers' ),
			'results'    => __( 'Executed actions', 'glocalsaino-automations-based-on-triggers' ),
			'status'     => __( 'Result', 'glocalsaino-automations-based-on-triggers' ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'retry'  => __( 'Retry failed actions', 'glocalsaino-automations-based-on-triggers' ),
			'delete' => __( 'Delete', 'glocalsaino-automations-based-on-triggers' ),
		);
	}

	protected function get_views() {
		$current = $this->current_status();
		$views   = array();
		$labels  = array( '' => __( 'All', 'glocalsaino-automations-based-on-triggers' ) );
		foreach ( array( 'success', 'partial', 'error', 'skipped' ) as $status ) {
			$labels[ $status ] = GSAAT_Admin::log_status_label( $status );
		}
		foreach ( $labels as $status => $label ) {
			$url             = GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array_filter( array( 'status' => $status ) ) );
			$views[ '' === $status ? 'all' : $status ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $url ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		return $views;
	}

	public function prepare_items() {
		$per_page = 20;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$automation_id = isset( $_GET['automation_id'] ) ? absint( wp_unslash( $_GET['automation_id'] ) ) : 0;

		$result = GSAAT_Logs::query(
			array(
				'status'        => $this->current_status(),
				'automation_id' => $automation_id,
				'per_page'      => $per_page,
				'paged'         => $this->get_pagenum(),
			)
		);

		$this->items           = $result['items'];
		$this->retries         = GSAAT_Logs::retries_for( wp_list_pluck( $this->items, 'id' ) );
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'created_at' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No log entries.', 'glocalsaino-automations-based-on-triggers' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );
	}

	protected function column_created_at( $item ) {
		$view_url = GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'view' => $item['id'] ) );
		$actions  = array(
			'view'   => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'View details', 'glocalsaino-automations-based-on-triggers' ) ),
		);
		if ( in_array( $item['status'], array( 'error', 'partial' ), true ) ) {
			$actions['retry'] = sprintf( '<a href="%s">%s</a>', esc_url( GSAAT_Admin::row_action_url( 'retry_log', $item['id'] ) ), esc_html__( 'Retry failed actions', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$actions += array(
			'delete' => sprintf( '<a href="%s">%s</a>', esc_url( GSAAT_Admin::row_action_url( 'delete_log', $item['id'] ) ), esc_html__( 'Delete', 'glocalsaino-automations-based-on-triggers' ) ),
		);
		return sprintf( '<a class="row-title" href="%s">%s</a>', esc_url( $view_url ), esc_html( GSAAT_Admin::format_date( $item['created_at'] ) ) ) . $this->row_actions( $actions );
	}

	protected function column_automation( $item ) {
		if ( ! GSAAT_Automations::get( $item['automation_id'] ) ) {
			return esc_html( $item['automation_name'] );
		}
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_EDIT, array( 'id' => $item['automation_id'] ) ) ),
			esc_html( $item['automation_name'] )
		);
	}

	protected function column_event( $item ) {
		return esc_html( $item['event'] );
	}

	protected function column_results( $item ) {
		if ( ! $item['results'] ) {
			return '<span class="glocalsaino-aat-muted">' . esc_html( $item['message'] ? $item['message'] : '—' ) . '</span>';
		}
		$list = '';
		foreach ( $item['results'] as $result ) {
			$list .= sprintf(
				'<li><span class="dashicons %1$s" aria-hidden="true"></span> %2$s <span class="screen-reader-text">%3$s</span></li>',
				esc_attr( ! empty( $result['success'] ) ? 'dashicons-yes-alt glocalsaino-aat-ok' : 'dashicons-dismiss glocalsaino-aat-ko' ),
				esc_html( GSAAT_Admin::result_label( $result ) ),
				esc_html( ! empty( $result['success'] ) ? __( 'OK', 'glocalsaino-automations-based-on-triggers' ) : __( 'Failed', 'glocalsaino-automations-based-on-triggers' ) )
			);
		}
		return '<ul class="glocalsaino-aat-plain-list">' . $list . '</ul>';
	}

	protected function column_status( $item ) {
		$classes = array(
			'success' => 'success',
			'partial' => 'warning',
			'error'   => 'error',
			'skipped' => 'neutral',
		);
		$html = sprintf(
			'<span class="glocalsaino-aat-badge glocalsaino-aat-badge--%s">%s</span>',
			esc_attr( isset( $classes[ $item['status'] ] ) ? $classes[ $item['status'] ] : 'neutral' ),
			esc_html( GSAAT_Admin::log_status_label( $item['status'] ) )
		);
		if ( isset( $this->retries[ $item['id'] ] ) ) {
			$retry = $this->retries[ $item['id'] ];
			$html .= sprintf(
				'<br><a class="glocalsaino-aat-muted" href="%1$s">%2$s</a>',
				esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'view' => $retry['id'] ) ) ),
				/* translators: 1: log entry ID, 2: status of the retry. */
				esc_html( sprintf( __( 'Retried in #%1$d: %2$s', 'glocalsaino-automations-based-on-triggers' ), $retry['id'], GSAAT_Admin::log_status_label( $retry['status'] ) ) )
			);
		}
		return $html;
	}

	private function current_status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		return in_array( $status, array( 'success', 'partial', 'error', 'skipped' ), true ) ? $status : '';
	}
}
