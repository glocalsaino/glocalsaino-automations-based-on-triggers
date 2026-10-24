<?php
/**
 * Plugin bootstrap: hooks, cron and settings.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Plugin {

	const CRON_HOOK       = 'glocalsaino_aat_daily_cleanup';
	const SETTINGS_OPTION = 'glocalsaino_aat_settings';

	public static function init() {
		GSAAT_DB::maybe_upgrade();

		GSAAT_Trigger_CF7::init();
		GSAAT_Trigger_Webhook::init();
		GSAAT_Notifier::init();
		GSAAT_Trigger_Click::init();
		GSAAT_Trigger_User::init();
		GSAAT_Redirect::init();
		GSAAT_Scheduler::init();
		GSAAT_Trigger_Widget::init();
		GSAAT_Tables::init();
		GSAAT_Trigger_File::init();

		add_action( self::CRON_HOOK, array( 'GSAAT_Logs', 'cleanup' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );

		if ( is_admin() ) {
			GSAAT_Admin::init();
			GSAAT_Admin_Tables::init();
		}

		/**
		 * Fires once the plugin is loaded. Add-ons should register their hooks here
		 * and check GSAAT_API_VERSION for compatibility.
		 *
		 * @param string $api_version Extension API version.
		 */
		do_action( 'glocalsaino_aat_loaded', GSAAT_API_VERSION );
	}

	public static function activate() {
		GSAAT_DB::install();
		self::ensure_cron();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( GSAAT_Scheduler::SWEEP_HOOK );
		// Pending jobs stay in the queue; the hourly sweep runs them after reactivation.
		wp_unschedule_hook( GSAAT_Scheduler::JOB_HOOK );
	}

	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( GSAAT_Scheduler::SWEEP_HOOK ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', GSAAT_Scheduler::SWEEP_HOOK );
		}
	}

	/**
	 * Default values for the plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		/**
		 * Filters the default settings. Add-ons can add their own keys.
		 *
		 * @param array $defaults Default settings.
		 */
		return apply_filters( 'glocalsaino_aat_default_settings', array(
			'logs_retention_days'         => 30,
			'webhook_logs_retention_days' => 30,
			'delete_data_on_uninstall'    => 0,
			'ip_source'                   => 'remote_addr',
			'notify_failures'             => 1,
			'notify_partial'              => 1,
			'failure_email_to'            => '',
			'failure_email_throttle'      => 60,
			'hcaptcha_site_key'           => '',
			'hcaptcha_secret_key'         => '',
		) );
	}

	/**
	 * Current plugin settings merged with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * Whether Contact Form 7 is active.
	 *
	 * @return bool
	 */
	public static function cf7_active() {
		return class_exists( 'WPCF7_ContactForm' );
	}
}
