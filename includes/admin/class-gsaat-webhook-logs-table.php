<?php
/**
 * Webhook logs list table.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GSAAT_Webhook_Logs_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'webhook-log',
				'plural'   => 'webhook-logs',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'created_at' => __( 'Date', 'glocalsaino-actions-automation-based-on-trigger' ),
			'automation' => __( 'Automation', 'glocalsaino-actions-automation-based-on-trigger' ),
			'source'     => __( 'Source', 'glocalsaino-actions-automation-based-on-trigger' ),
			'method'     => __( 'Method', 'glocalsaino-actions-automation-based-on-trigger' ),
			'remote_ip'  => __( 'Caller IP', 'glocalsaino-actions-automation-based-on-trigger' ),
			'received'   => __( 'Received fields', 'glocalsaino-actions-automation-based-on-trigger' ),
			'status'     => __( 'Result', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'glocalsaino-actions-automation-based-on-trigger' ) );
	}

	protected function get_views() {
		$current = $this->current_status();
		$source  = $this->current_source();
		$labels  = array(
			'all'     => array( __( 'All', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'success' => array( __( 'Received', 'glocalsaino-actions-automation-based-on-trigger' ), array( 'status' => 'success' ) ),
			'error'   => array( __( 'Errors', 'glocalsaino-actions-automation-based-on-trigger' ), array( 'status' => 'error' ) ),
			'webhook' => array( __( 'Webhooks', 'glocalsaino-actions-automation-based-on-trigger' ), array( 'source' => 'webhook' ) ),
			'widget'  => array( __( 'Widgets', 'glocalsaino-actions-automation-based-on-trigger' ), array( 'source' => 'widget' ) ),
		);
		$views   = array();
		foreach ( $labels as $key => $view ) {
			$is_current   = ( 'all' === $key && '' === $current && '' === $source ) || $key === $current || $key === $source;
			$views[ $key ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_WEBHOOK, $view[1] ) ),
				$is_current ? ' class="current" aria-current="page"' : '',
				esc_html( $view[0] )
			);
		}
		return $views;
	}

	public function prepare_items() {
		$per_page = 20;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$automation_id = isset( $_GET['automation_id'] ) ? absint( wp_unslash( $_GET['automation_id'] ) ) : 0;

		$result = GSAAT_Logs::query_webhook(
			array(
				'status'        => $this->current_status(),
				'source'        => $this->current_source(),
				'automation_id' => $automation_id,
				'per_page'      => $per_page,
				'paged'         => $this->get_pagenum(),
			)
		);

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'created_at' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No webhook calls have been received yet.', 'glocalsaino-actions-automation-based-on-trigger' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );
	}

	protected function column_created_at( $item ) {
		$view_url = GSAAT_Admin::url( GSAAT_Admin::PAGE_WEBHOOK, array( 'view' => $item['id'] ) );
		$actions  = array(
			'view'   => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'View details', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'delete' => sprintf( '<a href="%s">%s</a>', esc_url( GSAAT_Admin::row_action_url( 'delete_webhook_log', $item['id'] ) ), esc_html__( 'Delete', 'glocalsaino-actions-automation-based-on-trigger' ) ),
		);
		return sprintf( '<a class="row-title" href="%s">%s</a>', esc_url( $view_url ), esc_html( GSAAT_Admin::format_date( $item['created_at'] ) ) ) . $this->row_actions( $actions );
	}

	protected function column_automation( $item ) {
		return esc_html( $item['automation_name'] );
	}

	protected function column_source( $item ) {
		return 'widget' === $item['source'] ? esc_html__( 'Widget', 'glocalsaino-actions-automation-based-on-trigger' ) : esc_html__( 'Webhook', 'glocalsaino-actions-automation-based-on-trigger' );
	}

	protected function column_method( $item ) {
		return '<code>' . esc_html( $item['method'] ) . '</code>';
	}

	protected function column_remote_ip( $item ) {
		return '' !== $item['remote_ip'] ? '<code>' . esc_html( $item['remote_ip'] ) . '</code>' : '—';
	}

	protected function column_received( $item ) {
		$count = count( $item['received_data'] );
		if ( ! $count ) {
			return '<span class="glocalsaino-aat-muted">0</span>';
		}
		$keys = implode( ', ', array_slice( array_keys( $item['received_data'] ), 0, 6 ) );
		return esc_html( number_format_i18n( $count ) . ' — ' . $keys . ( $count > 6 ? ', …' : '' ) );
	}

	protected function column_status( $item ) {
		$code = (int) $item['status_code'];
		if ( $code < 300 ) {
			return sprintf( '<span class="glocalsaino-aat-badge glocalsaino-aat-badge--success">%s</span>', esc_html( (string) $code ) );
		}
		return sprintf(
			'<span class="glocalsaino-aat-badge glocalsaino-aat-badge--error">%1$s</span> <span class="glocalsaino-aat-muted">%2$s</span>',
			esc_html( (string) $code ),
			esc_html( $item['error_message'] )
		);
	}

	private function current_source() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		return in_array( $source, array( 'webhook', 'widget' ), true ) ? $source : '';
	}

	private function current_status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		return in_array( $status, array( 'success', 'error' ), true ) ? $status : '';
	}
}
