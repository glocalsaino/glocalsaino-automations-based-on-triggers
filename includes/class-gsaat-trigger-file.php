<?php
/**
 * Trigger: a CSV or Excel (.xlsx) file is uploaded — from the back end or from the
 * front end with [glocalsaino_aat_upload id="…"], whose form posts to a public REST route
 * (logged-in visitors are identified through WordPress's REST cookie authentication). The file is read and discarded
 * (it is never stored). The automation runs once for the whole file (its rows are
 * passed to the "Add rows to a table" action) or once per row.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Trigger_File {

	const SHORTCODE  = 'glocalsaino_aat_upload';
	const FILE_FIELD = 'glocalsaino_aat_file';
	const HONEYPOT   = 'glocalsaino_aat_upload_hp';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_post_glocalsaino_aat_run_file', array( __CLASS__, 'handle_admin' ) );
		add_action( 'wp_ajax_glocalsaino_aat_detect_columns', array( __CLASS__, 'ajax_detect_columns' ) );
	}

	public static function config( array $config ) {
		return wp_parse_args(
			$config,
			array(
				'columns'     => array(),
				'mode'        => 'file',
				'max_rows'    => 5000,
				'max_size'    => 5,
				'access'      => 'logged_in',
				'roles'       => array(),
				'label'       => __( 'Choose a CSV or Excel file', 'glocalsaino-automations-based-on-triggers' ),
				'button_text' => __( 'Upload', 'glocalsaino-automations-based-on-triggers' ),
				'message'     => __( 'Thank you, the file was received.', 'glocalsaino-automations-based-on-triggers' ),
				'rate_limit'  => 5,
			)
		);
	}

	public static function access_options() {
		return array(
			'logged_in' => __( 'Logged-in users', 'glocalsaino-automations-based-on-triggers' ),
			'roles'     => __( 'Only users with these roles', 'glocalsaino-automations-based-on-triggers' ),
			'anyone'    => __( 'Anyone, including visitors who are not logged in', 'glocalsaino-automations-based-on-triggers' ),
			'nobody'    => __( 'Nobody on the website (only from the back end)', 'glocalsaino-automations-based-on-triggers' ),
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Running the automation with a file
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Run an automation with a parsed file.
	 *
	 * @param array  $automation Automation.
	 * @param array  $parsed     GSAAT_File_Reader::read() result.
	 * @param string $file_name  Original file name.
	 * @param array  $context    Event context.
	 * @return array { rows, runs, failed }
	 */
	public static function run( array $automation, array $parsed, $file_name, array $context ) {
		$config  = self::config( $automation['trigger_config'] );
		$rows    = $parsed['rows'];
		$columns = $parsed['columns'];
		$extra   = array(
			'_file_name' => $file_name,
			'_row_count' => (string) count( $rows ),
			'_columns'   => implode( ', ', $columns ),
		);
		$summary = array(
			'rows'   => count( $rows ),
			'runs'   => 0,
			'failed' => 0,
		);

		if ( 'row' === $config['mode'] ) {
			foreach ( $rows as $index => $row ) {
				$status = GSAAT_Engine::process(
					$automation,
					$row,
					/* translators: 1: row number, 2: file name. */
					sprintf( __( 'Row %1$d of the file "%2$s"', 'glocalsaino-automations-based-on-triggers' ), $index + 1, $file_name ),
					$extra + array( '_row_number' => (string) ( $index + 1 ) ),
					$context
				);
				self::count( $summary, $status );
			}
			return $summary;
		}

		$status = GSAAT_Engine::process(
			$automation,
			array(),
			/* translators: 1: file name, 2: number of rows. */
			sprintf( _n( 'File "%1$s" uploaded (%2$d row)', 'File "%1$s" uploaded (%2$d rows)', count( $rows ), 'glocalsaino-automations-based-on-triggers' ), $file_name, count( $rows ) ),
			$extra,
			$context + array(
				'rows'    => $rows,
				'columns' => $columns,
			)
		);
		self::count( $summary, $status );
		return $summary;
	}

	private static function count( array &$summary, $status ) {
		if ( null === $status || 'skipped' === $status ) {
			return;
		}
		++$summary['runs'];
		if ( 'success' !== $status ) {
			++$summary['failed'];
		}
	}

	/**
	 * Check an uploaded file and read it.
	 *
	 * @param array|null $file     One entry of the uploaded files ($_FILES format).
	 * @param int        $max_mb   Maximum size in MB.
	 * @param int        $max_rows Maximum rows.
	 * @return array|WP_Error { parsed, name } — error codes glocalsaino_aat_file_{nofile|toobig|badtype|unreadable|empty|noexcel}.
	 */
	public static function receive( $file, $max_mb, $max_rows ) {
		if ( ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'], $file['name'], $file['size'] ) || is_array( $file['name'] ) ) {
			return new WP_Error( 'glocalsaino_aat_file_nofile', __( 'Choose a file to upload.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$max_bytes = self::max_bytes( $max_mb );
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
				/* translators: %s: maximum size. */
				? new WP_Error( 'glocalsaino_aat_file_toobig', sprintf( __( 'The file is too big (maximum %s).', 'glocalsaino-automations-based-on-triggers' ), size_format( $max_bytes ) ) )
				: new WP_Error( 'glocalsaino_aat_file_unreadable', __( 'The file could not be uploaded. Please try again.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$tmp_name = (string) $file['tmp_name'];
		if ( ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'glocalsaino_aat_file_unreadable', __( 'The file could not be uploaded. Please try again.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		if ( (int) $file['size'] > $max_bytes ) {
			/* translators: %s: maximum size. */
			return new WP_Error( 'glocalsaino_aat_file_toobig', sprintf( __( 'The file is too big (maximum %s).', 'glocalsaino-automations-based-on-triggers' ), size_format( $max_bytes ) ) );
		}

		$name      = sanitize_file_name( (string) $file['name'] );
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$head      = (string) file_get_contents( $tmp_name, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
		if ( 'xlsx' === $extension && 0 !== strpos( $head, "PK\x03\x04" ) ) {
			return new WP_Error( 'glocalsaino_aat_file_badtype', __( 'This does not look like an Excel (.xlsx) file.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$parsed = GSAAT_File_Reader::read( $tmp_name, $name, (int) $max_rows );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		return array(
			'parsed' => $parsed,
			'name'   => $name,
		);
	}

	private static function max_bytes( $max_mb ) {
		return min( (int) wp_max_upload_size(), max( 1, (int) $max_mb ) * MB_IN_BYTES );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Front end: [glocalsaino_aat_upload id="…"]
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Whether the current visitor may upload to this automation.
	 *
	 * @param array $config Trigger settings.
	 * @return bool
	 */
	public static function visitor_allowed( array $config ) {
		switch ( $config['access'] ) {
			case 'anyone':
				return true;
			case 'logged_in':
				return is_user_logged_in();
			case 'roles':
				$user = wp_get_current_user();
				return $user->exists() && ( array_intersect( (array) $user->roles, (array) $config['roles'] ) || current_user_can( 'manage_options' ) );
		}
		return false;
	}

	/**
	 * Messages shown after an upload from the website, by result code.
	 *
	 * @param array $config Trigger settings.
	 * @return array code => [ success|error, message ]
	 */
	private static function result_messages( array $config ) {
		return array(
			'ok'          => array( 'success', $config['message'] ),
			/* translators: %d: maximum rows. */
			'truncated'   => array( 'success', $config['message'] . ' ' . sprintf( __( 'Only the first %d rows were read.', 'glocalsaino-automations-based-on-triggers' ), (int) $config['max_rows'] ) ),
			'denied'      => array( 'error', __( 'You are not allowed to upload files here.', 'glocalsaino-automations-based-on-triggers' ) ),
			'rate'        => array( 'error', __( 'Too many uploads from this connection. Please try again later.', 'glocalsaino-automations-based-on-triggers' ) ),
			'unavailable' => array( 'error', __( 'Uploads are not available right now.', 'glocalsaino-automations-based-on-triggers' ) ),
			'nofile'      => array( 'error', __( 'Choose a file to upload.', 'glocalsaino-automations-based-on-triggers' ) ),
			/* translators: %s: maximum size. */
			'toobig'      => array( 'error', sprintf( __( 'The file is too big (maximum %s).', 'glocalsaino-automations-based-on-triggers' ), size_format( self::max_bytes( $config['max_size'] ) ) ) ),
			'badtype'     => array( 'error', __( 'Only CSV and Excel (.xlsx) files are accepted. Save .xls files as .xlsx or .csv first.', 'glocalsaino-automations-based-on-triggers' ) ),
			'unreadable'  => array( 'error', __( 'The file could not be read. Please check it and try again.', 'glocalsaino-automations-based-on-triggers' ) ),
			'empty'       => array( 'error', __( 'The file is empty.', 'glocalsaino-automations-based-on-triggers' ) ),
			'noexcel'     => array( 'error', __( 'This website cannot read Excel files. Save the file as CSV and upload it again.', 'glocalsaino-automations-based-on-triggers' ) ),
		);
	}

	public static function shortcode( $atts ) {
		$atts       = shortcode_atts( array( 'id' => 0 ), $atts, self::SHORTCODE );
		$automation = GSAAT_Automations::get( absint( $atts['id'] ) );
		if ( ! $automation || 'file' !== $automation['trigger_type'] ) {
			return '';
		}
		$config = self::config( $automation['trigger_config'] );
		wp_enqueue_style( 'glocalsaino-aat-front', GSAAT_URL . 'assets/css/front.css', array(), GSAAT_VERSION );

		// Result messages, hidden until the page is reloaded with #glocalsaino-aat-upload-ID-CODE (CSS :target).
		$html = '';
		foreach ( self::result_messages( $config ) as $code => $result ) {
			$html .= sprintf(
				'<p class="glocalsaino-aat-target-message glocalsaino-aat-upload__result glocalsaino-aat-upload__result--%1$s" id="%2$s" role="status">%3$s</p>',
				esc_attr( $result[0] ),
				esc_attr( 'glocalsaino-aat-upload-' . $automation['id'] . '-' . $code ),
				esc_html( $result[1] )
			);
		}

		if ( ! self::visitor_allowed( $config ) ) {
			if ( 'logged_in' === $config['access'] || 'roles' === $config['access'] ) {
				$html .= '<p class="glocalsaino-aat-upload__login">' . esc_html__( 'Log in to upload a file.', 'glocalsaino-automations-based-on-triggers' ) . '</p>';
			}
			return $html;
		}

		$html .= '<form method="post" enctype="multipart/form-data" class="glocalsaino-aat-upload" action="' . esc_url( rest_url( GSAAT_Automations::REST_NAMESPACE . '/upload/' . $automation['webhook_key'] ) ) . '">';
		if ( is_user_logged_in() ) {
			// WordPress's REST nonce: identifies the logged-in visitor (and blocks forged cross-site posts on their behalf).
			$html .= wp_nonce_field( 'wp_rest', '_wpnonce', false, false );
		}
		$html .= '<p class="glocalsaino-aat-upload__hp" aria-hidden="true"><input type="text" name="' . esc_attr( self::HONEYPOT ) . '" tabindex="-1" autocomplete="off"></p>';
		$html .= '<p><label>' . esc_html( $config['label'] ) . '<br><input type="file" name="' . esc_attr( self::FILE_FIELD ) . '" accept=".csv,.xlsx,.txt,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label></p>';
		$html .= '<p><button type="submit" class="wp-element-button">' . esc_html( $config['button_text'] ) . '</button></p>';
		$html .= '</form>';
		return $html;
	}

	public static function register_routes() {
		register_rest_route(
			GSAAT_Automations::REST_NAMESPACE,
			'/upload/(?P<key>[A-Za-z0-9]{20,64})',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_upload' ),
				// Public route; who may upload is decided by the automation's access setting below. Logged-in
				// visitors are identified only through the wp_rest nonce (REST cookie authentication).
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * File sent from the [glocalsaino_aat_upload] form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_upload( WP_REST_Request $request ) {
		$url_params = $request->get_url_params();
		$automation = GSAAT_Automations::get_by_webhook_key( isset( $url_params['key'] ) ? (string) $url_params['key'] : '' );
		$back       = wp_validate_redirect( (string) $request->get_header( 'referer' ), home_url( '/' ) );
		if ( ! $automation || 'file' !== $automation['trigger_type'] ) {
			return GSAAT_Redirect::rest_redirect( $back );
		}
		$config  = self::config( $automation['trigger_config'] );
		$body    = (array) $request->get_body_params();
		$user_id = get_current_user_id(); // 0 unless the wp_rest nonce was valid.

		if ( ! self::visitor_allowed( $config ) ) {
			return self::result( $automation, $back, 'denied' );
		}
		if ( ! empty( $body[ self::HONEYPOT ] ) ) {
			return self::result( $automation, $back, 'ok' ); // Pretend it worked, so bots do not learn about the trap.
		}
		if ( ! $user_id && self::rate_limited( $automation['id'], (int) $config['rate_limit'] ) ) {
			return self::result( $automation, $back, 'rate' );
		}
		if ( 'active' !== GSAAT_Automations::availability( $automation ) ) {
			return self::result( $automation, $back, 'unavailable' );
		}
		if ( ! $user_id && ! GSAAT_Automations::public_run_allowed( $automation ) ) {
			return self::result( $automation, $back, 'rate' );
		}

		$files  = (array) $request->get_file_params();
		$upload = self::receive( isset( $files[ self::FILE_FIELD ] ) ? $files[ self::FILE_FIELD ] : null, $config['max_size'], $config['max_rows'] );
		if ( is_wp_error( $upload ) ) {
			return self::result( $automation, $back, str_replace( 'glocalsaino_aat_file_', '', $upload->get_error_code() ) );
		}

		self::run(
			$automation,
			$upload['parsed'],
			$upload['name'],
			array(
				'user_id' => $user_id,
				'browser' => true,
			)
		);

		$target = GSAAT_Redirect::take();
		if ( '' !== $target ) {
			return GSAAT_Redirect::rest_redirect( $target );
		}
		return self::result( $automation, $back, $upload['parsed']['truncated'] ? 'truncated' : 'ok' );
	}

	/**
	 * Back to the page with the form, showing the message of a result code.
	 *
	 * @param array  $automation Automation.
	 * @param string $back       Page to return to.
	 * @param string $code       Result code (see result_messages()).
	 * @return WP_REST_Response
	 */
	private static function result( array $automation, $back, $code ) {
		$code = preg_replace( '/[^a-z]/', '', (string) $code );
		return GSAAT_Redirect::rest_redirect( strtok( $back, '#' ) . '#glocalsaino-aat-upload-' . $automation['id'] . '-' . ( '' !== $code ? $code : 'unreadable' ) );
	}

	private static function rate_limited( $automation_id, $limit ) {
		if ( $limit <= 0 ) {
			return false;
		}
		$key   = 'glocalsaino_aat_ur_' . md5( GSAAT_Webhook_Security::client_ip() . '|' . $automation_id );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}
	/*
	 * ---------------------------------------------------------------------
	 * Back end
	 * ---------------------------------------------------------------------
	 */

	/**
	 * "Run with a file" form on the automation screen.
	 */
	public static function handle_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'glocalsaino-automations-based-on-triggers' ), 403 );
		}
		check_admin_referer( 'glocalsaino_aat_run_file' );

		$id         = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$automation = GSAAT_Automations::get( $id );
		if ( ! $automation || 'file' !== $automation['trigger_type'] ) {
			wp_die( esc_html__( 'Automation not found.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$config = self::config( $automation['trigger_config'] );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability checked above; every part of the upload is validated by receive().
		$file   = isset( $_FILES[ self::FILE_FIELD ] ) ? $_FILES[ self::FILE_FIELD ] : null;
		$upload = self::receive( $file, $config['max_size'], $config['max_rows'] );

		if ( is_wp_error( $upload ) ) {
			$notice = array( 'error', $upload->get_error_message() );
		} elseif ( 'active' !== GSAAT_Automations::availability( $automation ) ) {
			$notice = array( 'warning', __( 'The automation is not active (or is outside its dates / run limit), so the file was not processed.', 'glocalsaino-automations-based-on-triggers' ) );
		} else {
			$summary = self::run( $automation, $upload['parsed'], $upload['name'], array( 'user_id' => get_current_user_id() ) );
			$text    = sprintf(
				/* translators: 1: number of rows, 2: number of executions, 3: failed executions. */
				__( 'File processed: %1$d rows read, %2$d executions, %3$d with errors. See the details in Logs.', 'glocalsaino-automations-based-on-triggers' ),
				$summary['rows'],
				$summary['runs'],
				$summary['failed']
			);
			if ( $upload['parsed']['truncated'] ) {
				/* translators: %d: maximum rows. */
				$text .= ' ' . sprintf( __( 'Only the first %d rows were read.', 'glocalsaino-automations-based-on-triggers' ), (int) $config['max_rows'] );
			}
			$notice = array( $summary['failed'] ? 'warning' : 'success', $text );
		}

		set_transient( 'glocalsaino_aat_file_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                => 'glocalsaino-aat-edit',
					'id'                  => $automation['id'],
					'glocalsaino_aat_msg' => 'file_processed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX: read the header of a file to fill in columns (trigger settings, table editor).
	 */
	public static function ajax_detect_columns() {
		check_ajax_referer( 'glocalsaino_aat_detect_columns', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'glocalsaino-automations-based-on-triggers' ) ), 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability checked above; every part of the upload is validated by receive().
		$file   = isset( $_FILES['file'] ) ? $_FILES['file'] : null;
		$upload = self::receive( $file, 50, 1 );
		if ( is_wp_error( $upload ) ) {
			wp_send_json_error( array( 'message' => $upload->get_error_message() ) );
		}
		$columns = array();
		foreach ( $upload['parsed']['columns'] as $key => $label ) {
			$columns[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}
		wp_send_json_success( array( 'columns' => $columns ) );
	}

	public static function sanitize_config( array $post, array &$notices ) {
		$text = static function ( $key, $default = '' ) use ( $post ) {
			return isset( $post[ $key ] ) && is_string( $post[ $key ] ) ? trim( sanitize_text_field( $post[ $key ] ) ) : $default;
		};

		$columns = array();
		foreach ( preg_split( '/[,;\n]+/', isset( $post['file_columns'] ) ? (string) $post['file_columns'] : '' ) as $column ) {
			$key = GSAAT_File_Reader::key_from_label( $column );
			if ( '' !== $key && ! in_array( $key, $columns, true ) ) {
				$columns[] = $key;
			}
		}
		$access = $text( 'file_access', 'logged_in' );
		$roles  = isset( $post['file_roles'] ) && is_array( $post['file_roles'] ) ? array_map( 'sanitize_key', $post['file_roles'] ) : array();

		return array(
			'columns'     => $columns,
			'mode'        => 'row' === $text( 'file_mode' ) ? 'row' : 'file',
			'max_rows'    => max( 1, min( GSAAT_Tables::MAX_INSERT, absint( $text( 'file_max_rows', '5000' ) ) ) ),
			'max_size'    => max( 1, min( 50, absint( $text( 'file_max_size', '5' ) ) ) ),
			'access'      => array_key_exists( $access, self::access_options() ) ? $access : 'logged_in',
			'roles'       => array_values( array_intersect( $roles, array_keys( wp_roles()->get_names() ) ) ),
			'label'       => $text( 'file_label', __( 'Choose a CSV or Excel file', 'glocalsaino-automations-based-on-triggers' ) ),
			'button_text' => $text( 'file_button_text', __( 'Upload', 'glocalsaino-automations-based-on-triggers' ) ),
			'message'     => $text( 'file_message', __( 'Thank you, the file was received.', 'glocalsaino-automations-based-on-triggers' ) ),
			'rate_limit'  => min( 1000, absint( $text( 'file_rate_limit', '5' ) ) ),
		);
	}

	public static function describe( array $automation ) {
		$config = self::config( $automation['trigger_config'] );
		return 'row' === $config['mode']
			? __( 'CSV / Excel file uploaded — once per row', 'glocalsaino-automations-based-on-triggers' )
			: __( 'CSV / Excel file uploaded — once per file', 'glocalsaino-automations-based-on-triggers' );
	}
}
