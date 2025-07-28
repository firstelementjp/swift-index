<?php
/**
 * Swift Index Admin Settings
 *
 * This file handles the creation of the admin settings page, registration of settings,
 * and rendering of the various form fields used to configure the Swift Index plugin.
 *
 * @package Swift_Index
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the Swift Index admin menu page under the WordPress 'Settings' menu.
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_add_admin_menu() {
	add_options_page(
		__('Swift Index', 'swift-index'),
		__('Swift Index', 'swift-index'),
		'manage_options',
		'swift-index-main',
		'swift_index_tabbed_page_html'
	);
}

/**
 * Initializes the plugin settings, registers sections, and fields.
 *
 * Hooked to 'admin_init'.
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_settings_init() {
	register_setting(
		'swift_index_settings',
		'swift_index_service_account_json',
		array(
			'type'              => 'string',
			'description'       => __('Service Account JSON key for Swift Index plugin.', 'swift-index'),
			'sanitize_callback' => 'swift_index_sanitize_service_account_json_callback',
			'default'           => '',
		)
	);
	register_setting(
		'swift_index_settings',
		'swift_index_target_post_types',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'swift_index_sanitize_post_types_cb',
			'default'           => array(),
		)
	);
	register_setting(
		'swift_index_settings',
		'swift_index_log_rotation_type',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'swift_index_sanitize_log_rotation_type',
			'default'           => 'days',
		)
	);
	register_setting(
		'swift_index_settings',
		'swift_index_log_rotation_value_days', // Option for days
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 30,
		)
	);
	register_setting(
		'swift_index_settings',
		'swift_index_log_rotation_value_count', // Option for count
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1000,
		)
	);
	register_setting(
		'swift_index_settings',
		'swift_index_delete_data_on_uninstall',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'swift_index_sanitize_delete_data_on_uninstall',
			'default'           => 'no',
		)
	);

	// API Credentials Section
	add_settings_section(
		'swift_index_section_credentials',
		__('API Credentials', 'swift-index'),
		null,
		'swift_index_settings'
	);
	add_settings_field(
		'swift_index_service_account_json_field',
		__('Service Account JSON', 'swift-index'),
		'swift_index_service_account_json_field_cb',
		'swift_index_settings',
		'swift_index_section_credentials',
		array(
			'label_for' => 'swift_index_service_account_json_textarea',
		)
	);

	// Notification Settings Section
	add_settings_section(
		'swift_index_section_notification_settings',
		__('Notification Settings', 'swift-index'),
		null,
		'swift_index_settings'
	);
	add_settings_field(
		'swift_index_target_post_types',
		__('Target Post Types', 'swift-index'),
		'swift_index_target_post_types_field_cb',
		'swift_index_settings',
		'swift_index_section_notification_settings'
	);
	add_settings_field(
		'swift_index_log_rotation_settings',
		__('Log Rotation', 'swift-index'),
		'swift_index_log_rotation_settings_field_cb',
		'swift_index_settings',
		'swift_index_section_notification_settings'
	);
	add_settings_field(
		'swift_index_delete_data_on_uninstall',
		__('Data on Uninstall', 'swift-index'),
		'swift_index_delete_data_on_uninstall_field_cb',
		'swift_index_settings',
		'swift_index_section_notification_settings'
	);

	add_filter('pre_update_option_swift_index_log_rotation_value_days', 'swift_index_conditional_update_log_rotation_value', 10, 3);
	add_filter('pre_update_option_swift_index_log_rotation_value_count', 'swift_index_conditional_update_log_rotation_value', 10, 3);
}

/**
 * Sanitizes the Service Account JSON input before saving to the database.
 *
 * @param string $input The JSON string submitted by the user.
 * @return string The sanitized JSON string, or the old value if the input is invalid.
 */
