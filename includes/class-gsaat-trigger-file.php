<?php
/**
 * Trigger: a CSV or Excel (.xlsx) file is uploaded — from the back end or from the
 * front end with [glocalsaino_aat_upload id="…"]. The file is read and discarded
 * (it is never stored). The automation runs once for the whole file (its rows are
 * passed to the "Add rows to a table" action) or once per row.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Trigger_File {

	const SHORTCODE   = 'glocalsaino_aat_upload';
	const POST_ARG    = 'glocalsaino_aat_upload';
	const FILE_FIELD  = 'glocalsaino_aat_file';
	const NONCE_FIELD = 'glocalsaino_aat_upload_nonce';
	const RESULT_ARG  = 'glocalsaino_aat_upload_result';
	const HONEYPOT    = 'glocalsaino_aat_upload_hp';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_frontend' ), 1 );
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
	 * Check the uploaded file and read it.
	 *
	 * @param string $field    $_FILES key.
	 * @param int    $max_mb   Maximum size in MB.
	 * @param int    $max_rows Maximum rows.
	 * @return array|WP_Error { parsed, name }
	 */
	public static function receive( $field, $max_mb, $max_rows ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked by the callers; every part of the upload is validated below.
		$file = isset( $_FILES[ $field ] ) && is_array( $_FILES[ $field ] ) ? $_FILES[ $field ] : null;

		if ( ! $file || ! isset( $file['error'], $file['tmp_name'], $file['name'], $file['size'] ) || is_array( $file['name'] ) ) {
			return new WP_Error( 'glocalsaino_aat_no_file', __( 'Choose a file to upload.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error(
				'glocalsaino_aat_upload_error',
				in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
					? __( 'The file is too big.', 'glocalsaino-automations-based-on-triggers' )
					: __( 'The file could not be uploaded. Please try again.', 'glocalsaino-automations-based-on-triggers' )
			);
		}
		$tmp_name = (string) $file['tmp_name'];
		if ( ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'glocalsaino_aat_upload_error', __( 'The file could not be uploaded. Please try again.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$max_bytes = min( (int) wp_max_upload_size(), max( 1, (int) $max_mb ) * MB_IN_BYTES );
		if ( (int) $file['size'] > $max_bytes ) {
			/* translators: %s: maximum size. */
			return new WP_Error( 'glocalsaino_aat_too_big', sprintf( __( 'The file is too big (maximum %s).', 'glocalsaino-automations-based-on-triggers' ), size_format( $max_bytes ) ) );
		}

		$name      = sanitize_file_name( wp_unslash( (string) $file['name'] ) );
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$head      = (string) file_get_contents( $tmp_name, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
		if ( 'xlsx' === $extension && 0 !== strpos( $head, "PK\x03\x04" ) ) {
			return new WP_Error( 'glocalsaino_aat_bad_file', __( 'This does not look like an Excel (.xlsx) file.', 'glocalsaino-automations-based-on-triggers' ) );
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

	public static function shortcode( $atts ) {
		$atts       = shortcode_atts( array( 'id' => 0 ), $atts, self::SHORTCODE );
		$automation = GSAAT_Automations::get( absint( $atts['id'] ) );
		if ( ! $automation || 'file' !== $automation['trigger_type'] ) {
			return '';
		}
		$config = self::config( $automation['trigger_config'] );
		$html   = '';
		wp_enqueue_style( 'glocalsaino-aat-upload', GSAAT_URL . 'assets/css/upload.css', array(), GSAAT_VERSION );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only result key.
		$result_key = isset( $_GET[ self::RESULT_ARG ] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) ) : '';
		if ( '' !== $result_key ) {
			$result = get_transient( 'glocalsaino_aat_upr_' . $result_key );
			if ( is_array( $result ) && (int) $result['id'] === $automation['id'] ) {
				$html .= sprintf(
					'<p class="glocalsaino-aat-upload__result glocalsaino-aat-upload__result--%1$s" role="status">%2$s</p>',
					esc_attr( 'error' === $result['status'] ? 'error' : 'success' ),
					esc_html( $result['message'] )
				);
			}
		}

		if ( ! self::visitor_allowed( $config ) ) {
			if ( 'logged_in' === $config['access'] || 'roles' === $config['access'] ) {
				$html .= '<p class="glocalsaino-aat-upload__login">' . esc_html__( 'Log in to upload a file.', 'glocalsaino-automations-based-on-triggers' ) . '</p>';
			}
			return $html;
		}

		$html .= '<form method="post" enctype="multipart/form-data" class="glocalsaino-aat-upload">';
		$html .= '<input type="hidden" name="' . esc_attr( self::POST_ARG ) . '" value="' . esc_attr( $automation['webhook_key'] ) . '">';
		if ( is_user_logged_in() ) {
			$html .= wp_nonce_field( 'glocalsaino_aat_upload_' . $automation['id'], self::NONCE_FIELD, false, false );
		}
		$html .= '<p class="glocalsaino-aat-upload__hp" aria-hidden="true"><input type="text" name="' . esc_attr( self::HONEYPOT ) . '" tabindex="-1" autocomplete="off"></p>';
		$html .= '<p><label>' . esc_html( $config['label'] ) . '<br><input type="file" name="' . esc_attr( self::FILE_FIELD ) . '" accept=".csv,.xlsx,.txt,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label></p>';
		$html .= '<p><button type="submit" class="wp-element-button">' . esc_html( $config['button_text'] ) . '</button></p>';
		$html .= '</form>';
		return $html;
	}

	public static function handle_frontend() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below for logged-in users.
		if ( 'post' !== $method || ! isset( $_POST[ self::POST_ARG ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below for logged-in users.
		$key        = preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST[ self::POST_ARG ] ) ) );
		$automation = '' !== $key ? GSAAT_Automations::get_by_webhook_key( $key ) : null;
		if ( ! $automation || 'file' !== $automation['trigger_type'] ) {
			return;
		}
		$config = self::config( $automation['trigger_config'] );

		$user_id = 0;
		if ( is_user_logged_in() ) {
			$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
			if ( wp_verify_nonce( $nonce, 'glocalsaino_aat_upload_' . $automation['id'] ) ) {
				$user_id = get_current_user_id();
			} elseif ( 'anyone' !== $config['access'] ) {
				self::finish( $automation, 'error', __( 'The form expired. Reload the page and try again.', 'glocalsaino-automations-based-on-triggers' ) );
			}
		}

		if ( ! self::visitor_allowed( $config ) || ( 'anyone' !== $config['access'] && ! $user_id ) ) {
			self::finish( $automation, 'error', __( 'You are not allowed to upload files here.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Honeypot of the public form.
		if ( ! empty( $_POST[ self::HONEYPOT ] ) ) {
			self::finish( $automation, 'success', $config['message'] );
		}
		if ( ! $user_id && self::rate_limited( $automation['id'], (int) $config['rate_limit'] ) ) {
			self::finish( $automation, 'error', __( 'Too many uploads from this connection. Please try again later.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		if ( 'active' !== GSAAT_Automations::availability( $automation ) ) {
			self::finish( $automation, 'error', __( 'Uploads are not available right now.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$upload = self::receive( self::FILE_FIELD, $config['max_size'], $config['max_rows'] );
		if ( is_wp_error( $upload ) ) {
			self::finish( $automation, 'error', $upload->get_error_message() );
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

		$message = $config['message'];
		if ( $upload['parsed']['truncated'] ) {
			/* translators: %d: maximum rows. */
			$message .= ' ' . sprintf( __( 'Only the first %d rows were read.', 'glocalsaino-automations-based-on-triggers' ), (int) $config['max_rows'] );
		}
		self::finish( $automation, 'success', $message );
	}

	/**
	 * Redirect back (or to a "Redirect the user" target) with the result, and exit.
	 *
	 * @param array  $automation Automation.
	 * @param string $status     success|error.
	 * @param string $message    Message for the visitor.
	 */
	private static function finish( array $automation, $status, $message ) {
		$target = GSAAT_Redirect::take();
		nocache_headers();
		if ( '' !== $target ) {
			wp_redirect( esc_url_raw( $target ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Validated by the Redirect action.
			exit;
		}
		$key = wp_generate_password( 16, false, false );
		set_transient(
			'glocalsaino_aat_upr_' . $key,
			array(
				'id'      => $automation['id'],
				'status'  => $status,
				'message' => $message,
			),
			10 * MINUTE_IN_SECONDS
		);
		$back = wp_validate_redirect( (string) wp_get_raw_referer(), home_url( '/' ) );
		wp_safe_redirect( add_query_arg( self::RESULT_ARG, $key, $back ) );
		exit;
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
		$upload = self::receive( self::FILE_FIELD, $config['max_size'], $config['max_rows'] );

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
		$upload = self::receive( 'file', 50, 1 );
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
