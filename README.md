# Swift Index

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/swift-index.svg?style=flat-square)](https://wordpress.org/plugins/swift-index/)
[![WordPress Requires At Least](https://img.shields.io/wordpress/plugin/tested/swift-index.svg?style=flat-square)](https://wordpress.org/plugins/swift-index/)
[![WordPress Tested Up To](https://img.shields.io/wordpress/plugin/tested/swift-index.svg?style=flat-square)](https://wordpress.org/plugins/swift-index/)
[![WordPress Requires PHP](https://img.shields.io/wordpress/plugin/php-version/swift-index.svg?style=flat-square)](https://wordpress.org/plugins/swift-index/)
[![License](https://img.shields.io/badge/License-GPLv3_or_later-blue.svg)](https://www.gnu.org/licenses/gpl-3.0.html)
[![Contributors](https://img.shields.io/badge/Contributors-firstelement%2C%20dxd5001-blue.svg)](https://github.com/firstelementjp/swift-index/graphs/contributors)
[![Donate](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://paypal.me/fejp?country.x=JP&locale.x=ja_JP)

Notify Google instantly for faster indexing of your WordPress content, like job postings and live streams, via the Indexing API.

## Description

Swift Index helps you get your new and updated content crawled more quickly by Google through the official Google Indexing API.
This plugin allows you to:

* **Notify Google Instantly**: Automatically send notifications to Google when posts (of selected types) are published, updated, or deleted.
* **Lightweight & Secure**: Uses the official `google/auth` library for secure OAuth 2.0 authentication with Service Account JSON keys, and WordPress HTTP functions (`wp_remote_post()`) for API calls, aiming for a smaller footprint.
* **Configurable Post Types**: Choose which post types (e.g., posts, pages, job listings, other custom post types) are eligible for API notifications.
* **Per-Post Control**: Enable or disable API notifications for individual posts directly from a meta box in the post editor.
* **Submission Status in Editor**: View the latest API submission status for the current post directly within the post editor meta box.
* **Admin List Table Integration**: See a summary of the latest API submission for each post in the date column of the admin post list tables for targeted post types.
* **Detailed Logging**:
	* Keep track of all API submission attempts (URL, type, status code, response message, timestamp) in a dedicated log table.
	* View, search, sort, and paginate through logs in a user-friendly tab on the plugin's admin page.
* **Log Management**:
	* **Flexible Log Rotation**: Choose your preferred log retention policy: keep only the latest log entry for each individual post (this is the default setting), keep logs for a specific number of days, or limit to a maximum number of total entries.
	* Manually delete all logs with a confirmation step.
* **Data Control on Uninstall**: Option to delete all plugin-specific data (settings and logs) from the database when the plugin is uninstalled.
* **Dependency Scoping**: Utilizes PHP-Scoper to prefix its PHP dependencies, significantly reducing the risk of conflicts with other plugins or themes.
* **Translation Ready**: Fully translatable, with English and Japanese language files included as a starting point.
* **User-Friendly Interface**: Tabbed admin page for easy access to settings and logs.

This plugin is particularly effective for enhancing timely indexing by Google for job posting pages utilizing [JobPosting structured data](https://developers.google.com/search/docs/appearance/structured-data/job-posting) and live streaming event pages with [VideoObject containing a BroadcastEvent](https://developers.google.com/search/docs/appearance/structured-data/video#broadcast-event), especially when this structured data is provided using the JSON-LD format. For these specific types of content, prompt indexing is crucial for maximizing visibility and relevance.

## Development

The development of Swift Index is managed on GitHub. If you are a developer and would like to contribute to the project, report an issue, or follow along with development, please visit our GitHub repository:

* [Swift Index on GitHub](https://github.com/firstelementjp/swift-index)

We welcome bug reports, feature requests, and pull requests.

## Contributing

Want to contribute to Swift Index? That's great!
Please head over to our [GitHub repository](https://github.com/firstelementjp/swift-index) to find out how you can help. We appreciate all contributions, from reporting bugs to submitting new features.

## Installation

1.  **Upload and Install**:
	* Download the `swift-index.zip` file from the [WordPress.org plugin directory](https://wordpress.org/plugins/swift-index/) (once approved) or the [GitHub Releases page](https://github.com/firstelementjp/swift-index/releases).
	* In your WordPress admin panel, go to "Plugins" > "Add New Plugin".
	* Click on "Upload Plugin" and choose the `swift-index.zip` file.
	* Alternatively, unzip the plugin and upload the `swift-index` folder to your `/wp-content/plugins/` directory.
2.  **Activate**: Activate the plugin through the "Plugins" menu in WordPress.
3.  **Initial Setup (Crucial Steps)**:
	After activation, you **must** configure the plugin to connect to the Google Indexing API. This involves several steps in the Google Cloud Platform and Google Search Console.

	* **A. Google Cloud Platform (GCP) Setup**:
		1.  Go to the [Google Cloud Console](https://console.cloud.google.com/).
		2.  Create a new project or select an existing one.
		3.  **Enable the Indexing API**: In the API Library, search for "Indexing API" and [enable it for your project](https://console.cloud.google.com/apis/library/indexing.googleapis.com).
		4.  **Create a Service Account**:
			* Go to "[IAM & Admin](https://console.cloud.google.com/iam-admin)" > "Service Accounts".
			* Click "Create Service Account".
			* Fill in a service account name (e.g., "Swift Index Service").
			* Grant this service account the **"Owner"** role for the project. (Note: For simplicity, "Owner" is suggested for setup. Advanced users might consider a custom role with minimal necessary permissions, though Indexing API often implies broad site access via Search Console delegation).
			* Click "Done".
		5.  **Create a Service Account Key**:
			* Find your newly created service account in the list.
			* Click on the service account's email address.
			* Navigate to the "KEYS" tab.
			* Click "ADD KEY" > "Create new key".
			* Choose "JSON" as the key type and click "CREATE".
			* A JSON file will be downloaded. **Keep this file secure and confidential.**

	* **B. Google Search Console Setup**:
		1.  Go to [Google Search Console](https://search.google.com/search-console/).
		2.  Ensure your website (e.g., `https://yourdomain.com`) is added as a property and that you have **verified ownership**.
		3.  For your property, go to "Settings" (usually at the bottom of the left sidebar).
		4.  Click on "Users and permissions".
		5.  Click the "ADD USER" button.
		6.  In the "Email address" field, paste the **email address of the Service Account** created in GCP (it looks like `your-service-account-name@your-project-id.iam.gserviceaccount.com`).
		7.  Set the "Permission" to **"Owner"**. This is required for the Indexing API.
		8.  Click "ADD".

	* **C. Plugin Configuration**:
		1.  In your WordPress admin, go to "Settings" > "Swift Index".
		2.  On the "Settings" tab, find the "Service Account JSON" textarea.
		3.  Open the JSON key file you downloaded from GCP in a text editor.
		4.  Copy the entire content of the JSON file.
		5.  Paste the copied JSON content into the textarea in the plugin settings.
		6.  Configure other settings as needed: "Target Post Types", "Log Rotation", and "Data on Uninstall".
		7.  Click "Save Settings".

4.  **Test**: Create or update a post of a targeted post type. Check the "Notification Logs" tab in the plugin settings or the meta box in the post editor to see the submission status.

## Frequently Asked Questions

### What are the prerequisites for using Swift Index?
You need a Google Account, a project in Google Cloud Platform with the Indexing API enabled, a Service Account JSON key, and a verified property in Google Search Console where the Service Account is added as an Owner.

### How do I get a Service Account JSON key?
Follow the detailed steps in the "Installation" section (Step 3.A).

### Which content types can be submitted?
You can select any registered post type (posts, pages, custom post types like 'job_listing', etc.) from the plugin's "Target Post Types" setting.

### How do I know if it's working?
1.  Check the "Notification Logs" tab under "Settings" > "Swift Index" in your WordPress admin.
2.  The post edit screen for targeted post types will show a meta box with the latest submission status.
3.  Monitor Google Search Console for crawl activity and indexing status updates (these may not be immediate).

### Does this plugin guarantee faster indexing or better rankings?
Swift Index helps Google discover your new or updated content much faster by using the Indexing API. This can lead to quicker crawling and potentially faster indexing. However, Google makes the final decision on indexing and ranking based on many factors (content quality, site authority, etc.). This plugin facilitates discovery, not ranking.

## Changelog

### 1.0.0
* Initial public release by FirstElement.
* Features:
	* Integration with Google Indexing API using Service Account JSON key (`google/auth` library and `wp_remote_post()`).
	* Automatic notification to Google on publish, update, or deletion of targeted post types.
	* Admin settings page with tabbed UI:
		* Service Account JSON configuration.
		* Selection of target post types for notifications.
		* Configurable log rotation: keep only the latest log for each post (default option), by duration (days), or by total number of entries.
		* Option to delete all plugin data (settings, logs) upon uninstallation.
	* Detailed logging of API submissions (URL, type, status, message, timestamp) in a custom database table.
	* Log viewer with search, sort, and pagination (`WP_List_Table`).
	* Manual "Delete All Logs" button with confirmation.
	* Meta box on post edit screens for targeted post types:
		* Toggle to enable/disable API submission for the individual post.
		* Display of the latest API submission log for that post.
		* Link to full logs page for administrators.
	* Display of latest API submission summary in the date column of admin post list tables for targeted types.
	* PHP dependencies are scoped using PHP-Scoper to prevent conflicts.
	* Translation-ready with initial English and Japanese language files.
	* Developed with contributions from dxd5001.

---