function swift_index_sanitize_service_account_json_callback($input) {

	$input = trim($input);

	// If the input is empty, allow it (e.g., to clear the option)
	if (empty($input)) {
		return '';
	}

	$decoded_json = json_decode($input, true); // "true" decodes as an associative array

	if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_json)) {
		// If JSON is invalid, add an error message and return the original value
		add_settings_error(
			'swift_index_service_account_json',
			'invalid_json',
			// translators: Error message when the provided service account JSON is not valid.
			__('The provided Service Account JSON is not valid. Please check the format and try again.', 'swift-index'),
			'error'
		);
		// If the input is invalid, return the existing option value (to prevent overwriting)
		return get_option('swift_index_service_account_json', '');
	}

	$required_keys = array('type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id');
	foreach ($required_keys as $key) {
		if (!isset($decoded_json[$key]) || empty($decoded_json[$key])) {
			add_settings_error(
				'swift_index_service_account_json',
				'missing_json_keys',
				// translators: %s: Name of the missing key (e.g., private_key).
				sprintf(__('The Service Account JSON appears to be missing a required field: %s. Please provide a complete JSON key.', 'swift-index'), esc_html($key)),
				'error'
			);
			// If the input is invalid, return the existing option value
			return get_option('swift_index_service_account_json', '');
		}
	}

	// Confirm that 'type' is 'service_account'
	if (!isset($decoded_json['type']) || $decoded_json['type'] !== 'service_account') {
		add_settings_error(
			'swift_index_service_account_json',
			'not_service_account_type',
			// translators: Error message when the JSON type field is not 'service_account'.
			__('The provided JSON does not seem to be of type "service_account". Please check the key.', 'swift-index'),
			'error'
		);
		// If the input is invalid, return the existing option value
		return get_option('swift_index_service_account_json', '');
	}

	// If all checks pass, return the original input string (trimmed).
	return $input;
}

/**
 * Conditionally updates log rotation values ('days' or 'count') based on the selected rotation type.
 *
 * This prevents the value of a disabled input field (for the non-selected rotation type)
 * from being reset to 0 or its default when the settings are saved. Instead, it preserves
 * the previously saved value for the non-active rotation type.
 *
 * @param mixed  $new_value   The new value for the option, typically after sanitization (e.g., absint).
 * @param mixed  $old_value   The old value of the option from the database.
 * @param string $option_name The name of the option being updated.
 * @return mixed The value that should actually be saved to the database.
 */
function swift_index_conditional_update_log_rotation_value($new_value, $old_value, $option_name) {
	$nonce_action = 'swift_index_save_settings_action';
	$nonce_name   = 'swift_index_settings_nonce';

	$use_post_data = false;
	if (isset($_POST[$nonce_name]) && wp_verify_nonce(sanitize_key($_POST[$nonce_name]), $nonce_action)) {
		$use_post_data = true;
	}

	// Prioritize verified POST data, otherwise fall back to the current database option.
	$selected_rotation_type = get_option('swift_index_log_rotation_type', 'latest_per_post');
	if ($use_post_data && isset($_POST['swift_index_log_rotation_type'])) {
		$selected_rotation_type = sanitize_key($_POST['swift_index_log_rotation_type']);
	}

	if ($option_name === 'swift_index_log_rotation_value_days') {
		// If 'days' is not the currently selected rotation type, keep the old value.
		if ($selected_rotation_type !== 'days') {
			return $old_value;
		}

		// If 'days' is selected, and we have verified POST data for its value, use it.
		if ($use_post_data && isset($_POST['swift_index_log_rotation_value_days'])) {
			return absint($_POST['swift_index_log_rotation_value_days']);
		}
		// Otherwise, keep the old value.
		return $old_value;
	}

	if ($option_name === 'swift_index_log_rotation_value_count') {
		// If 'count' is not the currently selected rotation type, keep the old value.
		if ($selected_rotation_type !== 'count') {
			return $old_value;
		}

		// If 'count' is selected, and we have verified POST data for its value, use it.
		if ($use_post_data && isset($_POST['swift_index_log_rotation_value_count'])) {
			return absint($_POST['swift_index_log_rotation_value_count']);
		}
		// Otherwise, keep the old value.
		return $old_value;
	}

	// As a fallback, return $new_value.
	return $new_value;
}

/**
 * Renders the textarea field for the Service Account JSON.
 *
 * This function is a callback for add_settings_field() to display the
 * input field where users can paste their Google Service Account JSON key.
 *
 * @since 1.0.0
 *
 * @param array $args Arguments passed by add_settings_field(). Expected to contain 'label_for'.
 * @return void
 */
