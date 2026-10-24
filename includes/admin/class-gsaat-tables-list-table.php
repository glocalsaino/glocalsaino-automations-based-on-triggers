<?php
/**
 * List tables of the Tables screens: tables, and rows of one table.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GSAAT_Tables_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'data-table',
				'plural'   => 'data-tables',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'name'       => __( 'Name', 'glocalsaino-actions-automation-based-on-trigger' ),
			'columns'    => __( 'Columns', 'glocalsaino-actions-automation-based-on-trigger' ),
			'rows'       => __( 'Rows', 'glocalsaino-actions-automation-based-on-trigger' ),
			'shortcode'  => __( 'Shortcode', 'glocalsaino-actions-automation-based-on-trigger' ),
			'visibility' => __( 'Visible to', 'glocalsaino-actions-automation-based-on-trigger' ),
			'updated_at' => __( 'Last change', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	public function prepare_items() {
		$this->items           = GSAAT_Tables::all();
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'name' );
	}

	public function no_items() {
		esc_html_e( 'No tables yet.', 'glocalsaino-actions-automation-based-on-trigger' );
	}

	protected function column_name( $item ) {
		$edit    = GSAAT_Admin_Tables::url( array( 'edit' => $item['id'] ) );
		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'rows'   => sprintf( '<a href="%s">%s</a>', esc_url( GSAAT_Admin_Tables::url( array( 'rows' => $item['id'] ) ) ), esc_html__( 'View rows', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'export' => sprintf( '<a href="%s">%s</a>', esc_url( GSAAT_Admin_Tables::action_url( 'export', $item['id'] ) ), esc_html__( 'Export CSV', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'delete' => sprintf(
				'<a href="%s" class="glocalsaino-aat-confirm" data-confirm="%s">%s</a>',
				esc_url( GSAAT_Admin_Tables::action_url( 'delete', $item['id'] ) ),
				esc_attr__( 'Delete this table and all its rows? Automations that add rows to it will fail.', 'glocalsaino-actions-automation-based-on-trigger' ),
				esc_html__( 'Delete', 'glocalsaino-actions-automation-based-on-trigger' )
			),
		);
		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>', esc_url( $edit ), esc_html( $item['name'] ) ) . $this->row_actions( $actions );
	}

	protected function column_columns( $item ) {
		return esc_html( implode( ', ', wp_list_pluck( $item['columns'], 'label' ) ) );
	}

	protected function column_rows( $item ) {
		return esc_html( number_format_i18n( GSAAT_Tables::count_rows( $item['id'] ) ) );
	}

	protected function column_shortcode( $item ) {
		return '<code>[' . esc_html( GSAAT_Tables::SHORTCODE ) . ' id="' . esc_html( (string) $item['id'] ) . '"]</code>';
	}

	protected function column_visibility( $item ) {
		$labels = GSAAT_Admin_Tables::visibility_labels();
		return esc_html( isset( $labels[ $item['settings']['visibility'] ] ) ? $labels[ $item['settings']['visibility'] ] : $item['settings']['visibility'] );
	}

	protected function column_updated_at( $item ) {
		return esc_html( GSAAT_Admin::format_date( $item['updated_at'] ) );
	}
}

class GSAAT_Table_Rows_List_Table extends WP_List_Table {

	const SHOWN_COLUMNS = 8;

	/**
	 * Table whose rows are listed.
	 *
	 * @var array
	 */
	private $table;

	public function __construct( array $table ) {
		$this->table = $table;
		parent::__construct(
			array(
				'singular' => 'table-row',
				'plural'   => 'table-rows',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		$columns = array( 'cb' => '<input type="checkbox" />' );
		foreach ( array_slice( $this->table['columns'], 0, self::SHOWN_COLUMNS ) as $column ) {
			$columns[ 'col_' . $column['key'] ] = $column['label'];
		}
		$columns['created_at'] = __( 'Added', 'glocalsaino-actions-automation-based-on-trigger' );
		return $columns;
	}

	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'glocalsaino-actions-automation-based-on-trigger' ) );
	}

	public function prepare_items() {
		$per_page = 50;
		$total    = GSAAT_Tables::count_rows( $this->table['id'] );
		$columns  = $this->get_columns();

		$this->items           = GSAAT_Tables::rows( $this->table['id'], $per_page, ( $this->get_pagenum() - 1 ) * $per_page, 'newest' );
		$this->_column_headers = array( $columns, array(), array(), (string) array_keys( $columns )[1] );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'This table has no rows yet.', 'glocalsaino-actions-automation-based-on-trigger' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );
	}

	protected function column_created_at( $item ) {
		$actions = array(
			'delete' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( GSAAT_Admin_Tables::action_url( 'delete_row', $this->table['id'], $item['id'] ) ),
				esc_html__( 'Delete', 'glocalsaino-actions-automation-based-on-trigger' )
			),
		);
		return esc_html( GSAAT_Admin::format_date( $item['created_at'] ) ) . $this->row_actions( $actions );
	}

	protected function column_default( $item, $column_name ) {
		$key   = substr( $column_name, 4 );
		$value = isset( $item['data'][ $key ] ) ? (string) $item['data'][ $key ] : '';
		return esc_html( function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $value, 0, 120, '…' ) : substr( $value, 0, 120 ) );
	}
}

