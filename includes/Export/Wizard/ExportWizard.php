<?php
namespace YWCE\Export\Wizard;

use WC_Order;
use WP_User_Query;

class ExportWizard {
	private \YWCE\Data\Helper\Helper $data_helper;

	public function __construct() {
		add_action('admin_menu', [$this, 'add_export_wizard_page']);
		add_action('wp_ajax_ywce_fetch_data_fields', [$this, 'fetch_data_fields']);
		add_action('wp_ajax_ywce_fetch_preview_data', [$this, 'fetch_preview_data']);
		add_action('wp_ajax_ywce_fetch_product_types', [$this, 'ajax_fetch_product_types']);
		add_action('wp_ajax_ywce_fetch_user_roles', [$this, 'ajax_fetch_user_roles']);
		add_action('wp_ajax_ywce_fetch_order_statuses', [$this, 'ajax_fetch_order_statuses']);
		$this->data_helper = new \YWCE\Data\Helper\Helper();
	}

	public function add_export_wizard_page(): void {
		add_submenu_page(
			'woocommerce',
			__('Export Wizard', 'yeti-woocommerce-export'),
			__('Export', 'yeti-woocommerce-export'),
			'manage_woocommerce',
			'ywce-export',
			[$this, 'render_export_wizard']
		);
	}

