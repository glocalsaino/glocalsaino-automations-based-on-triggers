<?php
/**
 * Admin screens of the data tables: list, edit (columns + display), rows.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Admin_Tables {

	const PAGE = 'glocalsaino-aat-tables';

	/**
	 * Hook suffix of the Tables page.
	 *
	 * @var string
	 */
	private static $hook = '';

	public static function init() {
		add_action( 'glocalsaino_aat_admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_glocalsaino_aat_save_table', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_glocalsaino_aat_table_action', array( __CLASS__, 'handle_action' ) );
		add_filter( 'glocalsaino_aat_admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menu( $parent ) {
		self::$hook = add_submenu_page(
			$parent,
			__( 'Tables', 'glocalsaino-actions-automation-based-on-trigger' ),
			__( 'Tables', 'glocalsaino-actions-automation-based-on-trigger' ),
			GSAAT_Admin::CAP,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
		add_action( 'load-' . self::$hook, array( __CLASS__, 'process_bulk' ) );
	}

	public static function enqueue( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_style( 'glocalsaino-aat-admin', GSAAT_URL . 'assets/css/admin.css', array(), GSAAT_VERSION );
		wp_enqueue_script( 'glocalsaino-aat-admin', GSAAT_URL . 'assets/js/admin.js', array(), GSAAT_VERSION, true );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : -1;
		if ( $id < 0 ) {
			return;
		}
		$table   = $id ? GSAAT_Tables::get( $id ) : null;
		$columns = $table ? $table['columns'] : array();
		foreach ( $columns as &$column ) {
			$column['saved'] = true;
		}
		unset( $column );

		wp_enqueue_script( 'glocalsaino-aat-tables', GSAAT_URL . 'assets/js/admin-tables.js', array(), GSAAT_VERSION, true );
		wp_add_inline_script(
			'glocalsaino-aat-tables',
			'window.glocalsainoAatTables = ' . wp_json_encode(
				array(
					'columns' => $columns,
					'types'   => GSAAT_Tables::column_types(),
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'glocalsaino_aat_detect_columns' ),
					'i18n'    => array(
						'key'      => __( 'Field name', 'glocalsaino-actions-automation-based-on-trigger' ),
						'label'    => __( 'Column title', 'glocalsaino-actions-automation-based-on-trigger' ),
						'type'     => __( 'Type', 'glocalsaino-actions-automation-based-on-trigger' ),
						'remove'   => __( 'Remove', 'glocalsaino-actions-automation-based-on-trigger' ),
						'moveUp'   => __( 'Move up', 'glocalsaino-actions-automation-based-on-trigger' ),
						'moveDown' => __( 'Move down', 'glocalsaino-actions-automation-based-on-trigger' ),
						'none'     => __( 'No columns yet.', 'glocalsaino-actions-automation-based-on-trigger' ),
						'reading'  => __( 'Reading the file…', 'glocalsaino-actions-automation-based-on-trigger' ),
						'imported' => __( 'Columns added from the file.', 'glocalsaino-actions-automation-based-on-trigger' ),
						'error'    => __( 'The file could not be read.', 'glocalsaino-actions-automation-based-on-trigger' ),
						'noKeys'   => __( 'Every column needs a title.', 'glocalsaino-actions-automation-based-on-trigger' ),
					),
				)
			) . ';',
			'before'
		);
	}

	public static function notices( $messages ) {
		$messages['table_saved']        = array( 'success', __( 'Table saved.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		$messages['table_no_columns']   = array( 'warning', __( 'Table saved without columns: add at least one so it can receive rows.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		$messages['table_deleted']      = array( 'success', __( 'Table deleted.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		$messages['table_emptied']      = array( 'success', __( 'All the rows of the table were deleted.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		$messages['table_rows_deleted'] = array( 'success', __( 'Rows deleted.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		return $messages;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Screens
	 * ---------------------------------------------------------------------
	 */

	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$edit = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : -1;
		$rows = isset( $_GET['rows'] ) ? absint( wp_unslash( $_GET['rows'] ) ) : 0;
		// phpcs:enable

		if ( $edit >= 0 ) {
			$table = $edit ? GSAAT_Tables::get( $edit ) : array(
				'id'       => 0,
				'name'     => '',
				'columns'  => array(),
				'settings' => GSAAT_Tables::default_settings(),
			);
			if ( ! $table ) {
				wp_die( esc_html__( 'Table not found.', 'glocalsaino-actions-automation-based-on-trigger' ) );
			}
			include GSAAT_PATH . 'includes/admin/views/table-edit.php';
			return;
		}

		require_once GSAAT_PATH . 'includes/admin/class-gsaat-tables-list-table.php';
		if ( $rows ) {
			$table = GSAAT_Tables::get( $rows );
			if ( ! $table ) {
				wp_die( esc_html__( 'Table not found.', 'glocalsaino-actions-automation-based-on-trigger' ) );
			}
			$list = new GSAAT_Table_Rows_List_Table( $table );
			$list->prepare_items();
			include GSAAT_PATH . 'includes/admin/views/table-rows.php';
			return;
		}

		$list = new GSAAT_Tables_List_Table();
		$list->prepare_items();
		include GSAAT_PATH . 'includes/admin/views/tables.php';
	}

	public static function visibility_labels() {
		return array(
			'everyone'  => __( 'Everyone', 'glocalsaino-actions-automation-based-on-trigger' ),
			'logged_in' => __( 'Logged-in users', 'glocalsaino-actions-automation-based-on-trigger' ),
			'roles'     => __( 'Users with these roles', 'glocalsaino-actions-automation-based-on-trigger' ),
			'nobody'    => __( 'Nobody (administrators only)', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	public static function url( array $args = array() ) {
		return GSAAT_Admin::url( self::PAGE, $args );
	}

	/**
	 * Nonced URL of a table action (delete, empty, export, delete_row).
	 *
	 * @param string $do     Action.
	 * @param int    $id     Table ID.
	 * @param int    $row_id Row ID (delete_row).
	 * @return string
	 */
	public static function action_url( $do, $id, $row_id = 0 ) {
		return wp_nonce_url(
			add_query_arg(
				array_filter(
					array(
						'action' => 'glocalsaino_aat_table_action',
						'do'     => $do,
						'id'     => (int) $id,
						'row'    => (int) $row_id,
					)
				),
				admin_url( 'admin-post.php' )
			),
			'glocalsaino_aat_table_' . $do . '_' . (int) $id . '_' . (int) $row_id
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Handlers
	 * ---------------------------------------------------------------------
	 */

	public static function handle_save() {
		self::check_capability();
		check_admin_referer( 'glocalsaino_aat_save_table' );

		$id   = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// JSON built by the table editor script; sanitized by GSAAT_Tables::sanitize_columns().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$columns = isset( $_POST['columns_json'] ) ? json_decode( (string) wp_unslash( $_POST['columns_json'] ), true ) : array();
		$roles   = isset( $_POST['roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ) ) : array();

		$settings = array(
			'visibility' => isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : 'everyone',
			'roles'      => $roles,
			'search'     => ! empty( $_POST['search'] ),
			'sort'       => ! empty( $_POST['sort'] ),
			'per_page'   => isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 25,
			'order'      => isset( $_POST['order'] ) ? sanitize_key( wp_unslash( $_POST['order'] ) ) : 'newest',
			'max_rows'   => isset( $_POST['max_rows'] ) ? absint( wp_unslash( $_POST['max_rows'] ) ) : 1000,
			'download'   => ! empty( $_POST['download'] ),
			'show_date'  => ! empty( $_POST['show_date'] ),
			'css_class'  => isset( $_POST['css_class'] ) ? sanitize_text_field( wp_unslash( $_POST['css_class'] ) ) : '',
		);

		$saved = GSAAT_Tables::save(
			array(
				'name'     => '' !== $name ? $name : __( 'Untitled table', 'glocalsaino-actions-automation-based-on-trigger' ),
				'columns'  => is_array( $columns ) ? $columns : array(),
				'settings' => $settings,
			),
			$id
		);
		if ( ! $saved ) {
			wp_die( esc_html__( 'The table could not be saved.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		$table = GSAAT_Tables::get( $saved );
		wp_safe_redirect(
			self::url(
				array(
					'edit'                    => $saved,
					GSAAT_Admin::MESSAGE_ARG => $table && $table['columns'] ? 'table_saved' : 'table_no_columns',
				)
			)
		);
		exit;
	}

	public static function handle_action() {
		self::check_capability();
		$do  = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$id  = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$row = isset( $_GET['row'] ) ? absint( wp_unslash( $_GET['row'] ) ) : 0;
		check_admin_referer( 'glocalsaino_aat_table_' . $do . '_' . $id . '_' . $row );

		$table = GSAAT_Tables::get( $id );
		if ( ! $table ) {
			wp_die( esc_html__( 'Table not found.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		switch ( $do ) {
			case 'export':
				GSAAT_Tables::send_csv( $table );
				break;
			case 'delete':
				GSAAT_Tables::delete( $id );
				wp_safe_redirect( self::url( array( GSAAT_Admin::MESSAGE_ARG => 'table_deleted' ) ) );
				exit;
			case 'empty':
				GSAAT_Tables::clear( $id );
				wp_safe_redirect(
					self::url(
						array(
							'rows'                   => $id,
							GSAAT_Admin::MESSAGE_ARG => 'table_emptied',
						)
					)
				);
				exit;
			case 'delete_row':
				GSAAT_Tables::delete_rows( $id, array( $row ) );
				wp_safe_redirect(
					self::url(
						array(
							'rows'                   => $id,
							GSAAT_Admin::MESSAGE_ARG => 'table_rows_deleted',
						)
					)
				);
				exit;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Bulk delete of rows on the rows screen.
	 */
	public static function process_bulk() {
		if ( empty( $_REQUEST['ids'] ) || ! current_user_can( GSAAT_Admin::CAP ) ) {
			return;
		}
		check_admin_referer( 'bulk-table-rows' );
		$action = '';
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) && '-1' !== $_REQUEST[ $key ] ) {
				$action = sanitize_key( wp_unslash( $_REQUEST[ $key ] ) );
				break;
			}
		}
		$table_id = isset( $_REQUEST['rows'] ) ? absint( wp_unslash( $_REQUEST['rows'] ) ) : 0;
		if ( 'delete' !== $action || ! $table_id ) {
			return;
		}
		GSAAT_Tables::delete_rows( $table_id, array_map( 'absint', (array) wp_unslash( $_REQUEST['ids'] ) ) );
		wp_safe_redirect(
			self::url(
				array(
					'rows'                   => $table_id,
					GSAAT_Admin::MESSAGE_ARG => 'table_rows_deleted',
				)
			)
		);
		exit;
	}

	private static function check_capability() {
		if ( ! current_user_can( GSAAT_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'glocalsaino-actions-automation-based-on-trigger' ), 403 );
		}
	}
}
