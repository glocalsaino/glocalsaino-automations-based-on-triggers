<?php
/**
 * Admin screens and form handlers.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Admin {

	const CAP          = 'manage_options';
	const PAGE_LIST    = 'glocalsaino-aat';
	const PAGE_EDIT    = 'glocalsaino-aat-edit';
	const PAGE_LOGS    = 'glocalsaino-aat-logs';
	const PAGE_WEBHOOK = 'glocalsaino-aat-webhook-logs';
	const PAGE_SETTING = 'glocalsaino-aat-settings';
	const MESSAGE_ARG  = 'glocalsaino_aat_msg';

	/**
	 * Hook suffixes of the plugin pages.
	 *
	 * @var array
	 */
	private static $hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_glocalsaino_aat_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_glocalsaino_aat_row', array( __CLASS__, 'handle_row_action' ) );
		add_action( 'admin_post_glocalsaino_aat_clear_logs', array( __CLASS__, 'handle_clear_logs' ) );
		add_action( 'wp_ajax_glocalsaino_aat_search_users', array( __CLASS__, 'ajax_search_users' ) );
		add_filter( 'removable_query_args', array( __CLASS__, 'removable_query_args' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GSAAT_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	public static function menu() {
		self::$hooks['list'] = add_menu_page(
			__( 'Automations', 'glocalsaino-actions-automation-based-on-trigger' ),
			__( 'Automations', 'glocalsaino-actions-automation-based-on-trigger' ),
			self::CAP,
			self::PAGE_LIST,
			array( __CLASS__, 'render_list' ),
			'dashicons-randomize',
			58
		);
		self::$hooks['list'] = add_submenu_page( self::PAGE_LIST, __( 'Automations', 'glocalsaino-actions-automation-based-on-trigger' ), __( 'Automations', 'glocalsaino-actions-automation-based-on-trigger' ), self::CAP, self::PAGE_LIST, array( __CLASS__, 'render_list' ) );
		self::$hooks['edit'] = add_submenu_page( self::PAGE_LIST, __( 'New Automation', 'glocalsaino-actions-automation-based-on-trigger' ), __( 'New Automation', 'glocalsaino-actions-automation-based-on-trigger' ), self::CAP, self::PAGE_EDIT, array( __CLASS__, 'render_edit' ) );
		self::$hooks['logs'] = add_submenu_page( self::PAGE_LIST, __( 'Logs', 'glocalsaino-actions-automation-based-on-trigger' ), __( 'Logs', 'glocalsaino-actions-automation-based-on-trigger' ), self::CAP, self::PAGE_LOGS, array( __CLASS__, 'render_logs' ) );
		self::$hooks['webhook'] = add_submenu_page( self::PAGE_LIST, __( 'Webhook Logs', 'glocalsaino-actions-automation-based-on-trigger' ), __( 'Webhook Logs', 'glocalsaino-actions-automation-based-on-trigger' ), self::CAP, self::PAGE_WEBHOOK, array( __CLASS__, 'render_webhook_logs' ) );
		self::$hooks['settings'] = add_submenu_page( self::PAGE_LIST, __( 'Settings', 'glocalsaino-actions-automation-based-on-trigger' ), __( 'Settings', 'glocalsaino-actions-automation-based-on-trigger' ), self::CAP, self::PAGE_SETTING, array( __CLASS__, 'render_settings' ) );

		add_action( 'load-' . self::$hooks['list'], array( __CLASS__, 'process_automations_bulk' ) );
		add_action( 'load-' . self::$hooks['logs'], array( __CLASS__, 'process_logs_bulk' ) );
		add_action( 'load-' . self::$hooks['webhook'], array( __CLASS__, 'process_webhook_logs_bulk' ) );
	}

	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, self::$hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'glocalsaino-aat-admin', GSAAT_URL . 'assets/css/admin.css', array(), GSAAT_VERSION );
		wp_enqueue_script( 'glocalsaino-aat-admin', GSAAT_URL . 'assets/js/admin.js', array(), GSAAT_VERSION, true );

		if ( self::$hooks['edit'] !== $hook ) {
			return;
		}

		wp_enqueue_script( 'glocalsaino-aat-editor', GSAAT_URL . 'assets/js/admin-editor.js', array(), GSAAT_VERSION, true );
		wp_add_inline_script(
			'glocalsaino-aat-editor',
			'window.glocalsainoAatEditor = ' . wp_json_encode( self::editor_data() ) . ';',
			'before'
		);
	}

	public static function removable_query_args( $args ) {
		$args[] = self::MESSAGE_ARG;
		return $args;
	}

	public static function plugin_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::url( self::PAGE_LIST ) ), esc_html__( 'Automations', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( self::url( self::PAGE_SETTING ) ), esc_html__( 'Settings', 'glocalsaino-actions-automation-based-on-trigger' ) )
		);
		return $links;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Pages
	 * ---------------------------------------------------------------------
	 */

	public static function render_list() {
		require_once GSAAT_PATH . 'includes/admin/class-gsaat-automations-table.php';
		$table = new GSAAT_Automations_Table();
		$table->prepare_items();
		include GSAAT_PATH . 'includes/admin/views/automations.php';
	}

	public static function render_edit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$id         = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$automation = $id ? GSAAT_Automations::get( $id ) : self::blank_automation();
		if ( ! $automation ) {
			wp_die( esc_html__( 'Automation not found.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		$cf7_forms = GSAAT_Trigger_CF7::forms();
		include GSAAT_PATH . 'includes/admin/views/edit.php';
	}

	public static function render_logs() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$view_id = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0;
		if ( $view_id ) {
			$log = GSAAT_Logs::get( $view_id );
			if ( ! $log ) {
				wp_die( esc_html__( 'Log entry not found.', 'glocalsaino-actions-automation-based-on-trigger' ) );
			}
			include GSAAT_PATH . 'includes/admin/views/log-detail.php';
			return;
		}
		require_once GSAAT_PATH . 'includes/admin/class-gsaat-logs-table.php';
		$table = new GSAAT_Logs_Table();
		$table->prepare_items();
		include GSAAT_PATH . 'includes/admin/views/logs.php';
	}

	public static function render_webhook_logs() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$view_id = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0;
		if ( $view_id ) {
			$log = GSAAT_Logs::get_webhook( $view_id );
			if ( ! $log ) {
				wp_die( esc_html__( 'Log entry not found.', 'glocalsaino-actions-automation-based-on-trigger' ) );
			}
			include GSAAT_PATH . 'includes/admin/views/webhook-log-detail.php';
			return;
		}
		require_once GSAAT_PATH . 'includes/admin/class-gsaat-webhook-logs-table.php';
		$table = new GSAAT_Webhook_Logs_Table();
		$table->prepare_items();
		include GSAAT_PATH . 'includes/admin/views/webhook-logs.php';
	}

	public static function render_settings() {
		include GSAAT_PATH . 'includes/admin/views/settings.php';
	}

	/*
	 * ---------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------------
	 */

	public static function register_settings() {
		register_setting(
			'glocalsaino_aat_settings_group',
			GSAAT_Plugin::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => GSAAT_Plugin::default_settings(),
			)
		);
	}

	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'logs_retention_days'         => isset( $input['logs_retention_days'] ) ? min( 3650, absint( $input['logs_retention_days'] ) ) : 30,
			'webhook_logs_retention_days' => isset( $input['webhook_logs_retention_days'] ) ? min( 3650, absint( $input['webhook_logs_retention_days'] ) ) : 30,
			'delete_data_on_uninstall'    => empty( $input['delete_data_on_uninstall'] ) ? 0 : 1,
			'ip_source'                   => ( isset( $input['ip_source'] ) && array_key_exists( $input['ip_source'], GSAAT_Webhook_Security::ip_sources() ) ) ? $input['ip_source'] : 'remote_addr',
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Handlers
	 * ---------------------------------------------------------------------
	 */

	public static function handle_save() {
		self::check_capability();
		check_admin_referer( 'glocalsaino_aat_save' );

		$id       = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$existing = $id ? GSAAT_Automations::get( $id ) : null;
		if ( $id && ! $existing ) {
			wp_die( esc_html__( 'Automation not found.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		$trigger_type = isset( $_POST['trigger_type'] ) ? sanitize_key( wp_unslash( $_POST['trigger_type'] ) ) : '';
		if ( ! array_key_exists( $trigger_type, GSAAT_Automations::trigger_types() ) ) {
			$trigger_type = '';
		}

		// JSON payloads built by the editor script; every value is sanitized after decoding.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$keys_json = isset( $_POST['webhook_keys_json'] ) ? wp_unslash( $_POST['webhook_keys_json'] ) : '[]';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$conditions_json = isset( $_POST['conditions_json'] ) ? wp_unslash( $_POST['conditions_json'] ) : '{}';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$actions_json = isset( $_POST['actions_json'] ) ? wp_unslash( $_POST['actions_json'] ) : '[]';

		$trigger_config = array();
		$notices        = array();
		if ( 'cf7' === $trigger_type ) {
			$mode           = isset( $_POST['cf7_mode'] ) ? sanitize_key( wp_unslash( $_POST['cf7_mode'] ) ) : 'form';
			$trigger_config = array(
				'form_id' => isset( $_POST['cf7_form_id'] ) ? absint( wp_unslash( $_POST['cf7_form_id'] ) ) : 0,
				'mode'    => 'field' === $mode ? 'field' : 'form',
				'field'   => isset( $_POST['cf7_field'] ) ? sanitize_text_field( wp_unslash( $_POST['cf7_field'] ) ) : '',
				'value'   => isset( $_POST['cf7_value'] ) ? sanitize_text_field( wp_unslash( $_POST['cf7_value'] ) ) : '',
			);
		} elseif ( 'webhook' === $trigger_type ) {
			$trigger_config = array(
				'keys'     => self::sanitize_keys( json_decode( (string) $keys_json, true ) ),
				'security' => self::sanitize_security( $notices ),
			);
		}

		$webhook_key = $existing ? $existing['webhook_key'] : '';
		$posted_key  = isset( $_POST['webhook_key'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_key'] ) ) : '';
		if ( ! $webhook_key && preg_match( '/^[A-Za-z0-9]{32}$/', $posted_key ) && ! GSAAT_Automations::webhook_key_exists( $posted_key, $id ) ) {
			$webhook_key = $posted_key;
		}
		if ( ! $webhook_key || ! empty( $_POST['regenerate_webhook_key'] ) ) {
			$webhook_key = GSAAT_Automations::generate_webhook_key();
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			$name = __( 'Untitled automation', 'glocalsaino-actions-automation-based-on-trigger' );
		}

		$unlimited_runs = ! empty( $_POST['unlimited_runs'] );
		$never_expires  = ! empty( $_POST['never_expires'] );

		$data = array(
			'name'           => $name,
			'status'         => ( isset( $_POST['status'] ) && 'active' === sanitize_key( wp_unslash( $_POST['status'] ) ) ) ? 'active' : 'inactive',
			'trigger_type'   => $trigger_type,
			'trigger_config' => $trigger_config,
			'conditions'     => self::sanitize_conditions( json_decode( (string) $conditions_json, true ) ),
			'actions'        => self::sanitize_actions( json_decode( (string) $actions_json, true ) ),
			'max_runs'       => $unlimited_runs ? 0 : ( isset( $_POST['max_runs'] ) ? absint( wp_unslash( $_POST['max_runs'] ) ) : 0 ),
			'start_date'     => isset( $_POST['start_date'] ) ? self::local_to_gmt( sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) ) : null,
			'end_date'       => ( ! $never_expires && isset( $_POST['end_date'] ) ) ? self::local_to_gmt( sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) ) : null,
			'webhook_key'    => $webhook_key,
		);

		$message = 'saved';
		if ( '' === $trigger_type || ( 'cf7' === $trigger_type && ( ! $trigger_config['form_id'] || ( 'field' === $trigger_config['mode'] && '' === $trigger_config['field'] ) ) ) ) {
			$data['status'] = 'inactive';
			$message        = 'saved_incomplete_trigger';
		} elseif ( ! $data['actions'] ) {
			$data['status'] = 'inactive';
			$message        = 'saved_no_actions';
		} elseif ( $data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date'] ) {
			$message = 'saved_bad_dates';
		}

		if ( $data['conditions']['dropped_user_rules'] ) {
			$notices[] = 'user_rule_incomplete';
		}
		unset( $data['conditions']['dropped_user_rules'] );

		$saved_id = GSAAT_Automations::save( $data, $id );
		if ( ! $saved_id ) {
			wp_die( esc_html__( 'The automation could not be saved.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		wp_safe_redirect(
			self::url(
				self::PAGE_EDIT,
				array(
					'id'              => $saved_id,
					self::MESSAGE_ARG => implode( ',', array_merge( array( $message ), $notices ) ),
				)
			)
		);
		exit;
	}

	/**
	 * Single-row actions from the list tables (toggle, duplicate, delete, delete log).
	 */
	public static function handle_row_action() {
		self::check_capability();

		$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		check_admin_referer( 'glocalsaino_aat_' . $do . '_' . $id );

		$page    = self::PAGE_LIST;
		$message = '';

		switch ( $do ) {
			case 'activate':
			case 'deactivate':
				GSAAT_Automations::set_status( $id, 'activate' === $do ? 'active' : 'inactive' );
				$message = 'activate' === $do ? 'activated' : 'deactivated';
				break;
			case 'duplicate':
				GSAAT_Automations::duplicate( $id );
				$message = 'duplicated';
				break;
			case 'delete':
				GSAAT_Automations::delete( $id );
				$message = 'deleted';
				break;
			case 'delete_log':
				GSAAT_Logs::delete( 'logs', array( $id ) );
				$page    = self::PAGE_LOGS;
				$message = 'logs_deleted';
				break;
			case 'delete_webhook_log':
				GSAAT_Logs::delete( 'webhook_logs', array( $id ) );
				$page    = self::PAGE_WEBHOOK;
				$message = 'logs_deleted';
				break;
		}

		wp_safe_redirect( self::url( $page, array( self::MESSAGE_ARG => $message ) ) );
		exit;
	}

	public static function handle_clear_logs() {
		self::check_capability();
		check_admin_referer( 'glocalsaino_aat_clear_logs' );

		$table = isset( $_POST['table'] ) ? sanitize_key( wp_unslash( $_POST['table'] ) ) : '';
		GSAAT_Logs::clear( $table );

		wp_safe_redirect( self::url( self::PAGE_SETTING, array( self::MESSAGE_ARG => 'logs_cleared' ) ) );
		exit;
	}

	/**
	 * AJAX: search registered users for the "is the user" condition.
	 */
	public static function ajax_search_users() {
		check_ajax_referer( 'glocalsaino_aat_search_users', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		$term  = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$users = get_users(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
				'number'         => 20,
				'orderby'        => 'display_name',
			)
		);
		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'    => (int) $user->ID,
				'label' => self::user_label( $user ),
			);
		}
		wp_send_json_success( $results );
	}

	/**
	 * Label of a user in the user picker: "Name <email> (#ID)".
	 *
	 * @param WP_User $user User.
	 * @return string
	 */
	public static function user_label( $user ) {
		return sprintf( '%1$s <%2$s> (#%3$d)', $user->display_name, $user->user_email, $user->ID );
	}

	public static function process_automations_bulk() {
		$ids    = self::bulk_ids( 'automations' );
		$action = self::current_bulk_action();
		if ( ! $ids || ! in_array( $action, array( 'activate', 'deactivate', 'delete' ), true ) ) {
			return;
		}
		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				GSAAT_Automations::delete( $id );
			} else {
				GSAAT_Automations::set_status( $id, 'activate' === $action ? 'active' : 'inactive' );
			}
		}
		$messages = array(
			'activate'   => 'activated',
			'deactivate' => 'deactivated',
			'delete'     => 'deleted',
		);
		wp_safe_redirect( self::url( self::PAGE_LIST, array( self::MESSAGE_ARG => $messages[ $action ] ) ) );
		exit;
	}

	public static function process_logs_bulk() {
		self::process_log_bulk( 'logs', self::PAGE_LOGS );
	}

	public static function process_webhook_logs_bulk() {
		self::process_log_bulk( 'webhook_logs', self::PAGE_WEBHOOK );
	}

	private static function process_log_bulk( $table, $page ) {
		$ids = self::bulk_ids( 'logs' === $table ? 'logs' : 'webhook-logs' );
		if ( ! $ids || 'delete' !== self::current_bulk_action() ) {
			return;
		}
		GSAAT_Logs::delete( $table, $ids );
		wp_safe_redirect( self::url( $page, array( self::MESSAGE_ARG => 'logs_deleted' ) ) );
		exit;
	}

	/**
	 * IDs selected in a list table, after checking capability and the table nonce.
	 *
	 * @param string $plural List table plural name (used by its nonce).
	 * @return int[]
	 */
	private static function bulk_ids( $plural ) {
		if ( empty( $_REQUEST['ids'] ) || ! current_user_can( self::CAP ) ) {
			return array();
		}
		check_admin_referer( 'bulk-' . $plural );
		return array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['ids'] ) ) );
	}

	private static function current_bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified in bulk_ids().
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) && '-1' !== $_REQUEST[ $key ] ) {
				return sanitize_key( wp_unslash( $_REQUEST[ $key ] ) );
			}
		}
		// phpcs:enable
		return '';
	}

	/*
	 * ---------------------------------------------------------------------
	 * Sanitizers
	 * ---------------------------------------------------------------------
	 */

	private static function sanitize_keys( $keys ) {
		$clean = array();
		foreach ( is_array( $keys ) ? $keys : array() as $key ) {
			$key = is_string( $key ) ? self::sanitize_field_name( $key ) : '';
			if ( '' !== $key && ! in_array( $key, $clean, true ) ) {
				$clean[] = $key;
			}
		}
		return $clean;
	}

	private static function sanitize_conditions( $conditions ) {
		$conditions = is_array( $conditions ) ? $conditions : array();
		$operators  = GSAAT_Engine::operators();
		$rules      = array();
		$dropped    = 0;

		foreach ( isset( $conditions['rules'] ) && is_array( $conditions['rules'] ) ? $conditions['rules'] : array() as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			if ( isset( $rule['type'] ) && 'user' === $rule['type'] ) {
				$user_rule = self::sanitize_user_rule( $rule );
				if ( $user_rule ) {
					$rules[] = $user_rule;
				} else {
					++$dropped;
				}
				continue;
			}
			$field    = isset( $rule['field'] ) ? self::sanitize_field_name( (string) $rule['field'] ) : '';
			$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
			if ( '' === $field || ! isset( $operators[ $operator ] ) ) {
				continue;
			}
			$rules[] = array(
				'field'    => $field,
				'operator' => $operator,
				'value'    => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
			);
		}

		return array(
			'logic'              => ( isset( $conditions['logic'] ) && 'any' === $conditions['logic'] ) ? 'any' : 'all',
			'rules'              => $rules,
			'dropped_user_rules' => $dropped,
		);
	}

	/**
	 * Sanitize a WordPress user condition.
	 *
	 * @param array $rule Raw rule.
	 * @return array|null Null when the rule is incomplete.
	 */
	private static function sanitize_user_rule( array $rule ) {
		$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
		if ( ! array_key_exists( $operator, GSAAT_Engine::user_operators() ) ) {
			return null;
		}
		$clean = array(
			'type'       => 'user',
			'operator'   => $operator,
			'user_field' => isset( $rule['user_field'] ) ? self::sanitize_field_name( (string) $rule['user_field'] ) : '',
			'user_id'    => isset( $rule['user_id'] ) ? absint( $rule['user_id'] ) : 0,
		);
		if ( 'is_user' === $operator && ! get_userdata( $clean['user_id'] ) ) {
			return null;
		}
		return $clean;
	}

	/**
	 * Sanitize the webhook security settings posted by the editor.
	 *
	 * @param string[] $notices Notice codes, appended to.
	 * @return array
	 */
	private static function sanitize_security( array &$notices ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Called from handle_save(), after check_admin_referer().
		$text = static function ( $key, $default = '' ) {
			return isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : $default;
		};

		$token_enabled = ! empty( $_POST['sec_token_enabled'] );
		$token_value   = preg_replace( '/[^A-Za-z0-9._~\-]/', '', $text( 'sec_token_value' ) );
		if ( $token_enabled && strlen( $token_value ) < 16 ) {
			$token_value = wp_generate_password( 40, false, false );
			$notices[]   = 'token_generated';
		}

		$ip_raw     = isset( $_POST['sec_ip_list'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sec_ip_list'] ) ) : '';
		$ip_entries = array_values( array_unique( array_filter( preg_split( '/[\s,;]+/', $ip_raw ) ) ) );
		$ip_list    = array_values( array_filter( $ip_entries, array( 'GSAAT_Webhook_Security', 'valid_ip_entry' ) ) );
		$ip_enabled = ! empty( $_POST['sec_ip_enabled'] );
		if ( count( $ip_list ) !== count( $ip_entries ) ) {
			$notices[] = 'ip_invalid_removed';
		}
		if ( $ip_enabled && ! $ip_list ) {
			$ip_enabled = false;
			$notices[]  = 'ip_disabled';
		}

		$preset      = $text( 'sec_sig_preset', 'generic' );
		$algo        = $text( 'sec_sig_algo', 'sha256' );
		$secret      = $text( 'sec_sig_secret' );
		$sig_enabled = ! empty( $_POST['sec_sig_enabled'] );
		if ( $sig_enabled && '' === $secret ) {
			$sig_enabled = false;
			$notices[]   = 'signature_disabled';
		}
		$tolerance = absint( $text( 'sec_sig_tolerance', (string) GSAAT_Webhook_Security::DEFAULT_TOLERANCE ) );
		// phpcs:enable

		return array(
			'token'     => array(
				'enabled' => $token_enabled,
				'header'  => self::sanitize_header_name( $text( 'sec_token_header' ), GSAAT_Webhook_Security::DEFAULT_TOKEN_HEADER ),
				'value'   => $token_value,
			),
			'ip'        => array(
				'enabled' => $ip_enabled,
				'list'    => $ip_list,
			),
			'signature' => array(
				'enabled'          => $sig_enabled,
				'preset'           => array_key_exists( $preset, GSAAT_Webhook_Security::presets() ) ? $preset : 'generic',
				'secret'           => $secret,
				'header'           => self::sanitize_header_name( $text( 'sec_sig_header' ), GSAAT_Webhook_Security::DEFAULT_SIGNATURE_HEADER ),
				'algo'             => in_array( $algo, GSAAT_Webhook_Security::algorithms(), true ) ? $algo : 'sha256',
				'encoding'         => 'base64' === $text( 'sec_sig_encoding' ) ? 'base64' : 'hex',
				'timestamp_header' => self::sanitize_header_name( $text( 'sec_sig_timestamp_header' ), '' ),
				'tolerance'        => min( 86400, max( 30, $tolerance ) ),
			),
		);
	}

	private static function sanitize_header_name( $name, $default ) {
		$name = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $name );
		return '' !== $name ? substr( $name, 0, 64 ) : $default;
	}

	private static function sanitize_actions( $actions ) {
		$clean = array();
		foreach ( is_array( $actions ) ? $actions : array() as $action ) {
			if ( ! is_array( $action ) || empty( $action['type'] ) ) {
				continue;
			}
			if ( 'webhook' === $action['type'] ) {
				$method  = isset( $action['method'] ) ? strtoupper( (string) $action['method'] ) : 'POST';
				$clean[] = array(
					'type'    => 'webhook',
					'url'     => isset( $action['url'] ) ? sanitize_text_field( (string) $action['url'] ) : '',
					'method'  => in_array( $method, GSAAT_Actions::http_methods(), true ) ? $method : 'POST',
					'format'  => ( isset( $action['format'] ) && 'form' === $action['format'] ) ? 'form' : 'json',
					'headers' => self::sanitize_pairs( isset( $action['headers'] ) ? $action['headers'] : array() ),
					'fields'  => self::sanitize_pairs( isset( $action['fields'] ) ? $action['fields'] : array() ),
				);
			} elseif ( 'email' === $action['type'] ) {
				$clean[] = array(
					'type'     => 'email',
					'to'       => isset( $action['to'] ) ? sanitize_text_field( (string) $action['to'] ) : '',
					'reply_to' => isset( $action['reply_to'] ) ? sanitize_text_field( (string) $action['reply_to'] ) : '',
					'subject'  => isset( $action['subject'] ) ? sanitize_text_field( (string) $action['subject'] ) : '',
					'body'     => isset( $action['body'] ) ? sanitize_textarea_field( (string) $action['body'] ) : '',
				);
			}
		}
		return $clean;
	}

	private static function sanitize_pairs( $pairs ) {
		$clean = array();
		foreach ( is_array( $pairs ) ? $pairs : array() as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}
			$key = isset( $pair['key'] ) ? sanitize_text_field( (string) $pair['key'] ) : '';
			if ( '' === $key ) {
				continue;
			}
			$clean[] = array(
				'key'   => $key,
				'value' => isset( $pair['value'] ) ? sanitize_text_field( (string) $pair['value'] ) : '',
			);
		}
		return $clean;
	}

	/**
	 * Field / key names: letters, digits and _ . - [ ] :
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	private static function sanitize_field_name( $name ) {
		return substr( preg_replace( '/[^A-Za-z0-9_.\-\[\]:]/', '', $name ), 0, 100 );
	}

	/**
	 * Convert a datetime-local value (site timezone) into a GMT MySQL datetime.
	 *
	 * @param string $value Y-m-d\TH:i value.
	 * @return string|null
	 */
	private static function local_to_gmt( $value ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
			return null;
		}
		$date = date_create_immutable_from_format( 'Y-m-d\TH:i', $value, wp_timezone() );
		return $date ? $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : null;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Helpers used by views and tables
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Admin URL of a plugin page.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Nonced URL for a single-row action.
	 *
	 * @param string $do Action.
	 * @param int    $id Row ID.
	 * @return string
	 */
	public static function row_action_url( $do, $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'glocalsaino_aat_row',
					'do'     => $do,
					'id'     => (int) $id,
				),
				admin_url( 'admin-post.php' )
			),
			'glocalsaino_aat_' . $do . '_' . (int) $id
		);
	}

	/**
	 * Format a GMT MySQL datetime in the site timezone.
	 *
	 * @param string|null $gmt GMT datetime.
	 * @return string
	 */
	public static function format_date( $gmt ) {
		if ( ! $gmt ) {
			return '—';
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $gmt . ' UTC' ) );
	}

	/**
	 * GMT MySQL datetime → datetime-local input value in the site timezone.
	 *
	 * @param string|null $gmt GMT datetime.
	 * @return string
	 */
	public static function gmt_to_local_input( $gmt ) {
		return $gmt ? wp_date( 'Y-m-d\TH:i', strtotime( $gmt . ' UTC' ) ) : '';
	}

	/**
	 * Human description of an automation trigger.
	 *
	 * @param array $automation Automation.
	 * @return string Plain text.
	 */
	public static function describe_trigger( array $automation ) {
		$config = $automation['trigger_config'];
		if ( 'cf7' === $automation['trigger_type'] ) {
			$form_id = isset( $config['form_id'] ) ? (int) $config['form_id'] : 0;
			$title   = $form_id ? get_the_title( $form_id ) : '';
			/* translators: %s: form title or ID. */
			$text = sprintf( __( 'Contact Form 7: "%s" submitted', 'glocalsaino-actions-automation-based-on-trigger' ), '' !== $title ? $title : '#' . $form_id );
			if ( isset( $config['mode'] ) && 'field' === $config['mode'] ) {
				$text .= '' !== $config['value']
					/* translators: 1: field name, 2: expected value. */
					? ' — ' . sprintf( __( 'field "%1$s" = "%2$s"', 'glocalsaino-actions-automation-based-on-trigger' ), $config['field'], $config['value'] )
					/* translators: %s: field name. */
					: ' — ' . sprintf( __( 'field "%s" filled in', 'glocalsaino-actions-automation-based-on-trigger' ), $config['field'] );
			}
			return $text;
		}
		if ( 'webhook' === $automation['trigger_type'] ) {
			$keys      = isset( $config['keys'] ) ? (array) $config['keys'] : array();
			$protected = GSAAT_Webhook_Security::enabled_labels( GSAAT_Webhook_Security::config( $config ) );
			$suffix    = $protected
				/* translators: %s: comma-separated list of protections. */
				? ' — ' . sprintf( __( 'protected by %s', 'glocalsaino-actions-automation-based-on-trigger' ), implode( ', ', $protected ) )
				: '';
			return ( $keys
				/* translators: %s: comma-separated list of keys. */
				? sprintf( __( 'Webhook (keys: %s)', 'glocalsaino-actions-automation-based-on-trigger' ), implode( ', ', $keys ) )
				: __( 'Webhook (all received data)', 'glocalsaino-actions-automation-based-on-trigger' ) ) . $suffix;
		}
		return __( 'No trigger', 'glocalsaino-actions-automation-based-on-trigger' );
	}

	/**
	 * Human description of one action.
	 *
	 * @param array $action Action.
	 * @return string Plain text.
	 */
	public static function describe_action( array $action ) {
		if ( 'webhook' === $action['type'] ) {
			$host = wp_parse_url( $action['url'], PHP_URL_HOST );
			/* translators: 1: HTTP method, 2: host name or URL. */
			return sprintf( __( 'Webhook: %1$s %2$s', 'glocalsaino-actions-automation-based-on-trigger' ), $action['method'], $host ? $host : $action['url'] );
		}
		if ( 'email' === $action['type'] ) {
			/* translators: %s: recipient. */
			return sprintf( __( 'Email to %s', 'glocalsaino-actions-automation-based-on-trigger' ), $action['to'] );
		}
		return $action['type'];
	}

	/**
	 * Label of an availability state.
	 *
	 * @param string $availability Availability key.
	 * @return string
	 */
	public static function availability_label( $availability ) {
		$labels = array(
			'active'        => __( 'Active', 'glocalsaino-actions-automation-based-on-trigger' ),
			'inactive'      => __( 'Inactive', 'glocalsaino-actions-automation-based-on-trigger' ),
			'scheduled'     => __( 'Active — not started yet', 'glocalsaino-actions-automation-based-on-trigger' ),
			'expired'       => __( 'Active — expired', 'glocalsaino-actions-automation-based-on-trigger' ),
			'limit_reached' => __( 'Active — run limit reached', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
		return isset( $labels[ $availability ] ) ? $labels[ $availability ] : $availability;
	}

	/**
	 * Label of a log status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function log_status_label( $status ) {
		$labels = array(
			'success' => __( 'Success', 'glocalsaino-actions-automation-based-on-trigger' ),
			'partial' => __( 'Partial', 'glocalsaino-actions-automation-based-on-trigger' ),
			'error'   => __( 'Error', 'glocalsaino-actions-automation-based-on-trigger' ),
			'skipped' => __( 'Conditions not met', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Short label of an action result.
	 *
	 * @param array $result Action result.
	 * @return string Plain text.
	 */
	public static function result_label( array $result ) {
		$types = GSAAT_Actions::types();
		$type  = isset( $types[ $result['type'] ] ) ? $types[ $result['type'] ] : $result['type'];
		return '' !== $result['target'] ? $type . ' → ' . $result['target'] : $type;
	}

	/**
	 * Print a field / value table.
	 *
	 * @param array $pairs Field => value.
	 */
	public static function pairs_table( array $pairs ) {
		if ( ! $pairs ) {
			echo '<p class="glocalsaino-aat-muted">' . esc_html__( 'No data.', 'glocalsaino-actions-automation-based-on-trigger' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped glocalsaino-aat-pairs"><thead><tr><th>' . esc_html__( 'Field', 'glocalsaino-actions-automation-based-on-trigger' ) . '</th><th>' . esc_html__( 'Value', 'glocalsaino-actions-automation-based-on-trigger' ) . '</th></tr></thead><tbody>';
		foreach ( $pairs as $key => $value ) {
			echo '<tr><td><code>' . esc_html( (string) $key ) . '</code></td><td>' . nl2br( esc_html( is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Print the notice matching the message query arg, if any.
	 */
	public static function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag.
		$codes = isset( $_GET[ self::MESSAGE_ARG ] ) ? array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_GET[ self::MESSAGE_ARG ] ) ) ) ) : array();

		$messages = array(
			'saved'                    => array( 'success', __( 'Automation saved.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'saved_incomplete_trigger' => array( 'warning', __( 'Automation saved as inactive: choose a trigger (and, for Contact Form 7, a form and field) before activating it.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'saved_no_actions'         => array( 'warning', __( 'Automation saved as inactive: add at least one action before activating it.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'saved_bad_dates'          => array( 'warning', __( 'Automation saved, but the end date is before the start date, so it will never run.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'activated'                => array( 'success', __( 'Automation activated.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'deactivated'              => array( 'success', __( 'Automation deactivated.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'duplicated'               => array( 'success', __( 'Automation duplicated. The copy is inactive.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'deleted'                  => array( 'success', __( 'Automation deleted.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'logs_deleted'             => array( 'success', __( 'Log entries deleted.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'logs_cleared'             => array( 'success', __( 'Logs cleared.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'token_generated'          => array( 'info', __( 'A new authentication token was generated (tokens must have at least 16 characters). Give it to the sender.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'ip_invalid_removed'       => array( 'warning', __( 'Some entries of the IP list were not valid IP addresses or ranges and were removed.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'ip_disabled'              => array( 'warning', __( 'The IP restriction was turned off because the list has no valid address.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'signature_disabled'       => array( 'warning', __( 'The signature check was turned off because no signing secret was entered.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'user_rule_incomplete'     => array( 'warning', __( 'A user condition was removed because no valid user was chosen.', 'glocalsaino-actions-automation-based-on-trigger' ) ),
		);

		foreach ( $codes as $code ) {
			if ( ! isset( $messages[ $code ] ) ) {
				continue;
			}
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $code ][0] ),
				esc_html( $messages[ $code ][1] )
			);
		}
	}

	private static function check_capability() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'glocalsaino-actions-automation-based-on-trigger' ), 403 );
		}
	}

	private static function blank_automation() {
		return array(
			'id'             => 0,
			'name'           => '',
			'status'         => 'active',
			'trigger_type'   => '',
			'trigger_config' => array(),
			'conditions'     => array(
				'logic' => 'all',
				'rules' => array(),
			),
			'actions'        => array(),
			'max_runs'       => 0,
			'run_count'      => 0,
			'start_date'     => null,
			'end_date'       => null,
			'webhook_key'    => GSAAT_Automations::generate_webhook_key(),
			'created_at'     => '',
			'updated_at'     => '',
		);
	}

	/**
	 * Data for the automation editor script.
	 *
	 * @return array
	 */
	private static function editor_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$id         = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$automation = $id ? GSAAT_Automations::get( $id ) : null;

		$builtin = array();
		foreach ( GSAAT_Engine::builtin_token_labels() as $key => $label ) {
			$builtin[] = array(
				'key'     => $key,
				'label'   => $label,
				'trigger' => '',
			);
		}
		$builtin[] = array(
			'key'     => '_form_title',
			'label'   => __( 'Form title', 'glocalsaino-actions-automation-based-on-trigger' ),
			'trigger' => 'cf7',
		);
		foreach (
			array(
				'_user_display_name' => __( 'Logged-in user: name', 'glocalsaino-actions-automation-based-on-trigger' ),
				'_user_email'        => __( 'Logged-in user: email', 'glocalsaino-actions-automation-based-on-trigger' ),
				'_user_login'        => __( 'Logged-in user: username', 'glocalsaino-actions-automation-based-on-trigger' ),
			) as $key => $label
		) {
			$builtin[] = array(
				'key'     => $key,
				'label'   => $label,
				'trigger' => 'cf7',
			);
		}
		$builtin[] = array(
			'key'     => '_webhook_method',
			'label'   => __( 'HTTP method used', 'glocalsaino-actions-automation-based-on-trigger' ),
			'trigger' => 'webhook',
		);

		$user_labels = array();
		foreach ( $automation ? $automation['conditions']['rules'] : array() as $rule ) {
			if ( ! empty( $rule['user_id'] ) ) {
				$user = get_userdata( $rule['user_id'] );
				/* translators: %d: user ID. */
				$user_labels[ (int) $rule['user_id'] ] = $user ? self::user_label( $user ) : sprintf( __( 'Deleted user (#%d)', 'glocalsaino-actions-automation-based-on-trigger' ), $rule['user_id'] );
			}
		}

		return array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'userNonce'     => wp_create_nonce( 'glocalsaino_aat_search_users' ),
			'userOperators' => GSAAT_Engine::user_operators(),
			'userLabels'    => (object) $user_labels,
			'cf7Forms'      => GSAAT_Trigger_CF7::forms(),
			'webhookBase'   => rest_url( GSAAT_Automations::REST_NAMESPACE . '/webhook/' ),
			'builtinTokens' => $builtin,
			'operators'     => GSAAT_Engine::operators(),
			'methods'       => GSAAT_Actions::http_methods(),
			'state'         => array(
				'webhookKeys' => $automation && isset( $automation['trigger_config']['keys'] ) ? array_values( (array) $automation['trigger_config']['keys'] ) : array(),
				'conditions'  => $automation ? $automation['conditions'] : array(
					'logic' => 'all',
					'rules' => array(),
				),
				'actions'     => $automation ? $automation['actions'] : array(),
			),
			'i18n'          => array(
				'triggerFields'   => __( 'Trigger fields', 'glocalsaino-actions-automation-based-on-trigger' ),
				'generalFields'   => __( 'General', 'glocalsaino-actions-automation-based-on-trigger' ),
				'insertField'     => __( 'Insert field…', 'glocalsaino-actions-automation-based-on-trigger' ),
				'chooseField'     => __( '— Choose a field —', 'glocalsaino-actions-automation-based-on-trigger' ),
				'chooseForm'      => __( '— Choose a form —', 'glocalsaino-actions-automation-based-on-trigger' ),
				'notAvailable'    => __( '(not available)', 'glocalsaino-actions-automation-based-on-trigger' ),
				'remove'          => __( 'Remove', 'glocalsaino-actions-automation-based-on-trigger' ),
				'moveUp'          => __( 'Move up', 'glocalsaino-actions-automation-based-on-trigger' ),
				'moveDown'        => __( 'Move down', 'glocalsaino-actions-automation-based-on-trigger' ),
				'keyName'         => __( 'Key name', 'glocalsaino-actions-automation-based-on-trigger' ),
				'value'           => __( 'Value', 'glocalsaino-actions-automation-based-on-trigger' ),
				'operator'        => __( 'Comparison', 'glocalsaino-actions-automation-based-on-trigger' ),
				'key'             => __( 'Key', 'glocalsaino-actions-automation-based-on-trigger' ),
				'addPair'         => __( 'Add key / value', 'glocalsaino-actions-automation-based-on-trigger' ),
				'addHeader'       => __( 'Add header', 'glocalsaino-actions-automation-based-on-trigger' ),
				'noKeys'          => __( 'No keys configured: every received field will be available.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'noConditions'    => __( 'No conditions: the actions run every time the trigger fires.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'noActions'       => __( 'No actions yet. Add at least one.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'actionWebhook'   => __( 'Send data to an external webhook', 'glocalsaino-actions-automation-based-on-trigger' ),
				'actionEmail'     => __( 'Send an email', 'glocalsaino-actions-automation-based-on-trigger' ),
				'url'             => __( 'URL', 'glocalsaino-actions-automation-based-on-trigger' ),
				'method'          => __( 'Method', 'glocalsaino-actions-automation-based-on-trigger' ),
				'format'          => __( 'Body format', 'glocalsaino-actions-automation-based-on-trigger' ),
				'formatJson'      => __( 'JSON', 'glocalsaino-actions-automation-based-on-trigger' ),
				'formatForm'      => __( 'Form (x-www-form-urlencoded)', 'glocalsaino-actions-automation-based-on-trigger' ),
				'dataToSend'      => __( 'Data to send', 'glocalsaino-actions-automation-based-on-trigger' ),
				'dataGetHint'     => __( 'With GET and DELETE the data is sent as query string parameters.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'headers'         => __( 'Headers (optional)', 'glocalsaino-actions-automation-based-on-trigger' ),
				'to'              => __( 'Send to', 'glocalsaino-actions-automation-based-on-trigger' ),
				'toHint'          => __( 'One or more addresses separated by commas. You can use fields, e.g. {{your-email}}.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'replyTo'         => __( 'Reply-To (optional)', 'glocalsaino-actions-automation-based-on-trigger' ),
				'subject'         => __( 'Subject', 'glocalsaino-actions-automation-based-on-trigger' ),
				'body'            => __( 'Message', 'glocalsaino-actions-automation-based-on-trigger' ),
				'copied'          => __( 'Copied!', 'glocalsaino-actions-automation-based-on-trigger' ),
				'errorForm'       => __( 'Choose the Contact Form 7 form used as trigger.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'errorField'      => __( 'Choose the form field used as trigger.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'exampleValue'    => __( 'VALUE', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sendHeader'      => __( 'Also send the header', 'glocalsaino-actions-automation-based-on-trigger' ),
				'wpUser'          => __( 'WordPress user', 'glocalsaino-actions-automation-based-on-trigger' ),
				'identifiedBy'    => __( 'identified by', 'glocalsaino-actions-automation-based-on-trigger' ),
				'loggedInVisitor' => __( 'the logged-in visitor', 'glocalsaino-actions-automation-based-on-trigger' ),
				'emailInField'    => __( 'Email or username in field', 'glocalsaino-actions-automation-based-on-trigger' ),
				'searchUser'      => __( 'Search by name, email or username…', 'glocalsaino-actions-automation-based-on-trigger' ),
				'errorUser'       => __( 'Choose a user from the list for every "is the user" condition.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'webhookNoLogin'  => __( 'Webhook calls come from an external server, so there is never a logged-in visitor: identify the user with a field.', 'glocalsaino-actions-automation-based-on-trigger' ),
				'missingKeyValue' => __( 'Enter a key name for every row, or remove the empty rows.', 'glocalsaino-actions-automation-based-on-trigger' ),
			),
		);
	}
}
