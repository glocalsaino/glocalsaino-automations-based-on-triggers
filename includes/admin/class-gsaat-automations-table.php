<?php
/**
 * Automations list table.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GSAAT_Automations_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'automation',
				'plural'   => 'automations',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Name', 'glocalsaino-automations-based-on-triggers' ),
			'trigger'    => __( 'Trigger', 'glocalsaino-automations-based-on-triggers' ),
			'actions'    => __( 'Actions', 'glocalsaino-automations-based-on-triggers' ),
			'runs'       => __( 'Completed runs', 'glocalsaino-automations-based-on-triggers' ),
			'status'     => __( 'Status', 'glocalsaino-automations-based-on-triggers' ),
			'created_at' => __( 'Created', 'glocalsaino-automations-based-on-triggers' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'       => array( 'name', false ),
			'trigger'    => array( 'trigger_type', false ),
			'runs'       => array( 'run_count', true ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'activate'   => __( 'Activate', 'glocalsaino-automations-based-on-triggers' ),
			'deactivate' => __( 'Deactivate', 'glocalsaino-automations-based-on-triggers' ),
			'delete'     => __( 'Delete', 'glocalsaino-automations-based-on-triggers' ),
		);
	}

	public function prepare_items() {
		$per_page = 20;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list parameters.
		$result = GSAAT_Automations::query(
			array(
				'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
				'per_page' => $per_page,
				'paged'    => $this->get_pagenum(),
			)
		);
		// phpcs:enable

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No automations yet.', 'glocalsaino-automations-based-on-triggers' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );
	}

	protected function column_name( $item ) {
		$edit_url = GSAAT_Admin::url( GSAAT_Admin::PAGE_EDIT, array( 'id' => $item['id'] ) );
		$toggle   = 'active' === $item['status'] ? 'deactivate' : 'activate';

		$actions = array(
			'edit'      => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'glocalsaino-automations-based-on-triggers' ) ),
			$toggle     => sprintf(
				'<a href="%s">%s</a>',
				esc_url( GSAAT_Admin::row_action_url( $toggle, $item['id'] ) ),
				'activate' === $toggle ? esc_html__( 'Activate', 'glocalsaino-automations-based-on-triggers' ) : esc_html__( 'Deactivate', 'glocalsaino-automations-based-on-triggers' )
			),
			'logs'      => sprintf( '<a href="%s">%s</a>', esc_url( GSAAT_Admin::url( GSAAT_Admin::PAGE_LOGS, array( 'automation_id' => $item['id'] ) ) ), esc_html__( 'Logs', 'glocalsaino-automations-based-on-triggers' ) ),
			'duplicate' => sprintf( '<a href="%s">%s</a>', esc_url( GSAAT_Admin::row_action_url( 'duplicate', $item['id'] ) ), esc_html__( 'Duplicate', 'glocalsaino-automations-based-on-triggers' ) ),
			'delete'    => sprintf(
				'<a href="%s" class="glocalsaino-aat-confirm" data-confirm="%s">%s</a>',
				esc_url( GSAAT_Admin::row_action_url( 'delete', $item['id'] ) ),
				esc_attr__( 'Delete this automation? This cannot be undone.', 'glocalsaino-automations-based-on-triggers' ),
				esc_html__( 'Delete', 'glocalsaino-automations-based-on-triggers' )
			),
		);

		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>', esc_url( $edit_url ), esc_html( $item['name'] ) ) . $this->row_actions( $actions );
	}

	protected function column_trigger( $item ) {
		return esc_html( GSAAT_Admin::describe_trigger( $item ) );
	}

	protected function column_actions( $item ) {
		if ( ! $item['actions'] ) {
			return '<span class="glocalsaino-aat-muted">' . esc_html__( 'None', 'glocalsaino-automations-based-on-triggers' ) . '</span>';
		}
		$list = '';
		foreach ( $item['actions'] as $action ) {
			$list .= '<li>' . esc_html( GSAAT_Admin::describe_action( $action ) ) . '</li>';
		}
		return '<ul class="glocalsaino-aat-plain-list">' . $list . '</ul>';
	}

	protected function column_runs( $item ) {
		$max = $item['max_runs'] > 0 ? number_format_i18n( $item['max_runs'] ) : '∞';
		return esc_html( number_format_i18n( $item['run_count'] ) . ' / ' . $max );
	}

	protected function column_status( $item ) {
		$availability = GSAAT_Automations::availability( $item );
		$class        = 'active' === $availability ? 'success' : ( 'inactive' === $availability ? 'neutral' : 'warning' );
		return sprintf(
			'<span class="glocalsaino-aat-badge glocalsaino-aat-badge--%s">%s</span>',
			esc_attr( $class ),
			esc_html( GSAAT_Admin::availability_label( $availability ) )
		);
	}

	protected function column_created_at( $item ) {
		return esc_html( GSAAT_Admin::format_date( $item['created_at'] ) );
	}
}
