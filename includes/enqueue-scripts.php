<?php
/**
 * Swift Index Admin Scripts Enqueue
 *
 * This file handles the enqueuing of admin-specific CSS and JavaScript
 * files required for the Swift Index plugin's settings and list table enhancements.
 *
 * @package Swift_Index
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues admin scripts and styles for the Swift Index plugin.
 *
 * This function checks the current admin screen and conditionally enqueues
 * CSS for both the plugin settings page and relevant post list tables.
 * JavaScript files (and localized data) are enqueued only for the plugin settings page.
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix The hook suffix of the current admin page.
 * @return void
 */
function swift_index_admin_scripts($hook_suffix) {
	$current_screen = get_current_screen();
	if (!$current_screen) {
		return;
	}

	$load_assets = false;

	$settings_page_slug = 'swift-index-main';
	$expected_settings_screen_id = 'settings_page_' . $settings_page_slug;

	if ($current_screen->id === $expected_settings_screen_id) {
		$load_assets = true;
	}

	$target_post_types = get_option('swift_index_target_post_types', array());
	if ($current_screen->base === 'edit' && is_array($target_post_types) && in_array($current_screen->post_type, $target_post_types)) {
		$load_assets = true;
	}

	if ($load_assets) {
		wp_enqueue_style(
			'swift-index-admin-styles',
			SWIFT_INDEX_PLUGIN_URL . 'css/swift-index-admin.css',
			array(),
			SWIFT_INDEX_VERSION
		);

		if ($current_screen->id === $expected_settings_screen_id) {
			wp_enqueue_script(
				'swift-index-admin-js',
				SWIFT_INDEX_PLUGIN_URL . 'js/swift-index-admin.js',
				array('jquery'),
				SWIFT_INDEX_VERSION,
				true
			);
			wp_localize_script(
				'swift-index-admin-js',
				'swiftIndexAdminParams',
				array(
					'delete_logs_confirm_message' => __('Are you sure you want to delete all notification logs? This action cannot be undone.', 'swift-index')
				)
			);
		}
	}
}