function swift_index_service_account_json_field_cb($args) {
	$option = get_option('swift_index_service_account_json');
	$textarea_id = isset($args['label_for']) ? $args['label_for'] : 'swift_index_service_account_json_textarea';
	?>
	<textarea id="<?php echo esc_attr($textarea_id); ?>"
			  name="swift_index_service_account_json"
			  rows="10"
			  cols="50"
			  class="large-text code"
			  placeholder="<?php esc_attr_e('Paste the entire content of your JSON key file here.', 'swift-index'); ?>"><?php echo esc_textarea($option); ?></textarea>
	<p class="description">
		<?php esc_html_e('Paste the content of your Google Service Account JSON key file here.', 'swift-index'); ?>
	</p>
	<p class="description" style="margin-top: 0.5em;">
		<strong><?php esc_html_e('Important Setup Required:', 'swift-index'); ?></strong><br>
		<?php
		$setup_guide_tab_text = esc_html__('Setup Guide', 'swift-index');
		$setup_guide_link_html = sprintf(
			'<a href="#setup-guide" class="swift-index-internal-tab-link">%s</a>',
			$setup_guide_tab_text
		);

		printf(
			/* Translators: %s: HTML link to the Setup Guide tab. */
			esc_html__('For this plugin to work correctly, you must also configure the Indexing API in your Google Cloud Console and link the Service Account to your verified property in Google Search Console. Please refer to the %s tab for detailed instructions.', 'swift-index'),
			wp_kses_post($setup_guide_link_html)
		);
		?>
	</p>
	<?php
}

/**
 * Renders the checkboxes for selecting target post types.
 *
 * Callback for add_settings_field().
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_target_post_types_field_cb() {
	$saved_target_post_types = get_option('swift_index_target_post_types', array());
	$post_types = get_post_types(array('public' => true, 'show_ui' => true), 'objects');
	$excluded_post_types = array('attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation');

	if ($post_types) {
		echo '<fieldset>';
		foreach ($post_types as $post_type) {
			if (in_array($post_type->name, $excluded_post_types, true)) {
				continue;
			}

			echo '<label style="margin-right: 20px; display: inline-block; margin-bottom: 5px;">';
			echo '<input type="checkbox" name="swift_index_target_post_types[]" value="' . esc_attr($post_type->name) . '" ';

			$is_selected = in_array($post_type->name, $saved_target_post_types, true);
			checked($is_selected, true);

			echo ' /> ';
			echo esc_html($post_type->label) . ' (<code>' . esc_html($post_type->name) . '</code>)';
			echo '</label><br />';
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__('Select the post types to automatically notify Google Indexing API for.', 'swift-index') . '</p>';
	} else {
		echo '<p>' . esc_html__('No eligible post types found.', 'swift-index') . '</p>';
	}
}

/**
 * Renders the fields for log rotation settings (type and value).
 *
 * Callback for add_settings_field().
 * Includes JavaScript for a better UX with radio buttons and number inputs.
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_log_rotation_settings_field_cb() {
	$rotation_type = get_option('swift_index_log_rotation_type', 'latest_per_post');
	$rotation_value_days = get_option('swift_index_log_rotation_value_days', 30);
	$rotation_value_count = get_option('swift_index_log_rotation_value_count', 1000);
	?>
	<fieldset>
		<legend class="screen-reader-text"><span><?php esc_html_e('Log Rotation Settings', 'swift-index'); ?></span></legend>
		<p>
			<label>
				<input type="radio" name="swift_index_log_rotation_type" value="latest_per_post" <?php checked($rotation_type, 'latest_per_post'); ?> />
				<?php esc_html_e('Keep only the latest log entry for each post', 'swift-index'); ?>
			</label>
			<span class="description" style="display: block; margin-left: 22px;"><?php esc_html_e('(Recommended for ensuring per-post history is always available)', 'swift-index'); ?></span>
		</p>
		<p>
			<label>
				<input type="radio" name="swift_index_log_rotation_type" value="days" <?php checked($rotation_type, 'days'); ?> />
				<?php esc_html_e('Retention period (days):', 'swift-index'); ?>
			</label>
			<input type="number" id="swift_index_log_rotation_value_days_input" name="swift_index_log_rotation_value_days" value="<?php echo esc_attr($rotation_value_days); ?>" class="small-text" min="1" step="1" <?php if ($rotation_type !== 'days') echo 'disabled'; ?> />
		</p>

		<p>
			<label>
				<input type="radio" name="swift_index_log_rotation_type" value="count" <?php checked($rotation_type, 'count'); ?> />
				<?php esc_html_e('Number of logs to keep (items):', 'swift-index'); ?>
			</label>
			<input type="number" id="swift_index_log_rotation_value_count_input" name="swift_index_log_rotation_value_count" value="<?php echo esc_attr($rotation_value_count); ?>" class="small-text" min="1" step="1" <?php if ($rotation_type !== 'count') echo 'disabled'; ?> />
		</p>
		<p class="description">
			<?php esc_html_e('Select the method for log rotation. Older or excess logs will be automatically deleted.', 'swift-index'); ?>
		</p>
	 </fieldset>

	 <script type="text/javascript">
		jQuery(document).ready(function($) {
			var daysInput = $('#swift_index_log_rotation_value_days_input');
			var countInput = $('#swift_index_log_rotation_value_count_input');

			function toggleInputs(type) {
				daysInput.prop('disabled', type !== 'days');
				countInput.prop('disabled', type !== 'count');
			}

			var initialType = $('input[name="swift_index_log_rotation_type"]:checked').val();
			toggleInputs(initialType);

			$('input[name="swift_index_log_rotation_type"]').on('change', function() {
				toggleInputs($(this).val());
			});
		});
	</script>
	<?php
}

/**
 * Renders the checkbox field for deleting data on uninstall.
 *
 * Callback for add_settings_field().
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_delete_data_on_uninstall_field_cb() {
	$option_value = get_option('swift_index_delete_data_on_uninstall', 'no');
	?>
	<fieldset>
		<legend class="screen-reader-text"><span><?php esc_html_e('Data Handling on Uninstall', 'swift-index'); ?></span></legend>
		<label for="swift_index_delete_data_on_uninstall_checkbox">
			<input type="checkbox" id="swift_index_delete_data_on_uninstall_checkbox" name="swift_index_delete_data_on_uninstall" value="yes" <?php checked($option_value, 'yes'); ?> />
			<?php esc_html_e('Delete all plugin data (settings, logs) from the database when the plugin is deleted.', 'swift-index'); ?>
		</label>
		<p class="description">
			<strong><?php esc_html_e('Warning:', 'swift-index'); ?></strong> <?php esc_html_e('If you check this box, all Swift Index settings and notification logs will be permanently removed from your database when you delete the plugin. This action cannot be undone. If you plan to reinstall the plugin later and wish to keep your data, leave this unchecked.', 'swift-index'); ?>
		</p>
	</fieldset>
	<?php
}

/**
 * Sanitizes the 'delete data on uninstall' option.
 *
 * @since 1.0.0
 * @param string|null $input The input from the checkbox.
 * @return string 'yes' or 'no'.
 */