	public function render_export_wizard(): void {

		$has_products = $this->has_products();
		$has_users = $this->has_users();
		$has_orders = $this->has_orders();

		wp_localize_script('ywce-admin', 'YWCE_i18n', array(
			'registrationDateRange' => __('Registration Date Range', 'yeti-woocommerce-export'),
			'orderDateRange' => __('Order Date Range', 'yeti-woocommerce-export'),
			'allTime' => __('All Time', 'yeti-woocommerce-export'),
			'customRange' => __('Custom Range', 'yeti-woocommerce-export'),
			'customIncomplete' => __('Custom (incomplete)', 'yeti-woocommerce-export'),
			'to' => __('to', 'yeti-woocommerce-export'),
			'from' => __('From', 'yeti-woocommerce-export'),
			'enterValidDate' => __('Please enter a valid date', 'yeti-woocommerce-export'),
			'fromDateLaterThanTo' => __('From date cannot be later than To date', 'yeti-woocommerce-export'),
		));

		echo '<div class="wrap">';
		
		echo '<div id="ywce-wizard" class="ywce-wizard">';
		
		// Progress Bar
		echo '<div class="progress mb-4">';
		echo '<div class="progress-bar" id="ywce-progress" role="progressbar" style="width: 0%;"></div>';
		echo '</div>';

		// Step 1 - Source
		echo '<div class="step step-1 active">';
		echo '<h2 class="fs-4 mb-4 text-center">' . esc_html__('Select Data Source', 'yeti-woocommerce-export') . '</h2>';
		
		// Debug button - hidden by default, will be shown via JS if in debug mode
		echo '<button id="ywce-debug-btn" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-3 d-none">' . esc_html__('Debug', 'yeti-woocommerce-export') . '</button>';
		
		echo '<div id="ywce-data-source" class="row justify-content-center g-4 mb-5">';
		
		// Only show data sources that have data
		if ($has_products) {
			// Products card
			echo '<div class="col-md-4 col-lg-3">';
			echo '<div id="btn-product" class="source-card card h-100 text-center" data-source="product">';
			echo '<div class="card-body d-flex flex-column align-items-center justify-content-center p-3">';
			echo '<div class="source-icon mb-3 d-flex align-items-center justify-content-center">';
			echo '<span class="dashicons dashicons-products"></span>';
			echo '</div>';
			echo '<h3 class="card-title h5 mb-0">' . esc_html__('Products', 'yeti-woocommerce-export') . '</h3>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}
		
		if ($has_users) {
			// Users card
			echo '<div class="col-md-4 col-lg-3">';
			echo '<div id="btn-user" class="source-card card h-100 text-center" data-source="user">';
			echo '<div class="card-body d-flex flex-column align-items-center justify-content-center p-3">';
			echo '<div class="source-icon mb-3 d-flex align-items-center justify-content-center">';
			echo '<span class="dashicons dashicons-admin-users"></span>';
			echo '</div>';
			echo '<h3 class="card-title h5 mb-0">' . esc_html__('Users', 'yeti-woocommerce-export') . '</h3>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}
		
		if ($has_orders) {
			// Orders card
			echo '<div class="col-md-4 col-lg-3">';
			echo '<div id="btn-order" class="source-card card h-100 text-center" data-source="order">';
			echo '<div class="card-body d-flex flex-column align-items-center justify-content-center p-3">';
			echo '<div class="source-icon mb-3 d-flex align-items-center justify-content-center">';
			echo '<span class="dashicons dashicons-cart"></span>';
			echo '</div>';
			echo '<h3 class="card-title h5 mb-0">' . esc_html__('Orders', 'yeti-woocommerce-export') . '</h3>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}

		// Show message if no data sources are available
		if (!$has_products && !$has_users && !$has_orders) {
			echo '<div class="col-12 text-center">';
			echo '<div class="alert alert-info">';
			echo esc_html__('No data available for export. Please add some products, users, or orders first.', 'yeti-woocommerce-export');
			echo '</div>';
			echo '</div>';
		}
		
		echo '</div>'; // End of ywce-data-source

		// Hidden input to store selected source
		echo '<input type="hidden" id="ywce-selected-source" name="ywce-selected-source" value="">';
		echo '</div>'; // End of step-1

		// Step 2 - Choose Data
		echo '<div class="step step-2">';
		echo '<h2 class="fs-4 mb-3 text-center">' . esc_html__('Choose the ', 'yeti-woocommerce-export') . '<span id="ywce-data-type-label">data</span>' . esc_html__(' to export', 'yeti-woocommerce-export') . '</h2>';
		
		echo '<div class="row g-3">';
		
		echo '<div class="col-md-6 mb-2">';
		echo '<div class="card h-100 shadow-sm">';
		echo '<div class="py-2">';
		echo '<h3 class="h6 mb-0">' . esc_html__('Standard Fields', 'yeti-woocommerce-export') . '</h3>';
		echo '</div>';
		echo '<div class="card-body py-2 px-0">';
		echo '<div class="field-list-container" id="ywce-data-fields-container"></div>';
		echo '<div class="d-flex justify-content-between mt-3 px-0">';
		echo '<button class="btn btn-sm btn-outline-primary select-all-btn" data-target="ywce-data-fields-container">' . esc_html__('Select All', 'yeti-woocommerce-export') . '</button>';
		echo '<button class="btn btn-sm btn-outline-secondary deselect-all-btn" data-target="ywce-data-fields-container">' . esc_html__('Deselect All', 'yeti-woocommerce-export') . '</button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="col-md-6 mb-2">';
		echo '<div class="card h-100 shadow-sm">';
		echo '<div class="py-2">';
		echo '<h3 class="h6 mb-0">' . esc_html__('Meta Fields', 'yeti-woocommerce-export') . '</h3>';
		echo '</div>';
		echo '<div class="card-body py-2 px-0">';
		echo '<div class="field-list-container" id="ywce-meta-fields-container"></div>';
		echo '<div class="d-flex justify-content-between mt-3 px-0">';
		echo '<button class="btn btn-sm btn-outline-primary select-all-btn" data-target="ywce-meta-fields-container">' . esc_html__('Select All', 'yeti-woocommerce-export') . '</button>';
		echo '<button class="btn btn-sm btn-outline-secondary deselect-all-btn" data-target="ywce-meta-fields-container">' . esc_html__('Deselect All', 'yeti-woocommerce-export') . '</button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div id="ywce-taxonomy-container" class="col-md-6 mb-2" style="display: none;">';
		echo '<div class="card h-100 shadow-sm">';
		echo '<div class="py-2">';
		echo '<h3 class="h6 mb-0">' . esc_html__('Taxonomy Fields', 'yeti-woocommerce-export') . '</h3>';
		echo '</div>';
		echo '<div class="card-body py-2 px-0">';
		echo '<div class="field-list-container" id="ywce-taxonomy-fields-container"></div>';
		echo '<div class="d-flex justify-content-between mt-3 px-0">';
		echo '<button class="btn btn-sm btn-outline-primary select-all-btn" data-target="ywce-taxonomy-fields-container">' . esc_html__('Select All', 'yeti-woocommerce-export') . '</button>';
		echo '<button class="btn btn-sm btn-outline-secondary deselect-all-btn" data-target="ywce-taxonomy-fields-container">' . esc_html__('Deselect All', 'yeti-woocommerce-export') . '</button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		
		// Hidden select elements for storing the actual selected values
		echo '<div style="display: none;">';
		echo '<select multiple id="ywce-data-fields"></select>';
		echo '<select multiple id="ywce-meta-fields"></select>';
		echo '<select multiple id="ywce-taxonomy-fields"></select>';
		echo '</div>';

		echo '</div>'; // End row
		echo '</div>'; // End Step 2

		// Step 3 - Preview
		echo '<div class="step step-3">';
		echo '<h2 class="fs-4 mb-3">' . esc_html__('Preview', 'yeti-woocommerce-export') . '</h2>';
		echo '<div id="ywce-preview-table-wrapper" class="table-responsive">';
		echo '<table class="table table-hover align-middle shadow-sm w-100">';
		echo '<thead class="table-light"><tr></tr></thead>';
		echo '<tbody></tbody>';
		echo '</table>';
		echo '</div>';
		echo '</div>'; // End Step 3

		// Step 4 - Export
		echo '<div class="step step-4">';
		echo '<h2 class="fs-4 mb-4 text-center">' . esc_html__('Export Configuration', 'yeti-woocommerce-export') . '</h2>';
		
		echo '<div class="export-config-container">';
		
		// Export format and name section - Always visible
		echo '<div class="export-section mb-5">';
		echo '<h3 class="section-title mb-4">' . esc_html__('Export Settings', 'yeti-woocommerce-export') . '</h3>';
		
		echo '<div class="row g-4">';
		
		// Export name
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Name to identify this export', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="input-group">';
		echo '<input type="text" class="form-control" id="export-name" placeholder="' . esc_attr__('Enter export name', 'yeti-woocommerce-export') . '">';
		echo '<button class="btn btn-outline-primary" type="button" id="generate-name" data-action="generate-name" onclick="if(window.YWCE && YWCE.generateExportName) YWCE.generateExportName();">' . esc_html__('Generate', 'yeti-woocommerce-export') . '</button>';
		echo '</div>';
		echo '</div>';
		
		// Export format
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Format for your export file', 'yeti-woocommerce-export') . '</div>';
		echo '<div id="ywce-format-buttons" class="d-flex gap-2 mb-3">';
		echo '<button type="button" class="btn btn-outline-primary format-btn csv-btn flex-grow-1" data-format="csv">';
		echo '<span class="dashicons dashicons-media-text"></span> CSV';
		echo '</button>';
		echo '<button type="button" class="btn btn-outline-primary format-btn excel-btn flex-grow-1" data-format="excel">';
		echo '<span class="dashicons dashicons-media-spreadsheet"></span> XLSX';
		echo '</button>';
		echo '<button type="button" class="btn btn-outline-primary format-btn xml-btn flex-grow-1" data-format="xml">';
		echo '<span class="dashicons dashicons-media-code"></span> XML';
		echo '</button>';
		echo '<button type="button" class="btn btn-outline-primary format-btn json-btn flex-grow-1" data-format="json">';
		echo '<span class="dashicons dashicons-media-code"></span> JSON';
		echo '</button>';
		echo '</div>';
		echo '</div>';
		
		echo '</div>'; // End row
		echo '</div>'; // End export section
		
		// Filter sections - Show based on selected data source
		
		// Product Filters
		echo '<div id="product-filters" class="export-section filter-section mb-5" style="display: none;">';
		echo '<h3 class="section-title mb-4">' . esc_html__('Product Filters', 'yeti-woocommerce-export') . '</h3>';

		echo '<div class="row g-4">';

		// Export Mode and Date Range in the same row
		echo '<div class="col-12">';
		echo '<div class="row align-items-end g-3">';

		// Export Mode
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Select which products to export', 'yeti-woocommerce-export') . '</div>';
		echo '<select class="form-select" id="product-export-mode" aria-label="' . esc_attr__('Select product export mode', 'yeti-woocommerce-export') . '">';
		echo '<option value="all" selected>' . esc_html__('All Products', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="modified">' . esc_html__('Last Modified Products', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="new">' . esc_html__('New Products', 'yeti-woocommerce-export') . '</option>';
		echo '</select>';
		echo '</div>';

		// Date Range (initially hidden)
		echo '<div class="col-md-6" id="product-date-range-container" style="display: none;">';
		echo '<div class="form-text mb-2">' . esc_html__('Time period', 'yeti-woocommerce-export') . '</div>';
		echo '<select class="form-select" id="product-date-range" aria-label="' . esc_attr__('Select export time period', 'yeti-woocommerce-export') . '">';
		echo '<option value="last30" selected>' . esc_html__('Last 30 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="last60">' . esc_html__('Last 60 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="last90">' . esc_html__('Last 90 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="custom">' . esc_html__('Custom Range', 'yeti-woocommerce-export') . '</option>';
		echo '</select>';
		echo '</div>';

		echo '</div>'; // End row for export mode and date range
		echo '</div>'; // End col-12

		// Custom date range (hidden by default)
		echo '<div class="col-12" id="product-custom-date-range" style="display: none;">';
		echo '<div class="date-range-container p-3 rounded-3 border">';
		echo '<div class="row g-3">';
		echo '<div class="col-md-6">';
		echo '<label for="product-date-from" class="form-label">' . esc_html__('From', 'yeti-woocommerce-export') . '</label>';
		echo '<input type="date" class="form-control" id="product-date-from" aria-label="' . esc_attr__('From date', 'yeti-woocommerce-export') . '">';
		echo '</div>';
		echo '<div class="col-md-6">';
		echo '<label for="product-date-to" class="form-label">' . esc_html__('To', 'yeti-woocommerce-export') . '</label>';
		echo '<input type="date" class="form-control" id="product-date-to" aria-label="' . esc_attr__('To date', 'yeti-woocommerce-export') . '">';
		echo '</div>';
		echo '</div>'; // End row
		echo '</div>'; // End date range container
		echo '</div>'; // End custom date range

		// Product Type
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Filter by product type', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="field-list-container border rounded p-0 bg-white" style="max-height: 200px; overflow-y: auto;">';
		echo '<div class="field-item" data-value="all">' . esc_html__('All Types', 'yeti-woocommerce-export') . '</div>';
		// Dynamic product types will be loaded via AJAX
		echo '</div>';
		echo '<select id="product-type" multiple style="display: none;"></select>';
		echo '</div>';
		
		// Product Status
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Filter by product status', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="field-list-container border rounded p-0 bg-white" style="max-height: 200px; overflow-y: auto;">';
		echo '<div class="field-item" data-value="all">' . esc_html__('All Statuses', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="field-item" data-value="publish">' . esc_html__('Published', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="field-item" data-value="draft">' . esc_html__('Draft', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="field-item" data-value="pending">' . esc_html__('Pending', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="field-item" data-value="private">' . esc_html__('Private', 'yeti-woocommerce-export') . '</div>';
		echo '</div>';
		echo '<select id="product-status" multiple style="display: none;"></select>';
		echo '</div>';
		
		echo '</div>'; // End row
		echo '</div>'; // End product filters section
		
		// User Filters
		echo '<div id="user-filters" class="export-section filter-section mb-5" style="display: none;">';
		echo '<h3 class="section-title mb-4">' . esc_html__('User Filters', 'yeti-woocommerce-export') . '</h3>';
		
		echo '<div class="row g-4">';
		
		// User Role
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Filter by user role', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="field-list-container border rounded p-0 bg-white" style="max-height: 200px; overflow-y: auto;">';
		echo '<div class="field-item" data-value="all">' . esc_html__('All Roles', 'yeti-woocommerce-export') . '</div>';
		// Dynamic user roles will be loaded via AJAX
		echo '</div>';
		echo '<select id="user-role" multiple style="display: none;"></select>';
		echo '</div>';
		
		// Registration Date Range
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Filter by registration date', 'yeti-woocommerce-export') . '</div>';
		echo '<select class="form-select" id="user-date-range">';
		echo '<option value="all" selected>' . esc_html__('All Time', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="last30">' . esc_html__('Last 30 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="last90">' . esc_html__('Last 90 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="last180">' . esc_html__('Last 180 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="custom">' . esc_html__('Custom Range', 'yeti-woocommerce-export') . '</option>';
		echo '</select>';
		echo '</div>';
		
		// Custom date range (hidden by default)
		echo '<div class="col-12" id="user-custom-date-range" style="display: none;">';
		echo '<div class="date-range-container p-3 rounded-3 border mt-3">';
		echo '<div class="row g-3">';
		echo '<div class="col-md-6">';
		echo '<label for="user-date-from" class="form-label">' . esc_html__('From', 'yeti-woocommerce-export') . '</label>';
		echo '<input type="date" class="form-control" id="user-date-from">';
		echo '</div>';
		echo '<div class="col-md-6">';
		echo '<label for="user-date-to" class="form-label">' . esc_html__('To', 'yeti-woocommerce-export') . '</label>';
		echo '<input type="date" class="form-control" id="user-date-to">';
		echo '</div>';
		echo '</div>'; // End row
		echo '</div>'; // End date range container
		echo '</div>'; // End custom date range
		
		echo '</div>'; // End row
		echo '</div>'; // End user filters section
		
		// Order Filters
		echo '<div id="order-filters" class="export-section filter-section mb-5" style="display: none;">';
		echo '<h3 class="section-title mb-4">' . esc_html__('Order Filters', 'yeti-woocommerce-export') . '</h3>';
		
		echo '<div class="row g-4">';
		
		// Order Status
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Filter by order status', 'yeti-woocommerce-export') . '</div>';
		echo '<div class="field-list-container border rounded p-0 bg-white" style="max-height: 200px; overflow-y: auto;">';
		echo '<div class="field-item" data-value="all">' . esc_html__('All Statuses', 'yeti-woocommerce-export') . '</div>';
		// Dynamic order statuses will be loaded via AJAX
		echo '</div>';
		echo '<select id="order-status" multiple style="display: none;"></select>';
		echo '</div>';
		
		// Order Date Range
		echo '<div class="col-md-6">';
		echo '<div class="form-text mb-2">' . esc_html__('Filter by order date', 'yeti-woocommerce-export') . '</div>';
		echo '<select class="form-select" id="order-date-range">';
		echo '<option value="all" selected>' . esc_html__('All Time', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="last30">' . esc_html__('Last 30 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="last90">' . esc_html__('Last 90 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="last180">' . esc_html__('Last 180 Days', 'yeti-woocommerce-export') . '</option>';
		echo '<option value="custom">' . esc_html__('Custom Range', 'yeti-woocommerce-export') . '</option>';
		echo '</select>';
		echo '</div>';
		
		// Custom date range (hidden by default)
		echo '<div class="col-12" id="order-custom-date-range" style="display: none;">';
		echo '<div class="date-range-container p-3 rounded-3 border mt-3">';
		echo '<div class="row g-3">';
		echo '<div class="col-md-6">';
		echo '<label for="order-date-from" class="form-label">' . esc_html__('From', 'yeti-woocommerce-export') . '</label>';
		echo '<input type="date" class="form-control" id="order-date-from">';
		echo '</div>';
		echo '<div class="col-md-6">';
		echo '<label for="order-date-to" class="form-label">' . esc_html__('To', 'yeti-woocommerce-export') . '</label>';
		echo '<input type="date" class="form-control" id="order-date-to">';
		echo '</div>';
		echo '</div>'; // End row
		echo '</div>'; // End date range container
		echo '</div>'; // End custom date range
		
		echo '</div>'; // End row
		echo '</div>'; // End order filters section
		
		// Export Summary
		echo '<div class="export-section mb-5">';
		echo '<div id="ywce-export-summary-content" class="p-4 rounded-3 border bg-white">';
		echo '<h4 class="h5 mb-4">' . esc_html__('Export Summary', 'yeti-woocommerce-export') . '</h4>';

		echo '<div class="row g-3">';

		// Export Name
		echo '<div class="col-12">';
		echo '<div class="d-flex align-items-center">';
		echo '<span class="fw-medium me-2">' . esc_html__('Export Name', 'yeti-woocommerce-export') . ':</span>';
		echo '<span id="summary-export-name"></span>';
		echo '</div>';
		echo '</div>';

		// Format
		echo '<div class="col-12">';
		echo '<div class="d-flex align-items-center">';
		echo '<span class="fw-medium me-2">' . esc_html__('Format', 'yeti-woocommerce-export') . ':</span>';
		echo '<span id="summary-format"></span>';
		echo '</div>';
		echo '</div>';

		// Data Source
		echo '<div class="col-12">';
		echo '<div class="d-flex align-items-center">';
		echo '<span class="fw-medium me-2">' . esc_html__('Data Source', 'yeti-woocommerce-export') . ':</span>';
		echo '<span id="summary-data-source"></span>';
		echo '</div>';
		echo '</div>';

		// Product Types (shown only for products)
		echo '<div class="col-12 product-summary-item" style="display: none;">';
		echo '<div class="d-flex align-items-center">';
		echo '<span class="fw-medium me-2">' . esc_html__('Product Types', 'yeti-woocommerce-export') . ':</span>';
		echo '<span id="summary-product-types"></span>';
		echo '</div>';
		echo '</div>';

		// Product Status (shown only for products)
		echo '<div class="col-12 product-summary-item" style="display: none;">';
		echo '<div class="d-flex align-items-center">';
		echo '<span class="fw-medium me-2">' . esc_html__('Product Statuses', 'yeti-woocommerce-export') . ':</span>';
		echo '<span id="summary-product-status"></span>';
		echo '</div>';
		echo '</div>';

		// Selected Fields
		echo '<div class="col-12">';
		echo '<div class="d-flex align-items-center mb-2">';
		echo '<span class="fw-medium me-2">' . esc_html__('Selected Fields', 'yeti-woocommerce-export') . ':</span>';
		echo '<span class="badge bg-primary" id="summary-fields-count"></span>';
		echo '</div>';
		echo '<div class="selected-fields-list small" id="summary-selected-fields"></div>';
		echo '</div>';

		echo '</div>'; // End row
		echo '</div>'; // End summary content
		echo '</div>'; // End export section
		
		// Export progress
		echo '<div id="ywce-export-progress-container" class="mb-4 mx-auto" style="display: none; max-width: 500px;">';
		echo '<div class="progress" style="height: 20px;">';
		echo '<div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="ywce-export-progress" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>';
		echo '</div>';
		echo '<div id="ywce-export-status" class="text-center mt-2 text-muted"></div>';
		echo '</div>';
		
		// Download links container
		echo '<div id="ywce-download-links" class="mb-4 d-flex flex-wrap justify-content-center gap-3" style="display: none;"></div>';
		
		// Export completed message
		echo '<div id="ywce-export-completed-msg" class="alert alert-light border text-center" style="display: none;">';
		echo esc_html__('Click the download links above to download your files.', 'yeti-woocommerce-export');
		echo '</div>';
		
		// Export button container
		echo '<div class="text-center">';
		echo '<button id="ywce-export-btn" class="btn btn-primary btn-lg px-5 py-3">' . esc_html__('Start Export', 'yeti-woocommerce-export') . '</button>';
		echo '</div>';
		
		echo '</div>';
		
		echo '</div>'; // End export-config-container
		echo '</div>'; // End Step 4

		echo '<div class="d-flex justify-content-center gap-3 mt-4">';
		echo '<button id="ywce-prev-step" class="btn btn-outline-secondary px-4" disabled>' . esc_html__('Previous', 'yeti-woocommerce-export') . '</button>';
		echo '<button id="ywce-next-step" class="btn btn-primary px-4">' . esc_html__('Next Step', 'yeti-woocommerce-export') . '</button>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	public function fetch_data_fields(): void {
		check_ajax_referer('ywce_export_nonce', 'nonce');
		if (!isset($_GET['source'])) { wp_send_json_error(['error' => 'Missing source parameter']); return; }
		$data_source = sanitize_text_field($_GET['source']);
		global $wpdb;
		$data = [ 'fields' => [], 'meta' => [], 'taxonomies' => [], 'required' => [], ];
		if ($data_source === 'product') {
			$fields = [ 'Parent ID','plytix_variant_of','ID','Title','Short Description','Description','Featured Image','Product Gallery URLs','Product Categories','Product Category URL','Permalink','Product type','Product status', ];
			$has_variable_products = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product_variation'") > 0;
			if (!$has_variable_products) { unset($fields[0]); $fields = array_values($fields); } else { $data['required'][] = 'Parent ID'; }
			$data['required'][] = 'Product type'; $data['required'][] = 'Product status';
			$data['fields'] = $fields; $data['required'][] = 'ID';
			$product_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' LIMIT 100");
			$meta_keys = [];
			foreach ($product_ids as $product_id) { $meta_data = get_post_meta($product_id); if (!empty($meta_data)) { foreach ($meta_data as $key => $value) { if (!in_array($key, $meta_keys)) { $meta_keys[] = $key; } } } }
			sort($meta_keys);
			$data['meta'] = $meta_keys;
			$taxonomies = get_object_taxonomies('product', 'names');
			$data['taxonomies'] = is_array($taxonomies) ? $taxonomies : [];
			sort($data['taxonomies']);
		} elseif ($data_source === 'user') {
			$data['fields'] = [ 'ID','Username','Email','First Name','Last Name','Role','Registration Date' ];
			$data['required'][] = 'ID';
			$user_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->users} LIMIT 100");
			$meta_keys = [];
			foreach ($user_ids as $user_id) { $meta_data = get_user_meta($user_id); if (!empty($meta_data)) { foreach ($meta_data as $key => $value) { if (!in_array($key, $meta_keys)) { $meta_keys[] = $key; } } } }
			sort($meta_keys);
			$data['meta'] = $meta_keys;
		} elseif ($data_source === 'order') {
			$data['fields'] = [ 'ID','Order Number','Order Status','Order Date','Customer ID','Customer Email','Customer First Name','Customer Last Name','Billing First Name','Billing Last Name','Billing Company','Billing Address 1','Billing Address 2','Billing City','Billing State','Billing Postcode','Billing Country','Billing Email','Billing Phone','Shipping First Name','Shipping Last Name','Shipping Company','Shipping Address 1','Shipping Address 2','Shipping City','Shipping State','Shipping Postcode','Shipping Country','Payment Method','Payment Method Title','Transaction ID','Order Total','Order Subtotal','Order Tax','Order Shipping','Order Shipping Tax','Order Discount','Order Currency','Order Items','Order Notes' ];
			$data['required'][] = 'ID';
			$order_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' LIMIT 100");
			$meta_keys = [];
			foreach ($order_ids as $order_id) { $meta_data = get_post_meta($order_id); if (!empty($meta_data)) { foreach ($meta_data as $key => $value) { if (!in_array($key, $meta_keys) && !in_array($key, ['_edit_lock', '_edit_last'])) { $meta_keys[] = $key; } } } }
			sort($meta_keys);
			$data['meta'] = $meta_keys;
		} else { wp_send_json_error(['error' => 'Invalid source type']); return; }
		$data['fields'] = is_array($data['fields']) ? $data['fields'] : [];
		$data['meta'] = is_array($data['meta']) ? $data['meta'] : [];
		$data['taxonomies'] = is_array($data['taxonomies']) ? $data['taxonomies'] : [];
		$data['required'] = is_array($data['required']) ? $data['required'] : [];
		wp_send_json($data);
	}

	public function fetch_preview_data(): void {
		check_ajax_referer('ywce_export_nonce', 'nonce');
		$data_source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : 'product';
		$selected_fields = isset($_GET['fields']) ? array_map('sanitize_text_field', explode(',', $_GET['fields'])) : [];
		$selected_meta = isset($_GET['meta']) ? array_map('sanitize_text_field', explode(',', $_GET['meta'])) : [];
		$selected_taxonomies = isset($_GET['taxonomies']) ? array_map('sanitize_text_field', explode(',', $_GET['taxonomies'])) : [];
		if ($data_source === 'product') {
			if (!in_array('Product type', $selected_fields, true)) { $selected_fields[] = 'Product type'; }
			if (!in_array('Product status', $selected_fields, true)) { $selected_fields[] = 'Product status'; }
		}
		if (!in_array('ID', $selected_fields)) { wp_send_json_error(['error' => 'The ID field is required and must be selected.']); }
		if ($data_source === 'product') {
			$has_variable_products = get_posts([ 'post_type' => 'product_variation', 'posts_per_page' => 1, ]);
			if ($has_variable_products) {
				$has_either = in_array('Parent ID', $selected_fields, true) || in_array('plytix_variant_of', $selected_fields, true);
				if (!$has_either) { wp_send_json_error(['error' => 'Select either Parent ID or plytix_variant_of to include variations.']); }
			}
		}
		$preview_data = [];
		if ($data_source === 'product') {
			$args = [ 'post_type' => 'product', 'posts_per_page' => 10, 'post_status' => 'publish', ];
			$products = get_posts($args);
			foreach ($products as $product) {
				$wc_product = wc_get_product($product->ID);
				if (!$wc_product) { continue; }
				$product_data = $this->data_helper->get_product_data($wc_product, $selected_fields, $selected_meta, $selected_taxonomies);
				$preview_data[] = $product_data;
				if ($wc_product->is_type('variable')) {
					$variation_args = [ 'post_type' => 'product_variation', 'post_parent' => $wc_product->get_id(), 'posts_per_page' => -1, 'post_status' => 'publish', ];
					$variations = get_posts($variation_args);
					foreach ($variations as $variation) {
						$wc_variation = wc_get_product($variation->ID);
						if (!$wc_variation) { continue; }
						$variation_data = $this->data_helper->get_product_data($wc_variation, $selected_fields, $selected_meta, $selected_taxonomies);
						$variation_data['Parent ID'] = $wc_product->get_id();
						$preview_data[] = $variation_data;
					}
				}
			}
		} elseif ($data_source === 'user') {
			$user_query = new WP_User_Query([ 'number' => 10, ]);
			$users = $user_query->get_results();
			if (!empty($users)) { foreach ($users as $user) { $user_data = $this->data_helper->get_user_data($user->ID, $selected_fields, $selected_meta); $preview_data[] = $user_data; } }
		} elseif ($data_source === 'order') {
			$args = [ 'post_type' => 'shop_order', 'posts_per_page' => 10, 'post_status' => array_keys(wc_get_order_statuses()), ];
			$orders = get_posts($args);
			if (!empty($orders)) { foreach ($orders as $order_post) { $order = wc_get_order($order_post->ID); if (!$order) { continue; } $order_data = $this->data_helper->get_order_data($order, $selected_fields, $selected_meta); $preview_data[] = $order_data; } }
		}
		wp_send_json(['data' => $preview_data]);
	}

	public function ajax_fetch_product_types(): void {
		check_ajax_referer('ywce_export_nonce', 'nonce');
		$product_types = $this->data_helper->get_product_types();
		wp_send_json_success(['product_types' => $product_types]);
	}
	public function ajax_fetch_user_roles(): void {
		check_ajax_referer('ywce_export_nonce', 'nonce');
		$user_roles = $this->data_helper->get_user_roles();
		wp_send_json_success(['user_roles' => $user_roles]);
	}
	public function ajax_fetch_order_statuses(): void {
		check_ajax_referer('ywce_export_nonce', 'nonce');
		$order_statuses = $this->data_helper->get_order_statuses();
		wp_send_json_success(['order_statuses' => $order_statuses]);
	}

	private function has_products(): bool {
		global $wpdb;
		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product', 'product_variation') AND post_status != 'trash'");
		return !empty($count);
	}
	private function has_users(): bool {
		global $wpdb;
		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
		return !empty($count);
	}
	private function has_orders(): bool {
		global $wpdb;
		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status != 'trash'");
		return !empty($count);
	}
}
