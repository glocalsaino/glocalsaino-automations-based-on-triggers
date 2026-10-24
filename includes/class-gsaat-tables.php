<?php
/**
 * Data tables: defined in the back end, filled by the "Add rows to a table" action (or by
 * add-ons through the public methods below) and shown with [glocalsaino_aat_table id="…"].
 *
 * Public API for add-ons: GSAAT_Tables::create(), ::get(), ::all(), ::add_rows(), ::clear().
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; there is no core API for them.

class GSAAT_Tables {

	const SHORTCODE  = 'glocalsaino_aat_table';
	const CSV_ARG    = 'glocalsaino_aat_table_csv';
	const MAX_VALUE  = 5000;
	const MAX_INSERT = 10000;

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_download' ), 1 );
	}

	/**
	 * Column types.
	 *
	 * @return array
	 */
	public static function column_types() {
		return array(
			'text'   => __( 'Text', 'glocalsaino-actions-automation-based-on-trigger' ),
			'number' => __( 'Number', 'glocalsaino-actions-automation-based-on-trigger' ),
			'date'   => __( 'Date', 'glocalsaino-actions-automation-based-on-trigger' ),
			'link'   => __( 'Link (URL)', 'glocalsaino-actions-automation-based-on-trigger' ),
			'email'  => __( 'Email', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	public static function default_settings() {
		return array(
			'visibility' => 'everyone',
			'roles'      => array(),
			'search'     => true,
			'sort'       => true,
			'per_page'   => 25,
			'order'      => 'newest',
			'max_rows'   => 1000,
			'download'   => false,
			'show_date'  => false,
			'css_class'  => '',
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Tables
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Create a table (public API).
	 *
	 * @param string $name     Name.
	 * @param array  $columns  [ { key, label, type } ] — or key => label.
	 * @param array  $settings Display settings (see default_settings()).
	 * @return int Table ID (0 on failure).
	 */
	public static function create( $name, array $columns, array $settings = array() ) {
		return self::save(
			array(
				'name'     => $name,
				'columns'  => $columns,
				'settings' => $settings,
			)
		);
	}

	/**
	 * Insert or update a table.
	 *
	 * @param array $data { name, columns, settings }.
	 * @param int   $id   Table ID (0 = new).
	 * @return int
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = array(
			'name'       => sanitize_text_field( (string) $data['name'] ),
			'columns'    => wp_json_encode( self::sanitize_columns( (array) $data['columns'] ) ),
			'settings'   => wp_json_encode( self::sanitize_settings( (array) $data['settings'] ) ),
			'updated_at' => $now,
		);
		if ( $id ) {
			return false === $wpdb->update( GSAAT_DB::table( 'tables' ), $row, array( 'id' => (int) $id ) ) ? 0 : (int) $id;
		}
		$row['created_at'] = $now;
		return $wpdb->insert( GSAAT_DB::table( 'tables' ), $row ) ? (int) $wpdb->insert_id : 0;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', GSAAT_DB::table( 'tables' ), $id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * All tables, by name.
	 *
	 * @return array[]
	 */
	public static function all() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY name ASC LIMIT 1000', GSAAT_DB::table( 'tables' ) ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	public static function delete( $id ) {
		global $wpdb;
		self::clear( $id );
		return (bool) $wpdb->delete( GSAAT_DB::table( 'tables' ), array( 'id' => (int) $id ) );
	}

	private static function hydrate( array $row ) {
		$columns  = json_decode( (string) $row['columns'], true );
		$settings = json_decode( (string) $row['settings'], true );
		return array(
			'id'         => (int) $row['id'],
			'name'       => (string) $row['name'],
			'columns'    => is_array( $columns ) ? $columns : array(),
			'settings'   => wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() ),
			'created_at' => (string) $row['created_at'],
			'updated_at' => (string) $row['updated_at'],
		);
	}

	/**
	 * Columns: unique keys (letters, digits, _), a label and a type.
	 *
	 * @param array $columns [ { key, label, type } ] or key => label.
	 * @return array
	 */
	public static function sanitize_columns( array $columns ) {
		$clean = array();
		$keys  = array();
		$types = self::column_types();
		foreach ( $columns as $index => $column ) {
			if ( ! is_array( $column ) ) {
				$column = array(
					'key'   => is_string( $index ) ? $index : '',
					'label' => (string) $column,
				);
			}
			$label = isset( $column['label'] ) ? sanitize_text_field( (string) $column['label'] ) : '';
			$key   = GSAAT_File_Reader::key_from_label( isset( $column['key'] ) && '' !== (string) $column['key'] ? (string) $column['key'] : $label );
			if ( '' === $key || in_array( $key, $keys, true ) ) {
				continue;
			}
			$keys[]  = $key;
			$clean[] = array(
				'key'   => $key,
				'label' => '' !== $label ? $label : $key,
				'type'  => isset( $column['type'], $types[ $column['type'] ] ) ? $column['type'] : 'text',
			);
		}
		return $clean;
	}

	public static function sanitize_settings( array $settings ) {
		$settings = wp_parse_args( $settings, self::default_settings() );
		$roles    = array_values( array_intersect( array_map( 'sanitize_key', (array) $settings['roles'] ), array_keys( wp_roles()->get_names() ) ) );
		return array(
			'visibility' => in_array( $settings['visibility'], array( 'everyone', 'logged_in', 'roles', 'nobody' ), true ) ? $settings['visibility'] : 'everyone',
			'roles'      => $roles,
			'search'     => (bool) $settings['search'],
			'sort'       => (bool) $settings['sort'],
			'per_page'   => max( 0, min( 500, (int) $settings['per_page'] ) ),
			'order'      => 'oldest' === $settings['order'] ? 'oldest' : 'newest',
			'max_rows'   => max( 1, min( self::MAX_INSERT, (int) $settings['max_rows'] ) ),
			'download'   => (bool) $settings['download'],
			'show_date'  => (bool) $settings['show_date'],
			'css_class'  => implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $settings['css_class'] ) ) ) ),
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Rows
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Add rows to a table (public API). Keys that are not columns of the table are ignored.
	 *
	 * @param int   $table_id Table ID.
	 * @param array $rows     List of column key => value.
	 * @param array $source   Optional { automation_id, log_id }.
	 * @param bool  $replace  Delete the existing rows first.
	 * @return int|WP_Error Number of rows added.
	 */
	public static function add_rows( $table_id, array $rows, array $source = array(), $replace = false ) {
		global $wpdb;
		$table = self::get( $table_id );
		if ( ! $table ) {
			return new WP_Error( 'glocalsaino_aat_no_table', __( 'The table does not exist.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		if ( ! $table['columns'] ) {
			return new WP_Error( 'glocalsaino_aat_no_columns', __( 'The table has no columns.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		if ( count( $rows ) > self::MAX_INSERT ) {
			$rows = array_slice( $rows, 0, self::MAX_INSERT );
		}
		if ( $replace ) {
			self::clear( $table_id );
		}

		$keys  = wp_list_pluck( $table['columns'], 'key' );
		$now   = current_time( 'mysql', true );
		$added = 0;
		foreach ( $rows as $row ) {
			$data = array();
			foreach ( $keys as $key ) {
				$value        = isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : '';
				$data[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_VALUE ) : substr( $value, 0, self::MAX_VALUE );
			}
			$added += (int) $wpdb->insert(
				GSAAT_DB::table( 'table_rows' ),
				array(
					'table_id'      => (int) $table_id,
					'data'          => wp_json_encode( $data ),
					'automation_id' => isset( $source['automation_id'] ) ? (int) $source['automation_id'] : 0,
					'log_id'        => isset( $source['log_id'] ) ? (int) $source['log_id'] : 0,
					'created_at'    => $now,
				)
			);
		}

		$wpdb->update( GSAAT_DB::table( 'tables' ), array( 'updated_at' => $now ), array( 'id' => (int) $table_id ) );

		/**
		 * Fires after rows were added to a table.
		 *
		 * @param int   $table_id Table ID.
		 * @param int   $added    Number of rows.
		 * @param array $source   { automation_id, log_id }.
		 */
		do_action( 'glocalsaino_aat_table_rows_added', (int) $table_id, $added, $source );

		return $added;
	}

	/**
	 * Rows of a table.
	 *
	 * @param int    $table_id Table ID.
	 * @param int    $limit    Max rows.
	 * @param int    $offset   Offset.
	 * @param string $order    newest|oldest.
	 * @return array[] { id, data, created_at }
	 */
	public static function rows( $table_id, $limit = 1000, $offset = 0, $order = 'newest' ) {
		global $wpdb;
		if ( 'oldest' === $order ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT id, data, created_at FROM %i WHERE table_id = %d ORDER BY id ASC LIMIT %d OFFSET %d', GSAAT_DB::table( 'table_rows' ), $table_id, $limit, $offset ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT id, data, created_at FROM %i WHERE table_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', GSAAT_DB::table( 'table_rows' ), $table_id, $limit, $offset ),
				ARRAY_A
			);
		}
		$clean = array();
		foreach ( (array) $rows as $row ) {
			$data    = json_decode( (string) $row['data'], true );
			$clean[] = array(
				'id'         => (int) $row['id'],
				'data'       => is_array( $data ) ? $data : array(),
				'created_at' => (string) $row['created_at'],
			);
		}
		return $clean;
	}

	public static function count_rows( $table_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE table_id = %d', GSAAT_DB::table( 'table_rows' ), $table_id ) );
	}

	public static function clear( $table_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE table_id = %d', GSAAT_DB::table( 'table_rows' ), $table_id ) );
	}

	public static function delete_rows( $table_id, array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return;
		}
		foreach ( $ids as $id ) {
			$wpdb->delete(
				GSAAT_DB::table( 'table_rows' ),
				array(
					'id'       => $id,
					'table_id' => (int) $table_id,
				)
			);
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Front end
	 * ---------------------------------------------------------------------
	 */

	public static function can_view( array $table ) {
		$settings = $table['settings'];
		switch ( $settings['visibility'] ) {
			case 'everyone':
				return true;
			case 'logged_in':
				return is_user_logged_in();
			case 'roles':
				$user = wp_get_current_user();
				return $user->exists() && ( array_intersect( (array) $user->roles, (array) $settings['roles'] ) || current_user_can( 'manage_options' ) );
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * [glocalsaino_aat_table id="3"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts  = shortcode_atts( array( 'id' => 0 ), $atts, self::SHORTCODE );
		$table = self::get( absint( $atts['id'] ) );
		if ( ! $table || ! self::can_view( $table ) ) {
			return '';
		}
		$settings = $table['settings'];
		$rows     = self::rows( $table['id'], $settings['max_rows'], 0, $settings['order'] );

		wp_enqueue_style( 'glocalsaino-aat-table', GSAAT_URL . 'assets/css/table.css', array(), GSAAT_VERSION );
		wp_enqueue_script( 'glocalsaino-aat-table', GSAAT_URL . 'assets/js/table.js', array(), GSAAT_VERSION, true );
		wp_localize_script(
			'glocalsaino-aat-table',
			'glocalsainoAatTable',
			array(
				'search'   => __( 'Search', 'glocalsaino-actions-automation-based-on-trigger' ),
				'previous' => __( 'Previous', 'glocalsaino-actions-automation-based-on-trigger' ),
				'next'     => __( 'Next', 'glocalsaino-actions-automation-based-on-trigger' ),
				/* translators: 1: first row, 2: last row, 3: total rows. */
				'showing'  => __( '%1$s–%2$s of %3$s', 'glocalsaino-actions-automation-based-on-trigger' ),
				'noRows'   => __( 'No matching rows.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sortBy'   => __( 'Sort by this column', 'glocalsaino-actions-automation-based-on-trigger' ),
			)
		);

		$html  = sprintf(
			'<div class="%1$s" data-glocalsaino-aat-table data-per-page="%2$d" data-search="%3$d" data-sort="%4$d">',
			esc_attr( trim( 'glocalsaino-aat-table-wrap ' . $settings['css_class'] ) ),
			(int) $settings['per_page'],
			$settings['search'] ? 1 : 0,
			$settings['sort'] ? 1 : 0
		);
		if ( $settings['download'] ) {
			$html .= sprintf(
				'<p class="glocalsaino-aat-table__download"><a href="%1$s">%2$s</a></p>',
				esc_url( add_query_arg( self::CSV_ARG, $table['id'], home_url( '/' ) ) ),
				esc_html__( 'Download as CSV', 'glocalsaino-actions-automation-based-on-trigger' )
			);
		}
		$html .= '<div class="glocalsaino-aat-table__scroll"><table class="glocalsaino-aat-table"><thead><tr>';
		foreach ( $table['columns'] as $column ) {
			$html .= sprintf( '<th scope="col" data-type="%1$s">%2$s</th>', esc_attr( $column['type'] ), esc_html( $column['label'] ) );
		}
		if ( $settings['show_date'] ) {
			$html .= '<th scope="col" data-type="date">' . esc_html__( 'Added', 'glocalsaino-actions-automation-based-on-trigger' ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( $table['columns'] as $column ) {
				$value = isset( $row['data'][ $column['key'] ] ) ? (string) $row['data'][ $column['key'] ] : '';
				$html .= self::cell( $value, $column['type'] );
			}
			if ( $settings['show_date'] ) {
				$html .= sprintf(
					'<td data-sort="%1$d">%2$s</td>',
					(int) strtotime( $row['created_at'] . ' UTC' ),
					esc_html( wp_date( get_option( 'date_format' ), strtotime( $row['created_at'] . ' UTC' ) ) )
				);
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table></div>';
		if ( ! $rows ) {
			$html .= '<p class="glocalsaino-aat-table__empty">' . esc_html__( 'There are no rows yet.', 'glocalsaino-actions-automation-based-on-trigger' ) . '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * One escaped cell, with a sort key for numbers and dates.
	 *
	 * @param string $value Value.
	 * @param string $type  Column type.
	 * @return string
	 */
	private static function cell( $value, $type ) {
		switch ( $type ) {
			case 'number':
				$number = GSAAT_Calculator::to_number( $value );
				return sprintf( '<td class="is-number" data-sort="%1$s">%2$s</td>', esc_attr( null === $number || '' === $value ? '' : (string) $number ), esc_html( $value ) );
			case 'date':
				$time = strtotime( str_replace( '/', '-', $value ) );
				return sprintf( '<td data-sort="%1$s">%2$s</td>', esc_attr( false === $time ? '' : (string) $time ), esc_html( $value ) );
			case 'link':
				$url = esc_url( $value, array( 'http', 'https' ) );
				return '' !== $url
					? sprintf( '<td><a href="%1$s" rel="nofollow noopener" target="_blank">%2$s</a></td>', $url, esc_html( $value ) )
					: '<td>' . esc_html( $value ) . '</td>';
			case 'email':
				return is_email( $value )
					? sprintf( '<td><a href="%1$s">%2$s</a></td>', esc_url( 'mailto:' . $value ), esc_html( $value ) )
					: '<td>' . esc_html( $value ) . '</td>';
		}
		return '<td>' . esc_html( $value ) . '</td>';
	}

	/**
	 * Public CSV download (only when the table allows it and the visitor can see it).
	 */
	public static function maybe_download() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public download of a table the visitor can already see.
		$id = isset( $_GET[ self::CSV_ARG ] ) ? absint( wp_unslash( $_GET[ self::CSV_ARG ] ) ) : 0;
		if ( ! $id ) {
			return;
		}
		$table = self::get( $id );
		if ( ! $table || ! $table['settings']['download'] || ! self::can_view( $table ) ) {
			status_header( 404 );
			exit;
		}
		self::send_csv( $table, $table['settings']['max_rows'] );
	}

	/**
	 * Stream a table as CSV and exit.
	 *
	 * @param array $table Table.
	 * @param int   $limit Max rows.
	 */
	public static function send_csv( array $table, $limit = self::MAX_INSERT ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $table['name'] ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming the download.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so spreadsheets detect the encoding.
		fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), wp_list_pluck( $table['columns'], 'label' ) ), ',', '"', '' );
		foreach ( self::rows( $table['id'], $limit, 0, $table['settings']['order'] ) as $row ) {
			$line = array();
			foreach ( $table['columns'] as $column ) {
				$line[] = self::csv_safe( isset( $row['data'][ $column['key'] ] ) ? (string) $row['data'][ $column['key'] ] : '' );
			}
			fputcsv( $out, $line, ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming the download.
		exit;
	}

	/**
	 * Neutralize spreadsheet formulas (CSV injection): values starting with = + - @ get a leading quote.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		return '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) && ! is_numeric( $value ) ? "'" . $value : $value;
	}
}
