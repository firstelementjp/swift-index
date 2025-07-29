<?php
/**
 * Swift Index Log Functions
 *
 * This file contains functions related to recording, managing, rotating,
 * and displaying notification logs for the Swift Index plugin.
 *
 * @package Swift_Index
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records an API notification log entry into the custom database table.
 *
 * @since 1.0.0
 *
 * @global wpdb  $wpdb WordPress database abstraction object.
 *
 * @param int    $post_id       The ID of the post related to the notification. Defaults to 0 if not applicable.
 * @param string $url           The URL that was notified.
 * @param string $type          The type of notification (e.g., 'URL_UPDATED', 'URL_DELETED').
 * @param string $status_code   The HTTP status code received from the API or a custom status string.
 * @param string $message (Optional) The API response message or error details. Default ''.
 * @return void
 */
function swift_index_record_log($post_id, $url, $type, $status_code, $message = '') {
	global $wpdb;
	$table_name = swift_index_get_log_table_name();

	$wpdb->insert(
		$table_name,
		array(
			'post_id'           => $post_id ? absint($post_id) : 0,
			'notified_url'      => esc_url_raw($url),
			'notification_type' => sanitize_text_field($type),
			'status_code'       => sanitize_text_field($status_code),
			'response_message'  => wp_kses_post($message),
			'notified_at'       => current_time('mysql', 1)
		),
		array(
			'%d', // post_id
			'%s', // notified_url
			'%s', // notification_type
			'%s', // status_code
			'%s', // response_message
			'%s'  // notified_at
		)
	);
}

/**
 * Schedules the daily cron event for log rotation if it's not already scheduled.
 *
 * This function should ideally be called once, e.g., on plugin activation,
 * or checked periodically via an 'init' hook if more robustness is needed.
 * Currently called via swift_index_activate().
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_schedule_log_rotation() {
	if (!wp_next_scheduled('swift_index_daily_log_rotation_hook')) {
		wp_schedule_event(time(), 'daily', 'swift_index_daily_log_rotation_hook');
	}
}

/**
 * Unschedules the log rotation cron event.
 *
 * This function is intended to be called on plugin deactivation.
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_deactivate() {
	$timestamp = wp_next_scheduled('swift_index_daily_log_rotation_hook');
	if ($timestamp) {
		wp_unschedule_event($timestamp, 'swift_index_daily_log_rotation_hook');
	}
}

/**
 * Performs the actual log rotation based on settings (latest_per_post, days or count).
 *
 * This function is hooked to 'swift_index_daily_log_rotation_hook'.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 */
