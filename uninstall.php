<?php
/**
 * Swift Index Uninstall
 *
 * Handles the uninstallation process for Swift Index.
 * Deletes custom database tables, WordPress options, and scheduled cron events
 * if the user has opted to remove data upon uninstallation.
 *
 * @package Swift_Index
 * @since   1.0.0
 */

// Exit if uninstall.php is not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check user's preference for data deletion.
$delete_data_on_uninstall = get_option( 'swift_index_delete_data_on_uninstall' );

if ( $delete_data_on_uninstall === 'yes' ) {

	$options_to_delete = array(
		'swift_index_service_account_json',
		'swift_index_target_post_types',
		'swift_index_log_rotation_type',
		'swift_index_log_rotation_value_days',
		'swift_index_log_rotation_value_count',
		'swift_index_delete_data_on_uninstall',
		'swift_index_version',
	);

	foreach ( $options_to_delete as $option_name ) {
		delete_option( $option_name );
	}

	// Delete Custom Database Table
	global $wpdb;
	$log_table_name = $wpdb->prefix . 'swift_index_log';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	if (!empty($log_table_name)) {
		$sql_query_format = "DROP TABLE IF EXISTS %s";
		$sql_template_for_prepare = sprintf($sql_query_format, $log_table_name);
		$prepared_sql = $wpdb->prepare($sql_template_for_prepare);

		if ($prepared_sql) {
			$wpdb->query($prepared_sql);
		}
	}

	// Delete Scheduled Cron Events
	$timestamp = wp_next_scheduled( 'swift_index_daily_log_rotation_hook' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'swift_index_daily_log_rotation_hook' );
	}
}

?>
