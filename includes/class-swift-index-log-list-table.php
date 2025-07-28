<?php
/**
 * Swift Index Log List Table Class
 *
 * This file defines the Swift_Index_Log_List_Table class, which extends WP_List_Table
 * to display Indexing API notification logs in a sortable and paginated table
 * within the WordPress admin area.
 *
 * @package Swift_Index
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if ( ! class_exists( 'Swift_Index_Log_List_Table' ) ) :

	/**
	 * Swift_Index_Log_List_Table Class.
	 *
	 * Handles the display of notification logs in a WordPress list table.
	 *
	 * @since 1.0.0
	 * @extends WP_List_Table
	 */
	class Swift_Index_Log_List_Table extends WP_List_Table {

		/**
		 * Constructor.
		 *
		 * Sets the singular and plural Labeled for the list table items.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			parent::__construct([
				// translators: Singular name of a log entry.
				'singular' => __('Log Entry', 'swift-index'),
				// translators: Plural name of log entries.
				'plural'   => __('Log Entries', 'swift-index'),
				'ajax'     => false
			]);
		}

		/**
		 * Defines the columns that are going to be used in the table.
		 *
		 * @since 1.0.0
		 * @return array An associative array of columns.
		 */
		public function get_columns() {
			$columns = [
				'notified_at'     => __('Date / Time', 'swift-index'),
				'notified_url'    => __('Notified URL', 'swift-index'),
				'post_id'         => __('Post ID', 'swift-index'),
				'notification_type' => __('Type', 'swift-index'),
				'status_code'     => __('Status', 'swift-index'),
				'response_message'=> __('Message', 'swift-index'),
			];
			return $columns;
		}

		/**
		 * Default callback to render most columns.
		 *
		 * @since 1.0.0
		 * @param array  $item        The current item's data.
		 * @param string $column_name The name of the current column.
		 * @return string The column's content.
		 */
		public function column_default($item, $column_name) {
			switch ($column_name) {
				case 'notified_at':
					// get_date_from_gmt returns a translated and formatted date string, considered safe.
					return get_date_from_gmt($item['notified_at'], get_option('date_format') . ' ' . get_option('time_format'));
				case 'notified_url':
					return '<a href="' . esc_url($item[$column_name]) . '" target="_blank" rel="noopener noreferrer">' . esc_html(urldecode($item[$column_name])) . '</a>';
				case 'post_id':
					if (!empty($item[$column_name]) && $item[$column_name] > 0) {
						$edit_link = get_edit_post_link((int)$item[$column_name]);
						if ($edit_link) {
							// get_edit_post_link() returns a URL that is already escaped.
							return '<a href="' . esc_url($edit_link) . '">' . esc_html($item[$column_name]) . '</a>';
						}
						return esc_html($item[$column_name]);
					}
					return esc_html__('N/A', 'swift-index'); // Use esc_html__() for direct translatable output
				case 'notification_type':
				case 'status_code':
					return esc_html($item[$column_name]);
				case 'response_message':
					return esc_html(wp_trim_words($item[$column_name], 20, '...'));
				default:
					return '';
			}
		}

		/**
		 * Defines which columns are sortable.
		 *
		 * @since 1.0.0
		 * @return array An associative array of sortable columns.
		 */
		protected function get_sortable_columns() {
			// The array key is the column slug shown in get_columns().
			$sortable_columns = array(
				'notified_at'  => array('notified_at', true), // true means it's already sorted by this column initially (desc)
				'post_id'      => array('post_id', false),
				'status_code'  => array('status_code', false)
			);
			return $sortable_columns;
		}

		/**
		 * Prepares the list of items for display.
		 *
		 * This method queries the database for log entries, handles pagination,
		 * sorting, and search functionality.
		 *
		 * @since 1.0.0
		 * @global wpdb $wpdb WordPress database abstraction object.
		 * @return void
		 */
		public function prepare_items() {
			global $wpdb;

			$table_name = swift_index_get_log_table_name();

			// 1. Set up column headers.
			$this->_column_headers = array(
				$this->get_columns(),
				array(), // Hidden columns
				$this->get_sortable_columns(),
				$this->get_primary_column_name()
			);

			// 2. Get sorting parameters, ensuring they are safe.
			$sortable_columns = $this->get_sortable_columns();
			$orderby_key_from_request = !empty($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : '';

			$orderby = 'notified_at';
			if (!empty($orderby_key_from_request) && isset($sortable_columns[$orderby_key_from_request])) {
				$orderby = $sortable_columns[$orderby_key_from_request][0]; // Use the actual DB column name from sortable_columns
			}

			$order = 'DESC';
			if (!empty($_REQUEST['order'])) {
				$unslashed_order = wp_unslash(trim((string) $_REQUEST['order']));
				$potential_order = strtoupper($unslashed_order);
				if (in_array($potential_order, array('ASC', 'DESC'), true)) {
					$order = $potential_order;
				}
			}

			// 3. Get pagination parameters.
			$per_page     = $this->get_items_per_page('swift_index_logs_per_page', 20);
			$current_page = $this->get_pagenum();
			$offset       = ($current_page - 1) * $per_page;

			// 4. Handle search query parameters.
			$search_term   = (!empty($_REQUEST['s'])) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
			$where_clauses = array();
			$query_args    = array(); // Arguments for $wpdb->prepare for search conditions in WHERE

			if ($search_term) {
				$like_search_term = '%' . $wpdb->esc_like($search_term) . '%';
				// Define columns to search through. These should be actual DB column names.
				$searchable_db_columns = ['notified_url', 'response_message', 'post_id', 'status_code', 'notification_type'];
				$search_conditions     = array();
				foreach ($searchable_db_columns as $col) {
					$search_conditions[] = $col . " LIKE %s";
					$query_args[]        = $like_search_term;
				}
				if (!empty($search_conditions)) {
					$where_clauses[] = "(" . implode(" OR ", $search_conditions) . ")";
				}
			}
			$where_sql = '';
			if (!empty($where_clauses)) {
				// Ensure $where_sql starts with a space if it has content.
				$where_sql = " WHERE " . implode(" AND ", $where_clauses);
			}

			// 5. Get the total number of items (considering search criteria).
			$total_items = 0;
			$sql_count_query_base = sprintf("SELECT COUNT(id) FROM %s", $table_name);
			$final_sql_count_template = $sql_count_query_base . $where_sql;

			$prepared_sql_count = null;
			if (!empty($query_args)) { // If $where_sql has placeholders needing arguments (from search)
				$prepared_sql_count = $wpdb->prepare($final_sql_count_template, ...$query_args);
			} else { // If $where_sql is empty or has no placeholders that need arguments.
				$prepared_sql_count = $wpdb->prepare($final_sql_count_template);
			}

			if ($prepared_sql_count) {
				$total_items = (int) $wpdb->get_var($prepared_sql_count);
			}

			// 6. Set pagination arguments for the list table.
			$this->set_pagination_args(array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil($total_items / $per_page)
			));

			// 7. Fetch the actual data for the current page.
			$this->items = array();

			// Construct SQL parts safely
			$sql_select_from_part = sprintf("SELECT * FROM %s", $table_name);
			$sql_order_by_part    = sprintf("ORDER BY %s %s", $orderby, $order);
			$sql_limit_offset_placeholders = "LIMIT %d OFFSET %d";

			// Combine parts
			$items_sql_template = $sql_select_from_part . $where_sql; // $where_sql includes " WHERE " or is empty
			$items_sql_template .= ' ' . $sql_order_by_part;
			$items_sql_template .= ' ' . $sql_limit_offset_placeholders;

			// Prepare arguments for $wpdb->prepare(): search args (if any) + pagination args
			$final_prepare_args = array_merge((array)$query_args, array($per_page, $offset));

			$prepared_sql_items = $wpdb->prepare($items_sql_template, ...$final_prepare_args);

			if ($prepared_sql_items) {
				$this->items = $wpdb->get_results($prepared_sql_items, ARRAY_A);
			}
		}

		/**
		 * Defines the primary column for the list table.
		 *
		 * Used for responsive views (e.g., on smaller screens, this column is always shown).
		 *
		 * @since 1.0.0
		 * @return string The slug of the primary column.
		 */
		public function get_primary_column_name() {
			return 'notified_at'; // This should be one of the keys from get_columns()
		}

	} // END class Swift_Index_Log_List_Table

endif;
?>