function swift_index_perform_log_rotation() {
	global $wpdb;
	$table_name = swift_index_get_log_table_name();

	$rotation_type = get_option('swift_index_log_rotation_type', 'latest_per_post');

	if ($rotation_type === 'latest_per_post') {
		global $wpdb;

		$sql_query_format = "
			DELETE FROM %s
			WHERE id NOT IN (
				SELECT latest_id FROM (
					SELECT MAX(id) as latest_id
					FROM %s
					GROUP BY post_id
				) AS subquery_for_delete
			)
		";
		$sql_template_for_db = sprintf($sql_query_format, $table_name, $table_name);
		$prepared_sql = $wpdb->prepare($sql_template_for_db);

		$deleted_rows = 0;
		if (!empty($prepared_sql)) {
			$result = $wpdb->query($prepared_sql);
			if (false !== $result) {
				$deleted_rows = $result;

				if ($deleted_rows > 0) {

					// TODO: Implement caching for 'total_log_count' and enable this delete.
					// wp_cache_delete('total_log_count', 'swift_index_logs');
					// TODO: Implement caching for 'latest_logs_list' and enable this delete.
					// wp_cache_delete('latest_logs_list', 'swift_index_logs');

					/**
					 * Fires after log entries are deleted by rotation.
					 *
					 * Allows invalidating custom caches or performing other actions.
					 *
					 * @since 1.0.0
					 * @param int $deleted_rows Number of rows deleted.
					 */
					do_action('swift_index_after_log_rotation_delete', $deleted_rows);
				}
			}
		}
		return;
	}

	$rotation_value = 0;
	if ($rotation_type === 'days') {
		$rotation_value = get_option('swift_index_log_rotation_value_days', 30);
	} elseif ($rotation_type === 'count') {
		$rotation_value = get_option('swift_index_log_rotation_value_count', 1000);
	}

	if ($rotation_value <= 0) {
		return;
	}

	if ($rotation_type === 'days') {
		$sql_format_string = "DELETE FROM %s WHERE notified_at < DATE_SUB(NOW(), INTERVAL %%d DAY)";
		$sql_query_template = sprintf($sql_format_string, $table_name);
		$prepared_sql = $wpdb->prepare($sql_query_template, $rotation_value);

		if ($prepared_sql) {
			$wpdb->query($prepared_sql);
		}

	} elseif ($rotation_type === 'count') {
		$sql_format_string_count = "SELECT COUNT(id) FROM %s";
		$sql_query_template_count = sprintf($sql_format_string_count, $table_name);
		$prepared_sql_count = $wpdb->prepare($sql_query_template_count);

		if ($prepared_sql_count) {
			$total_logs = (int) $wpdb->get_var($prepared_sql_count);
		} else {
			$total_logs = 0;
		}

		if ($total_logs > $rotation_value) {
			$logs_to_delete_count = $total_logs - $rotation_value;
			$sql_format_string_delete = "DELETE FROM %s ORDER BY notified_at ASC LIMIT %%d";
			$sql_query_template_delete = sprintf($sql_format_string_delete, $table_name);
			$prepared_sql_delete = $wpdb->prepare($sql_query_template_delete, $logs_to_delete_count);

			if ($prepared_sql_delete) {
				$wpdb->query($prepared_sql_delete);
			}
		}
	}
}
add_action('swift_index_daily_log_rotation_hook', 'swift_index_perform_log_rotation');

/**
 * Handles the action for deleting all notification logs.
 *
 * Triggered by an admin POST request. Verifies nonce and user capabilities
 * before deleting all entries from the log table.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void Dies or redirects.
 */
function swift_index_handle_delete_all_logs_action() {
	// 1. Verify Nonce
	if (!isset($_POST['swift_index_delete_all_logs_nonce']) || !wp_verify_nonce(sanitize_key($_POST['swift_index_delete_all_logs_nonce']), 'swift_index_delete_all_logs_nonce_action')) {
		wp_die(
			esc_html__('Security check failed. Please try refreshing the page and try again.', 'swift-index'),
			esc_html__('Error', 'swift-index'),
			array('response' => 403, 'back_link' => true)
		);
	}

	// 2. Check User Capabilities
	if (!current_user_can('manage_options')) {
		wp_die(
			esc_html__('You do not have sufficient permissions to perform this action.', 'swift-index'),
			esc_html__('Error', 'swift-index'),
			array('response' => 403, 'back_link' => true)
		);
	}

	// 3. Delete Logs
	global $wpdb;
	$table_name = swift_index_get_log_table_name();

	$sql_format_string = "DELETE FROM %s";
	$sql_query_template = sprintf($sql_format_string, $table_name);
	$prepared_sql = $wpdb->prepare($sql_query_template);

	$result = false;
	if (!empty($prepared_sql)) {
		$result = $wpdb->query($prepared_sql);
	}

	// 4. Set Admin Notice via Transient
	if (false === $result) {
		$message = __('Failed to delete logs.', 'swift-index');
		if ($wpdb->last_error) {
			// translators: %s: The specific database error message.
			$message .= ' ' . sprintf(__('Database error: %s', 'swift-index'), $wpdb->last_error);
		}
		set_transient('swift_index_admin_notice', array('type' => 'error', 'message' => $message), 30);
	} else {
		set_transient('swift_index_admin_notice', array('type' => 'success', 'message' => __('All notification logs have been successfully deleted.', 'swift-index')), 30);
	}

	// 5. Redirect back to the logs page/tab
	$redirect_url = admin_url('admin.php?page=swift-index-main&tab=logs');
	wp_redirect(esc_url_raw($redirect_url));
	exit;
}
add_action('admin_post_swift_index_delete_all_logs_action', 'swift_index_handle_delete_all_logs_action');
