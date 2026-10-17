<?php
/**
 * Uninstall: clears the cron event and, if enabled in the settings, removes all plugin data.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'glocalsaino_aat_daily_cleanup' );

$glocalsaino_aat_settings = get_option( 'glocalsaino_aat_settings', array() );
if ( empty( $glocalsaino_aat_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'automations', 'logs', 'webhook_logs' ) as $glocalsaino_aat_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own tables on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'glocalsaino_aat_' . $glocalsaino_aat_table ) );
}

delete_option( 'glocalsaino_aat_settings' );
delete_option( 'glocalsaino_aat_db_version' );