function swift_index_sanitize_delete_data_on_uninstall($input) {
	return ($input === 'yes') ? 'yes' : 'no';
}

/**
 * Sanitizes the log rotation type option.
 *
 * @since 1.0.0
 * @param string $input The input value for rotation type.
 * @return string 'days' or 'count'.
 */
function swift_index_sanitize_log_rotation_type($input) {
	$valid_types = array('latest_per_post', 'days', 'count');
	if (in_array($input, $valid_types, true)) {
		return $input;
	}
	return 'latest_per_post'; // Default to 'latest_per_post' if invalid input
}

/**
 * Sanitizes the target post types option.
 *
 * Ensures that the input is an array of valid, existing post type slugs.
 *
 * @since 1.0.0
 * @param mixed $input The input from the checkboxes.
 * @return array Sanitized array of post type slugs.
 */
function swift_index_sanitize_post_types_cb($input) {
	$sanitized_input = array();
	if (is_array($input)) {
		foreach ($input as $post_type) {
			$sanitized_post_type = sanitize_key($post_type);
			if (post_type_exists($sanitized_post_type)) {
				$sanitized_input[] = $sanitized_post_type;
			}
		}
	}
	return $sanitized_input;
}

/**
 * Renders the main tabbed settings page for Swift Index.
 *
 * This page includes tabs for 'Settings' and 'Notification Logs'.
 *
 * @since 1.0.0
 * @return void
 */
