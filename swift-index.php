<?php
/**
 * Plugin Name: Swift Index
 * Plugin URI: https://github.com/firstelementjp/swift-index
 * Description: Automatically notifies Google's Indexing API when your WordPress content is published, for faster indexing.
 * Version: 1.0.0
 * Author: FirstElement Inc.
 * Author URI: https://www.firstelement.co.jp/
 * Text Domain: swift-index
 * Domain Path: /languages
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.8
 * Tested up to: 6.8.1
 * Requires PHP: 7.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version.
 *
 * @since 1.0.0
 */
define( 'SWIFT_INDEX_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 *
 * @since 1.0.0
 */
define( 'SWIFT_INDEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @since 1.0.0
 */
define( 'SWIFT_INDEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin main file path.
 *
 * @since 1.0.0
 */
define( 'SWIFT_INDEX_PLUGIN_FILE', __FILE__ );

// Load scoped Composer autoloader.
if ( file_exists( SWIFT_INDEX_PLUGIN_DIR . 'scoped/vendor/autoload.php' ) ) {
    require_once SWIFT_INDEX_PLUGIN_DIR . 'scoped/vendor/autoload.php';
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>Swift Index: Required scoped autoloader not found. Please ensure the plugin was built correctly.</p></div>';
    });
    return;
}

require_once SWIFT_INDEX_PLUGIN_DIR . 'includes/db-setup.php';
require_once SWIFT_INDEX_PLUGIN_DIR . 'includes/class-swift-index-log-list-table.php';
require_once SWIFT_INDEX_PLUGIN_DIR . 'includes/log-functions.php';
require_once SWIFT_INDEX_PLUGIN_DIR . 'includes/api-handler.php';
require_once SWIFT_INDEX_PLUGIN_DIR . 'includes/post-integration.php';
require_once SWIFT_INDEX_PLUGIN_DIR . 'includes/admin-settings.php';
require_once SWIFT_INDEX_PLUGIN_DIR . 'includes/enqueue-scripts.php';

/**
 * Plugin activation callback.
 *
 * This function is triggered when the plugin is activated. It handles
 * initial setup tasks such as database table creation, version checking
 * for upgrades, and scheduling cron events.
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_activate() {
    $current_db_version = get_option( 'swift_index_version', '0.0.0' );
    if ( version_compare( $current_db_version, SWIFT_INDEX_VERSION, '<' ) ) {
        // Create or update the database table. dbDelta() handles this.
        if ( function_exists( 'swift_index_create_db_table' ) ) {
            swift_index_create_db_table();
        }
        update_option( 'swift_index_version', SWIFT_INDEX_VERSION );
    }
    // Schedule cron events (function checks if already scheduled).
    if ( function_exists( 'swift_index_schedule_log_rotation' ) ) {
        swift_index_schedule_log_rotation();
    }
}

/**
 * Register activation and deactivation hooks.
 *
 * Activation callback `swift_index_activate` is defined in this file.
 * Deactivation callback `swift_index_deactivate` is defined in includes/log-functions.php.
 */
register_activation_hook( SWIFT_INDEX_PLUGIN_FILE, 'swift_index_activate' );
register_deactivation_hook( SWIFT_INDEX_PLUGIN_FILE, 'swift_index_deactivate' );

/**
 * Loads the plugin's text domain for localization.
 *
 * This function is hooked to the 'plugins_loaded' action.
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_load_textdomain() {
    $loaded = load_plugin_textdomain(
        'swift-index',
        false,
        dirname( plugin_basename( SWIFT_INDEX_PLUGIN_FILE ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'swift_index_load_textdomain' );

/**
 * Register Admin Menu and Settings
 * Functions are defined in includes/admin-settings.php
 */
add_action( 'admin_menu', 'swift_index_add_admin_menu' );
add_action( 'admin_init', 'swift_index_settings_init' );

/**
 * Enqueue Admin Scripts and Styles
 * Function is defined in includes/enqueue-scripts.php
 */
add_action( 'admin_enqueue_scripts', 'swift_index_admin_scripts' );

/**
 * Register custom column display for targeted post types
 * Function is defined in includes/post-integration.php
 */
add_action( 'admin_init', 'swift_index_register_date_column_filter_for_targeted_types' );

/**
 * Hook for the daily log rotation cron event
 * Callback function is defined in includes/log-functions.php
 */
add_action( 'swift_index_daily_log_rotation_hook', 'swift_index_perform_log_rotation' );

?>
