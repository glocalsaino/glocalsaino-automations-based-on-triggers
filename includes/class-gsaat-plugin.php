<?php
/**
 * Plugin bootstrap: hooks, cron and settings.
 *
 * @package GlocalSainoActionsAutomation
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

		add_action( self::CRON_HOOK, array( 'GSAAT_Logs', 'cleanup' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );

		if ( is_admin() ) {
			GSAAT_Admin::init();
		}
	}

	public static function activate() {
		GSAAT_DB::install();
		self::ensure_cron();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Default values for the plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'logs_retention_days'         => 30,
			'webhook_logs_retention_days' => 30,
			'delete_data_on_uninstall'    => 0,
			'ip_source'                   => 'remote_addr',
		);
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