function swift_index_tabbed_page_html() {
	?>
	<div class="wrap" id="swift-index-wrap">
		<h1><?php esc_html_e('Swift Index', 'swift-index'); ?></h1>

		<h2 class="nav-tab-wrapper">
			<a href="#settings" class="nav-tab nav-tab-active" data-tab-content="settings-content"><?php esc_html_e('Settings', 'swift-index'); ?></a>
			<a href="#logs" class="nav-tab" data-tab-content="logs-content"><?php esc_html_e('Notification Logs', 'swift-index'); ?></a>
			<a href="#setup-guide" class="nav-tab" data-tab-content="setup-guide-content"><?php esc_html_e('Setup Guide', 'swift-index'); ?></a>
		</h2>

		<?php // Settings Tab Content ?>
		<div id="settings-content" class="tab-content-panel active-tab-content">
			<form action="options.php" method="post">
				<?php
				settings_fields('swift_index_settings');
				do_settings_sections('swift_index_settings');
				submit_button(__('Save Settings', 'swift-index'));
				?>
			</form>
		</div>

		<?php // Logs Tab Content ?>
		<div id="logs-content" class="tab-content-panel" style="display: none;">
			<h2><?php esc_html_e('Notification Logs', 'swift-index'); ?></h2>
			<?php
			if (!class_exists('WP_List_Table')) {
				require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
			}
			if (!class_exists('Swift_Index_Log_List_Table')) {
				$class_file_path = SWIFT_INDEX_PLUGIN_DIR . 'includes/class-swift-index-log-list-table.php';
				if (file_exists($class_file_path)) {
					require_once $class_file_path;
				} else {
					echo '<p>' . esc_html__('Error: Log table class file not found.', 'swift-index') . '</p>';
				}
			}
			if (class_exists('Swift_Index_Log_List_Table')) {
				$log_list_table = new Swift_Index_Log_List_Table();
				$log_list_table->prepare_items();
				?>
				<form method="get">
					<input type="hidden" name="page" value="<?php echo isset($_REQUEST['page']) ? esc_attr(sanitize_key($_REQUEST['page'])) : ''; ?>" />
					<?php
					$log_list_table->search_box(__('Search Logs', 'swift-index'), 'swift-index-log-search');
					$log_list_table->display();
					?>
				</form>
				<div style="margin-top: 20px; clear: both;">
					<?php $delete_logs_url = admin_url('admin-post.php'); ?>
					<form method="post" action="<?php echo esc_url($delete_logs_url); ?>" id="swift-index-delete-logs-form">
						<input type="hidden" name="action" value="swift_index_delete_all_logs_action">
						<?php wp_nonce_field('swift_index_delete_all_logs_nonce_action', 'swift_index_delete_all_logs_nonce'); ?>
						<?php
						submit_button(
							__('Delete All Notification Logs', 'swift-index'),
							'secondary delete',
							'swift-index-delete-logs-submit',
							true,
							array('id' => 'swift-index-delete-logs-button')
						);
						?>
					</form>
				</div>
			<?php
			}
			?>
		</div>

		<?php // Setup Guide Tab Content ?>
		<div id="setup-guide-content" class="tab-content-panel" style="display: none;">
			<h2><?php esc_html_e('Setup Guide: Google API & Search Console', 'swift-index'); ?></h2>
			<p><?php esc_html_e('To use the Swift Index plugin, you need to set up a Google Cloud Project, enable the Indexing API, create a Service Account JSON key, and then add that Service Account as an owner in your Google Search Console property.', 'swift-index'); ?></p>

			<h3><?php esc_html_e('A. Google Cloud Platform (GCP) Setup', 'swift-index'); ?></h3>
			<ol>
				<li><?php
					printf(
						wp_kses_post(
							// translators: %s: The URL for the Google Cloud Console.
							__('Go to the <a href="%s" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>.', 'swift-index')
						),
						'https://console.cloud.google.com/'
					); ?></li>
				<li><?php esc_html_e('Create a new project or select an existing one.', 'swift-index'); ?></li>
				<li><strong><?php esc_html_e('Enable the Indexing API:', 'swift-index'); ?></strong> <?php esc_html_e('In the API Library, search for "Indexing API" and enable it for your project.', 'swift-index'); ?></li>
				<li><strong><?php esc_html_e('Create a Service Account:', 'swift-index'); ?></strong>
					<ol style="list-style-type: lower-alpha; margin-left: 20px;">
						<li><?php esc_html_e('Go to "IAM & Admin" > "Service Accounts".', 'swift-index'); ?></li>
						<li><?php esc_html_e('Click "Create Service Account".', 'swift-index'); ?></li>
						<li><?php esc_html_e('Fill in a service account name (e.g., "Swift Index Service"). The Service account ID will be generated automatically.', 'swift-index'); ?></li>
						<li><?php esc_html_e('Click "Create and Continue".', 'swift-index'); ?></li>
						<li><?php esc_html_e('For "Grant this service account access to project", select the role "Owner". (Note: While selecting the "Owner" role in GCP simplifies setup, it grants broad permissions. Advanced users can configure custom roles with more limited access. Regardless, the Indexing API requires your Service Account to be added as an "Owner" to your verified property in Google Search Console.).', 'swift-index'); ?></li>
						<li><?php esc_html_e('Click "Continue", then click "Done".', 'swift-index'); ?></li>
					</ol>
				</li>
				<li><strong><?php esc_html_e('Create a Service Account Key (JSON):', 'swift-index'); ?></strong>
					<ol style="list-style-type: lower-alpha; margin-left: 20px;">
						<li><?php esc_html_e('Find your newly created service account in the list.', 'swift-index'); ?></li>
						<li><?php esc_html_e('Click on the service account\'s email address (or click the three dots menu and select "Manage keys").', 'swift-index'); ?></li>
						<li><?php esc_html_e('Navigate to the "KEYS" tab.', 'swift-index'); ?></li>
						<li><?php esc_html_e('Click "ADD KEY" > "Create new key".', 'swift-index'); ?></li>
						<li><?php esc_html_e('Choose "JSON" as the key type and click "CREATE".', 'swift-index'); ?></li>
						<li><?php
							$text = __('A JSON file will be downloaded to your computer. <strong>Keep this file secure and confidential.</strong> You will need its content for the plugin settings.', 'swift-index');
							echo wp_kses_post($text);
						?></li>
					</ol>
				</li>
			</ol>

			<h3><?php esc_html_e('B. Google Search Console Setup', 'swift-index'); ?></h3>
			<ol>
				<li><?php
				printf(
					wp_kses_post(
						// translators: %s: The URL for Google Search Console.
						__('Go to <a href="%s" target="_blank" rel="noopener noreferrer">Google Search Console</a>.', 'swift-index')
					),
					'https://search.google.com/search-console/'
				); ?></li>
				<li><?php esc_html_e('Ensure your website (e.g., yourdomain.com) is added as a property and that you have verified ownership.', 'swift-index'); ?></li>
				<li><?php esc_html_e('For your property, go to "Settings" (usually at the bottom of the left sidebar).', 'swift-index'); ?></li>
				<li><?php esc_html_e('Click on "Users and permissions".', 'swift-index'); ?></li>
				<li><?php esc_html_e('Click the "ADD USER" button (usually top right).', 'swift-index'); ?></li>
				<li><?php esc_html_e('In the "Email address" field, paste the email address of the Service Account you created in GCP (it looks like your-service-account-name@your-project-id.iam.gserviceaccount.com).', 'swift-index'); ?></li>
				<li><?php esc_html_e('Set the "Permission" to "Owner". This is required for the Indexing API to work.', 'swift-index'); ?></li>
				<li><?php esc_html_e('Click "ADD".', 'swift-index'); ?></li>
			</ol>

			<h3><?php esc_html_e('C. Plugin Configuration', 'swift-index'); ?></h3>
			<p><?php esc_html_e('Once you have the JSON key file and have configured Search Console:', 'swift-index'); ?></p>
			<ol>
				<li><?php esc_html_e('Navigate to the "Settings" tab on this plugin page.', 'swift-index'); ?></li>
				<li><?php esc_html_e('Open the downloaded JSON key file in a text editor.', 'swift-index'); ?></li>
				<li><?php esc_html_e('Copy the entire content of the JSON file.', 'swift-index'); ?></li>
				<li><?php esc_html_e('Paste the copied JSON content into the "Service Account JSON" textarea in the plugin settings.', 'swift-index'); ?></li>
				<li><?php esc_html_e('Configure other settings like "Target Post Types" and "Log Rotation" as needed.', 'swift-index'); ?></li>
				<li><?php esc_html_e('Click "Save Settings".', 'swift-index'); ?></li>
			</ol>
			<p><?php esc_html_e('After these steps, the plugin should be ready to notify Google when your content is published or updated!', 'swift-index'); ?></p>
		</div>

	</div>
	<?php
}

/**
 * Displays admin notices stored in a transient.
 *
 * Checks for a transient set by other plugin functions (e.g., after deleting logs)
 * and displays it as a WordPress admin notice. The transient is then deleted.
 *
 * @since 1.0.0
 * @return void
 */
if (!function_exists('swift_index_display_admin_notices')) {
	function swift_index_display_admin_notices() {
		if ($notice = get_transient('swift_index_admin_notice')) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr($notice['type']),
				esc_html($notice['message'])
			);
			delete_transient('swift_index_admin_notice');
		}
	}
	add_action('admin_notices', 'swift_index_display_admin_notices');
}
?>
