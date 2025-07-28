<?php
/**
 * Swift Index Database Setup
 *
 * This file contains functions related to setting up and managing the custom
 * database table used by the Swift Index plugin for logging notifications.
 *
 * @package Swift_Index
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieves the full name of the custom log table, including the WordPress prefix.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return string The full name of the log table.
 */
function swift_index_get_log_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'swift_index_log';
}

/**
 * Creates or updates the custom database table for storing notification logs.
 *
 * This function is typically called on plugin activation. It uses dbDelta()
 * to create the table if it doesn't exist, or update it if the schema has changed.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 */
function swift_index_create_db_table() {
	global $wpdb;
	$table_name      = swift_index_get_log_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		post_id BIGINT(20) UNSIGNED DEFAULT 0,
		notified_url TEXT NOT NULL,
		notification_type VARCHAR(50) NOT NULL,
		status_code VARCHAR(20) DEFAULT NULL,
		response_message TEXT DEFAULT NULL,
		notified_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY idx_post_id (post_id),
		KEY idx_notified_at (notified_at)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
