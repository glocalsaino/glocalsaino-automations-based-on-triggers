<?php
/**
 * Plugin Name:       GlocalSaino Automations Based on Triggers
 * Plugin URI:        https://glocalsaino.com/automationsbasedontriggers/
 * Description:       No-code automations: when a form is sent, a file is uploaded, a link, button or widget is used, a webhook arrives or a user signs up, run emails, webhooks, calculations, tables, redirects and scheduled actions.
 * Version:           1.3.4
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Glocal Saino
 * Author URI:        https://glocalsaino.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       glocalsaino-automations-based-on-triggers
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSAAT_VERSION', '1.3.4' );
define( 'GSAAT_DB_VERSION', '6' );
define( 'GSAAT_API_VERSION', '1.0' );
define( 'GSAAT_FILE', __FILE__ );
define( 'GSAAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'GSAAT_URL', plugin_dir_url( __FILE__ ) );

// Translations are loaded automatically by WordPress since 4.6 for
// WordPress.org-hosted plugins (matched by the Text Domain header above).

require_once GSAAT_PATH . 'includes/class-gsaat-plugin.php';
require_once GSAAT_PATH . 'includes/class-gsaat-db.php';
require_once GSAAT_PATH . 'includes/class-gsaat-registry.php';
require_once GSAAT_PATH . 'includes/class-gsaat-automations.php';
require_once GSAAT_PATH . 'includes/class-gsaat-logs.php';
require_once GSAAT_PATH . 'includes/class-gsaat-engine.php';
require_once GSAAT_PATH . 'includes/class-gsaat-actions.php';
require_once GSAAT_PATH . 'includes/class-gsaat-calculator.php';
require_once GSAAT_PATH . 'includes/class-gsaat-formatter.php';
require_once GSAAT_PATH . 'includes/class-gsaat-redirect.php';
require_once GSAAT_PATH . 'includes/class-gsaat-scheduler.php';
require_once GSAAT_PATH . 'includes/class-gsaat-notifier.php';
require_once GSAAT_PATH . 'includes/class-gsaat-webhook-security.php';
require_once GSAAT_PATH . 'includes/class-gsaat-trigger-cf7.php';
require_once GSAAT_PATH . 'includes/class-gsaat-trigger-webhook.php';
require_once GSAAT_PATH . 'includes/class-gsaat-trigger-click.php';
require_once GSAAT_PATH . 'includes/class-gsaat-trigger-user.php';
require_once GSAAT_PATH . 'includes/class-gsaat-captcha.php';
require_once GSAAT_PATH . 'includes/class-gsaat-widget-stats.php';
require_once GSAAT_PATH . 'includes/class-gsaat-trigger-widget.php';
require_once GSAAT_PATH . 'includes/class-gsaat-file-reader.php';
require_once GSAAT_PATH . 'includes/class-gsaat-tables.php';
require_once GSAAT_PATH . 'includes/class-gsaat-trigger-file.php';

if ( is_admin() ) {
	require_once GSAAT_PATH . 'includes/admin/class-gsaat-admin.php';
	require_once GSAAT_PATH . 'includes/admin/class-gsaat-admin-tables.php';
}

register_activation_hook( __FILE__, array( 'GSAAT_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GSAAT_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'GSAAT_Plugin', 'init' ) );
