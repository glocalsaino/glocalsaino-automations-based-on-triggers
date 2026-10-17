<?php
/**
 * Database tables.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_DB {

	const VERSION_OPTION = 'glocalsaino_aat_db_version';

	/**
	 * Full table name for one of the plugin tables.
	 *
	 * @param string $name automations|logs|webhook_logs.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'glocalsaino_aat_' . $name;
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset      = $wpdb->get_charset_collate();
		$automations  = self::table( 'automations' );
		$logs         = self::table( 'logs' );
		$webhook_logs = self::table( 'webhook_logs' );

		dbDelta(
			"CREATE TABLE {$automations} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'inactive',
				trigger_type varchar(30) NOT NULL DEFAULT '',
				trigger_config longtext NULL,
				conditions longtext NULL,
				actions longtext NULL,
				max_runs int(10) unsigned NOT NULL DEFAULT 0,
				run_count int(10) unsigned NOT NULL DEFAULT 0,
				start_date datetime NULL DEFAULT NULL,
				end_date datetime NULL DEFAULT NULL,
				webhook_key varchar(64) NOT NULL DEFAULT '',
				meta longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY trigger_type (trigger_type),
				KEY webhook_key (webhook_key)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				automation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				automation_name varchar(191) NOT NULL DEFAULT '',
				trigger_type varchar(30) NOT NULL DEFAULT '',
				event text NULL,
				trigger_data longtext NULL,
				results longtext NULL,
				status varchar(20) NOT NULL DEFAULT '',
				message text NULL,
				context longtext NULL,
				retry_of bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY automation_id (automation_id),
				KEY status (status),
				KEY retry_of (retry_of),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$webhook_logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				automation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				automation_name varchar(191) NOT NULL DEFAULT '',
				webhook_url text NULL,
				method varchar(10) NOT NULL DEFAULT '',
				remote_ip varchar(45) NOT NULL DEFAULT '',
				received_data longtext NULL,
				status_code smallint(5) unsigned NOT NULL DEFAULT 200,
				error_code varchar(64) NOT NULL DEFAULT '',
				error_message text NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY automation_id (automation_id),
				KEY created_at (created_at)
			) {$charset};"
		);

		update_option( self::VERSION_OPTION, GSAAT_DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) !== GSAAT_DB_VERSION ) {
			self::install();
		}
	}
}
