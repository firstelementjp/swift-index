<?php
/**
 * Swift Index Post Integration
 *
 * This file contains functions responsible for integrating Swift Index features
 * with WordPress posts. This includes adding meta boxes to post edit screens,
 * saving post meta data, hooking into post status transitions (publish, update, trash)
 * to trigger API notifications, and customizing admin list table columns to display
 * log summaries.
 *
 * @package Swift_Index
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a meta box to the post editing screen for targeted post types.
 *
 * The meta box allows users to control Indexing API notifications for
 * individual posts and view the latest notification log for the post.
 * Hooked to 'add_meta_boxes'.
 *
 * @since 1.0.0
 * @see swift_index_meta_box_callback() For the meta box content.
 * @return void
 */
function swift_index_add_meta_box() {
	$target_post_types = get_option('swift_index_target_post_types', array());

	if (empty($target_post_types)) {
		return;
	}

	foreach ($target_post_types as $post_type) {
		add_meta_box(
			'swift_index_meta_box',
			__('Swift Index', 'swift-index'),
			'swift_index_meta_box_callback',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action('add_meta_boxes', 'swift_index_add_meta_box');

/**
 * Callback function to display the HTML content of the Swift Index meta box.
 *
 * This function calls swift_index_get_post_meta_content_html() to render the actual content.
 *
 * @since 1.0.0
 * @param WP_Post $post The current post object.
 * @return void
 */
function swift_index_meta_box_callback($post) {
	echo wp_kses_post(swift_index_get_post_meta_content_html($post));
}

/**
 * Generates and returns the HTML content for the Swift Index meta box.
 *
 * This includes a nonce field for security, a checkbox to enable/disable API
 * submission for the specific post, and a display of the latest notification
 * log entry related to this post.
 *
 * @since 1.0.0
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post $post_or_post_id Post ID or WP_Post object.
 * @return string The HTML content for the meta box, or an error message if post data is invalid.
 */
function swift_index_get_post_meta_content_html($post_or_post_id) {
	$post = get_post($post_or_post_id);

	if (!$post) {
		return '<p>' . esc_html__('Invalid post data provided to Swift Index.', 'swift-index') . '</p>';
	}

	ob_start();

	wp_nonce_field('swift_index_save_meta_box_data', 'swift_index_meta_box_nonce');

	$send_to_api = get_post_meta($post->ID, '_swift_index_send_notification', true);
	$is_new_post = empty($post->ID) || $post->post_status === 'auto-draft';
	$target_post_types_option = get_option('swift_index_target_post_types', array());
	$current_post_type_is_globally_enabled = in_array($post->post_type, $target_post_types_option, true);

	$checked_value = '';
	if ($is_new_post) {
		$checked_value = $current_post_type_is_globally_enabled ? 'yes' : 'no';
	} else {
		$checked_value = ($send_to_api === '') ? ($current_post_type_is_globally_enabled ? 'yes' : 'no') : $send_to_api;
	}

	?>
	<div class="swift-index-meta-section swift-index-submission-options">
		<h4><?php esc_html_e('Indexing API Submission', 'swift-index'); ?></h4>
		<p>
			<label for="swift_index_send_notification_<?php echo esc_attr($post->ID); ?>">
				<input type="checkbox" id="swift_index_send_notification_<?php echo esc_attr($post->ID); ?>" name="swift_index_send_notification" value="yes" <?php checked($checked_value, 'yes'); ?> />
				<?php esc_html_e('Notify Google Indexing API for this post', 'swift-index'); ?>
			</label>
		</p>
		<p class="description">
			<?php
			if ($current_post_type_is_globally_enabled) {
				esc_html_e('This post type is generally targeted for notifications. Uncheck to skip for this specific post.', 'swift-index');
			} else {
				esc_html_e('This post type is not generally targeted for notifications. Check to notify for this specific post.', 'swift-index');
			}
			?>
		</p>
	</div>

	<hr style="margin-top: 15px; margin-bottom: 10px;">

	<div class="swift-index-meta-section swift-index-latest-log-display">
		<h4><?php esc_html_e('Latest Notification Log', 'swift-index'); ?></h4>
		<?php
		global $wpdb;
		$table_name = swift_index_get_log_table_name();
		$post_id_to_check = $post->ID;

		$sql_query_format = "SELECT notified_at, notification_type, status_code, response_message FROM %s WHERE post_id = %%d ORDER BY notified_at DESC LIMIT 1";

		$sql_template_for_prepare = sprintf($sql_query_format, $table_name);

		$prepared_sql = $wpdb->prepare(
			$sql_template_for_prepare,
			$post_id_to_check
		);
		if ($prepared_sql) {
			$latest_log = $wpdb->get_row($prepared_sql);
		}

		if ($latest_log) :
			?>
			<div class="swift-index-latest-log">
				<p><strong><?php esc_html_e('Date:', 'swift-index'); ?></strong> <?php echo esc_html(get_date_from_gmt($latest_log->notified_at, get_option('date_format') . ' ' . get_option('time_format'))); ?></p>
				<p><strong><?php esc_html_e('Type:', 'swift-index'); ?></strong> <?php echo esc_html($latest_log->notification_type); ?></p>
				<p><strong><?php esc_html_e('Status:', 'swift-index'); ?></strong> <span class="status-<?php echo esc_attr(strtolower(str_replace(' ', '-', sanitize_html_class($latest_log->status_code)))); ?>"><?php echo esc_html($latest_log->status_code); ?></span></p>
				<?php if (!empty($latest_log->response_message)) : ?>
				<p><strong><?php esc_html_e('Message:', 'swift-index'); ?></strong><br><small><?php echo esc_html(wp_trim_words($latest_log->response_message, 30, '...')); ?></small></p>
				<?php endif; ?>
				<?php
				if (current_user_can('manage_options')) {
					$log_page_url = admin_url('admin.php?page=swift-index-main&tab=logs');
					echo '<p style="margin-top:10px;"><a href="' . esc_url($log_page_url) . '" target="_blank">' . esc_html__('View All Logs', 'swift-index') . '</a></p>';
				}
				?>
			</div>
			<?php
		else :
			?>
			<p><?php esc_html_e('No notification history found for this post.', 'swift-index'); ?></p>
			<?php
		endif;
		?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Saves the 'Notify Google Indexing API' meta data when a post is saved.
 *
 * Verifies nonce, checks for autosaves, and user permissions before updating
 * the '_swift_index_send_notification' post meta.
 * Hooked to 'save_post'.
 *
 * @since 1.0.0
 * @param int $post_id The ID of the post being saved.
 * @return void
 */
function swift_index_save_meta_box_data($post_id) {
	if (!isset($_POST['swift_index_meta_box_nonce']) || !wp_verify_nonce(sanitize_key($_POST['swift_index_meta_box_nonce']), 'swift_index_save_meta_box_data')) {
		return;
	}

	// Ignore autosaves
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	// Check user permissions
	$post_type = get_post_type($post_id);
	if (!$post_type) return;
	$post_type_object = get_post_type_object($post_type);
	if (!$post_type_object || !current_user_can($post_type_object->cap->edit_post, $post_id)) {
		return;
	}

	// Save the meta field
	if (isset($_POST['swift_index_send_notification']) && $_POST['swift_index_send_notification'] === 'yes') {
		update_post_meta($post_id, '_swift_index_send_notification', 'yes');
	} else {
		update_post_meta($post_id, '_swift_index_send_notification', 'no');
	}
}
add_action('save_post', 'swift_index_save_meta_box_data', 10, 1);

/**
 * Retrieves and formats a user-friendly summary of the latest Indexing API
 * notification log for a given post ID.
 *
 * This summary includes a human-readable status (Success, Failed, etc.),
 * and a title attribute with more details (notification type, time, original status code).
 * Used for display in the admin list table's date column.
 * The output HTML of this function can be customized via the 'swift_index_admin_list_log_summary_html' filter.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $post_id The post ID.
 * @return string HTML string of the log summary, or 'Indexing API: No log yet' message.
 *
 * @filter swift_index_admin_list_log_summary_html Allows customization of the log summary HTML.
 * Passes the generated HTML, post ID, and the latest log object (or null if no log).
 */
function swift_index_get_latest_log_summary_for_admin_list($post_id) {

	if (empty($post_id)) {
		return '';
	}

	global $wpdb;
	$table_name = swift_index_get_log_table_name();

	$sql_query_format = "SELECT notified_at, notification_type, status_code, response_message FROM %s WHERE post_id = %%d ORDER BY notified_at DESC LIMIT 1";

	$sql_template_for_prepare = sprintf($sql_query_format, $table_name);

	$prepared_sql = $wpdb->prepare(
		$sql_template_for_prepare,
		$post_id
	);

	if ($prepared_sql) {
		$latest_log = $wpdb->get_row($prepared_sql);
	}

	$output_html = '';

	if ($latest_log) {
		$log_time_formatted = get_date_from_gmt($latest_log->notified_at, 'Y/m/d H:i');
		$original_status_code = $latest_log->status_code;
		$notification_type = $latest_log->notification_type;
		$response_message_short = wp_trim_words($latest_log->response_message, 15, '...');

		$display_status_text = '';
		$status_css_class = 'status-info';

		// Determine display text and CSS class based on status code
		if (is_numeric($original_status_code)) {
			$numeric_status_code = intval($original_status_code);
			if ($numeric_status_code >= 200 && $numeric_status_code < 300) {
				$display_status_text = __('Success', 'swift-index');
				$status_css_class = 'status-success';
			} elseif ($numeric_status_code >= 400 && $numeric_status_code < 500) {
				$display_status_text = __('Failed (Client Error)', 'swift-index');
				$status_css_class = 'status-error';
			} elseif ($numeric_status_code >= 500) {
				$display_status_text = __('Failed (Server Error)', 'swift-index');
				$status_css_class = 'status-error';
			} else {
				$display_status_text = $original_status_code;
			}
		} else {
			switch (strtoupper($original_status_code)) {
				case '200 OK':
					$display_status_text = __('Success', 'swift-index');
					$status_css_class = 'status-success';
					break;
				case 'CONFIG_ERROR':
				case 'JSON_ERROR':
					$display_status_text = __('Configuration Error', 'swift-index');
					$status_css_class = 'status-warning';
					break;
				case 'TOKEN_ERROR':
				case 'AUTH_EXCEPTION':
				case 'GENERAL_EXCEPTION_AUTH':
				case 'TOKEN_UNAVAILABLE':
				case '401':
				case '403':
					$display_status_text = __('Auth/Permission Error', 'swift-index');
					$status_css_class = 'status-error';
					break;
				case 'WP_REMOTE_ERROR':
					$display_status_text = __('Network Error', 'swift-index');
					$status_css_class = 'status-error';
					break;
				default:
					$display_status_text = __('Failed (Unknown)', 'swift-index');
					$status_css_class = 'status-error';
			}
		}

		// Assemble information for the title attribute
		$title_parts = array();

		// translators: %s: The type of notification (e.g., URL_UPDATED, URL_DELETED).
		$title_parts[] = sprintf(__('Type: %s', 'swift-index'), esc_html($notification_type));

		// translators: %s: The formatted timestamp of the log entry.
		$title_parts[] = sprintf(__('Time: %s', 'swift-index'), esc_html($log_time_formatted));

		// translators: %s: The HTTP status code returned by the API.
		$title_parts[] = sprintf(__('Status Code: %s', 'swift-index'), esc_html($original_status_code));

		if (!empty($response_message_short) && $display_status_text !== __('Success', 'swift-index')) {
			// translators: %s: A short description of the response or error details.
			$title_parts[] = sprintf(__('Details: %s', 'swift-index'), esc_html($response_message_short));
		}
		$title_attribute = implode(' | ', $title_parts);

		$output_html = sprintf(
			'<small class="swift-index-log-summary">Indexing API: <span class="%s" title="%s">%s</span></small>',
			esc_attr($status_css_class),
			esc_attr($title_attribute),
			esc_html($display_status_text)
		);
	} else {
		$output_html = '<small class="swift-index-log-summary">' . esc_html__('Indexing API: Not sent yet', 'swift-index') . '</small>';
	}

	/**
	 * Filters the HTML output of the latest log summary for a post in admin list tables.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $output_html The generated HTML for the log summary.
	 * @param int         $post_id     The ID of the current post.
	 * @param object|null $latest_log  The latest log entry object (stdClass from $wpdb->get_row)
	 * or null if no log entry was found.
	 */
	return apply_filters('swift_index_admin_list_log_summary_html', $output_html, $post_id, $latest_log);
}

/**
 * Handles the API notification when a post is published or updated.
 *
 * Checks if the post type is targeted and if the individual post is set to notify
 * before calling the main notification function.
 * Hooked to 'save_post'.
 *
 * @since 1.0.0
 * @param int     $post_id The ID of the post being saved.
 * @param WP_Post $post    The post object.
 * @param bool    $update  Whether this is an existing post being updated or not.
 * @return void
 */
function swift_index_handle_post_update( $post_id, $post, $update ) {

	// Ignore autosaves, revisions, and non-published posts
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && wp_is_post_autosave($post_id)) return;
	if (wp_is_post_revision($post_id)) return;
	if (!is_object($post) || $post->post_status !== 'publish') return;

	// Check if post type is targeted
	$target_post_types = get_option('swift_index_target_post_types', array());
	if (empty($target_post_types) || !in_array($post->post_type, $target_post_types, true)) {
		return;
	}

	// Check individual post setting for notification
	$send_notification_meta = get_post_meta($post_id, '_swift_index_send_notification', true);
	if ($send_notification_meta === 'no') {
		return;
	}

	$url_to_notify = get_permalink($post_id);
	if ($url_to_notify) {
		$result = swift_index_send_notification($url_to_notify, 'URL_UPDATED');
		if (is_wp_error($result)) {
			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
				error_log(
					sprintf(
						'[Swift Index] Error sending URL_UPDATED notification for %s: %s',
						esc_url_raw($url_to_notify),
						$result->get_error_message()
					)
				);
			}
		}
	}
}
add_action('save_post', 'swift_index_handle_post_update', 10, 3);

/**
 * Handles the API notification when a post is moved to the trash.
 *
 * Checks if the post type is targeted and if the individual post was set to notify
 * before calling the main notification function with 'URL_DELETED' type.
 * Hooked to 'wp_trash_post'.
 *
 * @since 1.0.0 // Assuming refactored from an earlier version
 * @param int $post_id The ID of the post being trashed.
 * @return void
 */
function swift_index_handle_post_trash( $post_id ) {
	$post = get_post($post_id);
	if (!$post) return;

	// Check if post type is targeted
	$target_post_types = get_option('swift_index_target_post_types', array());
	if (empty($target_post_types) || !in_array($post->post_type, $target_post_types, true)) {
		return;
	}

	// Check individual post setting
	$send_notification_meta = get_post_meta($post_id, '_swift_index_send_notification', true);
	if ($send_notification_meta === 'no') {
		return;
	}

	$url_to_notify = get_permalink($post_id);
	if ($url_to_notify) {
		$result = swift_index_send_notification($url_to_notify, 'URL_DELETED');
		if (is_wp_error($result)) {
			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
				error_log(
					sprintf(
						'[Swift Index] Error sending URL_DELETED notification for %s: %s',
						esc_url_raw($url_to_notify),
						$result->get_error_message()
					)
				);
			}
		}
	}
}
add_action('wp_trash_post', 'swift_index_handle_post_trash');

/**
 * Adds the log summary to the date column display in admin list tables
 * using the 'post_date_column_time' filter.
 *
 * This function checks if the current post type is targeted for notifications
 * before appending the log summary to the date/time output.
 *
 * @since 1.0.0
 * @param string  $date_output Original date/time string output from the filter.
 * @param WP_Post $post        Current post object.
 * @param string  $column_name Current column name (expected to be 'date' for this filter).
 * @return string Modified date/time string with log summary appended if applicable.
 */
function swift_index_add_log_to_date_via_filter($date_output, $post, $column_name = 'date') {

	if (!($post instanceof WP_Post) || empty($post->ID)) {
		return $date_output;
	}

	$target_post_types = get_option('swift_index_target_post_types', array());
	if (!in_array($post->post_type, $target_post_types, true)) {
		return $date_output;
	}

	$log_summary = swift_index_get_latest_log_summary_for_admin_list($post->ID);
	if ($log_summary) {
		$date_output .= '<br>' . $log_summary;
	}
	return $date_output;
}

/**
 * Registers the filter to add log summary to the date column for targeted post types.
 *
 * Hooks into 'admin_init' to add the filter. The filter itself should ideally be
 * added only once if it's global, and the callback will handle post type checks.
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_register_date_column_filter_for_targeted_types() {
	$target_post_types = get_option('swift_index_target_post_types', array());
	if (is_array($target_post_types) && !empty($target_post_types)) {
		if (!has_filter('post_date_column_time', 'swift_index_add_log_to_date_via_filter')) {
			add_filter('post_date_column_time', 'swift_index_add_log_to_date_via_filter', 10, 3);
		}
	}
}
add_action('admin_init', 'swift_index_register_date_column_filter_for_targeted_types');

?>
