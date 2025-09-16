<?php
namespace YWCE;

/**
 * Exporter Class
 *
 * Handles the export process
 */
class YWCE_Exporter {

	private int $batch_size = 100;
	private string $export_dir;
	private YWCE_Data_Helper $data_helper;
	private YWCE_Format_Handler $format_handler;

	/**
	 * Debug log utility - only logs in development environments
	 *
	 * @param string $message The message to log
	 *
	 * @return void
	 */
	private function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $message );
		}
	}

	public function __construct() {
		add_action( 'wp_ajax_ywce_start_export', [ $this, 'start_export' ] );
		add_action( 'wp_ajax_ywce_process_export', [ $this, 'process_export' ] );
		add_action( 'wp_ajax_ywce_download_export', [ $this, 'download_export' ] );

		$this->export_dir     = WP_CONTENT_DIR . "/exports/";
		$this->data_helper    = new YWCE_Data_Helper();
		$this->format_handler = new YWCE_Format_Handler();

		$this->ensure_export_directory_exists();
	}


	/**
	 * Ensure Export Directory Exists: Create the export directory if it doesn't exist.
	 * @return bool
	 */
	private function ensure_export_directory_exists(): bool {
		if ( ! file_exists( $this->export_dir ) ) {
			$created = wp_mkdir_p( $this->export_dir );

			if ( ! $created ) {
				$this->debug_log( 'Failed to create export directory: ' . $this->export_dir );

				return false;
			}
		}

		if ( ! is_writable( $this->export_dir ) ) {
			chmod( $this->export_dir, 0755 );

			if ( ! is_writable( $this->export_dir ) ) {
				$this->debug_log( 'Export directory is not writable: ' . $this->export_dir );

				return false;
			}
		}

		// Create an index.php file to prevent directory listing
		$index_file = $this->export_dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, '<?php // Silence is golden' );
		}

		return true;
	}


	/**
	 * Start the export process
	 * @return void
	 */
	public function start_export(): void {
		if ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();

		$original_error_reporting = error_reporting();
		error_reporting( 0 );

		try {
			check_ajax_referer( 'ywce_export_nonce', 'nonce' );

			$export_id   = 'export_' . time();
			$export_name = sanitize_text_field( $_POST['export_name'] ?? $export_id );

			// Handle multiple formats
			$formats = [];
			if ( isset( $_POST['formats'] ) ) {
				$formats = json_decode( stripslashes( $_POST['formats'] ), true, 512, JSON_THROW_ON_ERROR );
			} else if ( isset( $_POST['format'] ) ) {
				// Backward compatibility for a single format
				$formats = [ sanitize_text_field( $_POST['format'] ) ];
			}

			if ( empty( $formats ) ) {
				$formats = [ 'csv' ]; // Default format if none specified
			}

			// Validate formats
			$allowed_formats = [ 'csv', 'excel', 'xml', 'json' ];
			$valid_formats   = [];
			foreach ( $formats as $format ) {
				if ( in_array( $format, $allowed_formats, true ) ) {
					$valid_formats[] = $format;
				}
			}

			if ( empty( $valid_formats ) ) {
				$valid_formats = [ 'csv' ]; // Default if no valid formats
			}

			$data_source = sanitize_text_field( $_POST['data_source'] ?? 'product' );

			// Validate data source
			$allowed_sources = [ 'product', 'user', 'order' ];
			if ( ! in_array( $data_source, $allowed_sources, true ) ) {
				wp_send_json_error( [ 'message' => 'Invalid data source' ] );

				return;
			}

			// Get selected fields
			$fields      = isset( $_POST['fields'] ) ? json_decode( stripslashes( $_POST['fields'] ), true, 512, JSON_THROW_ON_ERROR ) : [];
			$meta_fields = isset( $_POST['meta_fields'] ) ? json_decode( stripslashes( $_POST['meta_fields'] ), true, 512, JSON_THROW_ON_ERROR ) : [];
			$taxonomies  = isset( $_POST['taxonomies'] ) ? json_decode( stripslashes( $_POST['taxonomies'] ), true, 512, JSON_THROW_ON_ERROR ) : [];

			// Validate fields
			if ( empty( $fields ) && empty( $meta_fields ) && empty( $taxonomies ) ) {
				wp_send_json_error( [ 'message' => 'No fields selected for export' ] );

				return;
			}

			// Ensure Product type and Product status are always included for product exports
			if ( $data_source === 'product' ) {
				if ( ! in_array( 'Product type', $fields, true ) ) {
					$fields[] = 'Product type';
				}
				if ( ! in_array( 'Product status', $fields, true ) ) {
					$fields[] = 'Product status';
				}
			}

			$selected_filters = [
				'data_source' => $data_source,
				'fields'      => $fields,
				'meta'        => $meta_fields,
				'taxonomies'  => $taxonomies
			];

			$column_order   = isset( $_POST['column_order'] ) ? json_decode( stripslashes( $_POST['column_order'] ), true, 512, JSON_THROW_ON_ERROR ) : [];
			$custom_headers = isset( $_POST['custom_headers'] ) ? json_decode( stripslashes( $_POST['custom_headers'] ), true, 512, JSON_THROW_ON_ERROR ) : [];

			$selected_filters['column_order']   = $column_order;
			$selected_filters['custom_headers'] = $custom_headers;

			// Get item IDs based on data source and filters
			$total_items = 0;
			$item_ids    = [];

			if ( $data_source === 'product' ) {
				// Get product filters
				$product_types  = isset( $_POST['product_types'] ) ? json_decode( stripslashes( $_POST['product_types'] ), true, 512, JSON_THROW_ON_ERROR ) : [ 'all' ];
				$product_status = isset( $_POST['product_status'] ) ? json_decode( stripslashes( $_POST['product_status'] ), true, 512, JSON_THROW_ON_ERROR ) : [ 'all' ];
				$export_mode    = isset( $_POST['product_export_mode'] ) ? sanitize_text_field( $_POST['product_export_mode'] ) : 'all';
				$date_range     = isset( $_POST['product_date_range'] ) ? sanitize_text_field( $_POST['product_date_range'] ) : 'last30';
				$date_from      = isset( $_POST['product_date_from'] ) ? sanitize_text_field( $_POST['product_date_from'] ) : '';
				$date_to        = isset( $_POST['product_date_to'] ) ? sanitize_text_field( $_POST['product_date_to'] ) : '';

				// Build product query args
				$args = [
					'post_type'      => 'product',
					'posts_per_page' => - 1,
					'fields'         => 'ids'
				];

				// Apply product type filter
				if ( ! empty( $product_types ) && ! in_array( 'all', $product_types ) ) {
					$args['tax_query'][] = [
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => $product_types,
					];
				}

				// Apply status filter
				if ( ! empty( $product_status ) && ! in_array( 'all', $product_status ) ) {
					$args['post_status'] = $product_status;
				} else {
					$args['post_status'] = 'any';
				}

				// Apply export mode filters
				if ( $export_mode !== 'all' ) {
					$this->debug_log( 'YWCE: Export mode: ' . $export_mode );
					$this->debug_log( 'YWCE: Date range: ' . $date_range );

					$date_query = [];

					// Calculate date range
					switch ( $date_range ) {
						case 'last30':
							$date_query = [
								'after'     => date( 'Y-m-d', strtotime( '-30 days' ) ),
								'inclusive' => true
							];
							break;
						case 'last60':
							$date_query = [
								'after'     => date( 'Y-m-d', strtotime( '-60 days' ) ),
								'inclusive' => true
							];
							break;
						case 'last90':
							$date_query = [
								'after'     => date( 'Y-m-d', strtotime( '-90 days' ) ),
								'inclusive' => true
							];
							break;
						case 'custom':
							if ( $date_from ) {
								$date_query['after'] = $date_from;
							}
							if ( $date_to ) {
								$date_query['before'] = $date_to;
							}
							$date_query['inclusive'] = true;
							break;
					}

					$this->debug_log( 'YWCE: Date query before: ' . print_r( $date_query, true ) );

					if ( ! empty( $date_query ) ) {
						$column             = $export_mode === 'modified' ? 'post_modified_gmt' : 'post_date_gmt';
						$args['date_query'] = [
							[
								'column'    => $column,
								'after'     => $date_query['after'],
								'before'    => $date_query['before'] ?? null,
								'inclusive' => true
							]
						];

						$this->debug_log( 'YWCE: Final date query: ' . print_r( $args['date_query'], true ) );
					}
				}

				// Resolve product and variation IDs using ProductStore (single source of truth)
				$store = new \YWCE\Data\Store\ProductStore();
				$filters_for_store = [
					'product_types'  => $product_types,
					'product_status' => $args['post_status'] ?? 'any',
					'date_query'     => $args['date_query'] ?? [],
				];
				$resolved    = $store->resolveItemIds( $filters_for_store, $fields );
				$item_ids    = $resolved['ids'] ?? [];
				$total_items = $resolved['total'] ?? 0;

				if ( $total_items === 0 ) {
					$message = __( 'No products found for the selected filters.', 'yeti-woocommerce-export' );
					if ( $export_mode !== 'all' ) {
						if ( $date_range === 'custom' ) {
							$message .= ' ' . __( 'Try selecting a different date range.', 'yeti-woocommerce-export' );
						} else {
							$message .= ' ' . __( 'Try expanding your date range or adjusting your filters.', 'yeti-woocommerce-export' );
						}
					}
					wp_send_json_error( [ 'message' => $message ] );

					return;
				}
			} elseif ( $data_source === 'user' ) {
				// Get user filters
				$user_roles = isset( $_POST['user_roles'] ) ? json_decode( stripslashes( $_POST['user_roles'] ), true, 512, JSON_THROW_ON_ERROR ) : [ 'all' ];
				$date_range = isset( $_POST['user_date_range'] ) ? sanitize_text_field( $_POST['user_date_range'] ) : 'all';
				$date_from  = isset( $_POST['user_date_from'] ) ? sanitize_text_field( $_POST['user_date_from'] ) : '';
				$date_to    = isset( $_POST['user_date_to'] ) ? sanitize_text_field( $_POST['user_date_to'] ) : '';

				// Resolve user IDs via UserStore to keep logic centralized
				$store    = new \YWCE\Data\Store\UserStore();
				$resolved = $store->resolveItemIds([
					'user_roles' => $user_roles,
					'date_range' => $date_range,
					'date_from'  => $date_from,
					'date_to'    => $date_to,
				]);
				$item_ids    = $resolved['ids'] ?? [];
				$total_items = $resolved['total'] ?? 0;

				if ( $total_items === 0 ) {
					$message = __( 'No users found for the selected date range.', 'yeti-woocommerce-export' );
					if ( $date_range === 'custom' ) {
						$message .= ' ' . __( 'Try selecting a different date range.', 'yeti-woocommerce-export' );
					} else {
						$message .= ' ' . __( 'Try expanding your date range or removing date filters.', 'yeti-woocommerce-export' );
					}
					wp_send_json_error( [ 'message' => $message ] );

					return;
				}
			} elseif ( $data_source === 'order' ) {
				// Get order filters
				$order_statuses = isset( $_POST['order_statuses'] ) ? json_decode( stripslashes( $_POST['order_statuses'] ), true, 512, JSON_THROW_ON_ERROR ) : [ 'all' ];
				$date_range     = isset( $_POST['order_date_range'] ) ? sanitize_text_field( $_POST['order_date_range'] ) : 'all';
				$date_from      = isset( $_POST['order_date_from'] ) ? sanitize_text_field( $_POST['order_date_from'] ) : '';
				$date_to        = isset( $_POST['order_date_to'] ) ? sanitize_text_field( $_POST['order_date_to'] ) : '';

				// Resolve order IDs via OrderStore
				$store    = new \YWCE\Data\Store\OrderStore();
				$resolved = $store->resolveItemIds([
					'order_statuses' => $order_statuses,
					'date_range'     => $date_range,
					'date_from'      => $date_from,
					'date_to'        => $date_to,
				]);
				$item_ids    = $resolved['ids'] ?? [];
				$total_items = $resolved['total'] ?? 0;

				if ( $total_items === 0 ) {
					$message = __( 'No orders found for the selected date range.', 'yeti-woocommerce-export' );
					if ( $date_range === 'custom' ) {
						$message .= ' ' . __( 'Try selecting a different date range.', 'yeti-woocommerce-export' );
					} else {
						$message .= ' ' . __( 'Try expanding your date range or removing date filters.', 'yeti-woocommerce-export' );
					}
					wp_send_json_error( [ 'message' => $message ] );

					return;
				}
			}

			// Validate item IDs
			if ( empty( $item_ids ) ) {
				$message = __( 'No items found to export.', 'yeti-woocommerce-export' );
				if ( $data_source === 'user' || $data_source === 'order' ) {
					$message .= ' ' . __( 'Try adjusting your filters or date range.', 'yeti-woocommerce-export' );
				}
				wp_send_json_error( [ 'message' => $message ] );

				return;
			}

			// Create export data
			$base_filename = sanitize_file_name( $export_name );

			// Ensure export directory exists
			if ( ! $this->ensure_export_directory_exists() ) {
				wp_send_json_error( [ 'message' => 'Export directory is not writable. Please check server permissions.' ] );

				return;
			}

			// Create file paths for each format
			$file_paths = [];
			foreach ( $valid_formats as $format ) {
				$file_extension = $this->format_handler->get_file_extension( $format );
				$this->debug_log( 'YWCE: Format: ' . $format . ', Extension: ' . $file_extension );

				$format_filename = $base_filename . '_' . $format . '_' . time();
				$file_path       = $this->export_dir . "{$format_filename}.{$file_extension}";

				$this->debug_log( 'YWCE: Creating file path: ' . $file_path );

				// Test file creation
				$test_file = @fopen( $file_path, 'w' );
				if ( ! $test_file ) {
					$this->debug_log( 'YWCE: Failed to create export file: ' . $file_path );
					wp_send_json_error( [ 'message' => 'Failed to create export file. Please check server permissions.' ] );

					return;
				}
				fclose( $test_file );

				$file_paths[ $format ] = $file_path;

				$this->debug_log( 'YWCE: Created export file path: ' . $file_path );
			}

			$export_data = [
				'processed_items'  => 0,
				'total_items'      => $total_items,
				'formats'          => $valid_formats,
				'current_format'   => $valid_formats[0],
				'export_name'      => $export_name,
				'file_paths'       => $file_paths,
				'selected_filters' => $selected_filters,
				'item_ids'         => $item_ids,
				'headers'          => array_values( array_unique( array_merge( $fields, $meta_fields, $taxonomies ) ) ),
				'batch_number'     => 0
			];

			$this->debug_log( 'YWCE: Storing export data with ID: ' . $export_id );
			$this->debug_log( 'YWCE: Export data file paths: ' . print_r( $file_paths, true ) );

			// Store export data
			update_option( $export_id, $export_data );

			// Log export data for debugging
			$this->debug_log( 'Export data: ' . print_r( $export_data, true ) );

			wp_send_json_success( [
				'export_id'   => $export_id,
				'total_items' => $total_items,
				'formats'     => $valid_formats,
				'filters'     => $selected_filters
			] );
		} catch ( \Exception $e ) {
			$this->debug_log( 'YWCE Export Error: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		} finally {
			// Restore error reporting
			error_reporting( $original_error_reporting );

			// Clean up output buffer
			if ( ob_get_length() ) {
				ob_end_clean();
			}
		}
		exit;
	}

	/**
	 * Process an export request.
	 * @return void
	 */
	public function process_export(): void {
		// Prevent any output before our JSON response
		if ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();

		$original_error_reporting = error_reporting();
		error_reporting( E_ERROR | E_PARSE );
		ini_set( 'display_errors', 0 );

		try {
			check_ajax_referer( 'ywce_export_nonce', 'nonce' );

			$export_id = sanitize_text_field( $_POST['export_id'] ?? '' );
			if ( empty( $export_id ) ) {
				throw new \Exception( 'Invalid export ID' );
			}

			// Get export data from option
			$export_data = get_option( $export_id );
			if ( ! $export_data ) {
				throw new \Exception( 'Invalid export ID or no items found' );
			}

			// Get current format
			$current_format = $export_data['current_format'];
			$file_path      = $export_data['file_paths'][ $current_format ];
			$data_source    = $export_data['selected_filters']['data_source'];

			if ( empty( $export_data['item_ids'] ) ) {
				throw new \Exception( 'No items found to export.' );
			}

			// Get batch information
			$processed    = (int) $export_data['processed_items'];
			$total        = (int) $export_data['total_items'];
			$batch_size   = $this->batch_size;
			$batch_number = (int) $export_data['batch_number'] + 1;

			// Calculate start and end indices for this batch
			$start_index = $processed;
			$end_index   = min( $processed + $batch_size, $total );
			$progress    = $total > 0 ? round( ( $end_index / $total ) * 100 ) : 100;

			$column_order   = $export_data['selected_filters']['column_order'] ?? [];
			$custom_headers = $export_data['selected_filters']['custom_headers'] ?? [];

			// Get batch IDs
			$batch_ids    = array_slice( $export_data['item_ids'], $processed, $batch_size );
			$export_items = [];

			if ( $data_source === 'product' ) {
				foreach ( $batch_ids as $item_id ) {
					$wc_product = wc_get_product( $item_id );
					if ( ! $wc_product ) {
						continue;
					}

					$product_data = $this->data_helper->get_product_data(
						$wc_product,
						$export_data['selected_filters']['fields'],
						$export_data['selected_filters']['meta'],
						$export_data['selected_filters']['taxonomies']
					);

					if ( ! empty( $product_data ) ) {
						$export_items[] = $product_data;
					}
				}
			} elseif ( $data_source === 'user' ) {
				foreach ( $batch_ids as $user_id ) {
					$user_data = $this->data_helper->get_user_data(
						$user_id,
						$export_data['selected_filters']['fields'],
						$export_data['selected_filters']['meta']
					);
					if ( ! empty( $user_data ) ) {
						$export_items[] = $user_data;
					}
				}
			} elseif ( $data_source === 'order' ) {
				foreach ( $batch_ids as $order_id ) {
					$order = wc_get_order( $order_id );
					if ( ! $order ) {
						continue;
					}

					$order_data = $this->data_helper->get_order_data(
						$order,
						$export_data['selected_filters']['fields'],
						$export_data['selected_filters']['meta']
					);

					if ( ! empty( $order_data ) ) {
						$export_items[] = $order_data;
					}
				}
			}

			// Handle first batch - create file and write headers
			if ( $processed === 0 && ! empty( $export_items ) ) {
				$original_headers = array_keys( $export_items[0] );

				if ( ! empty( $column_order ) ) {
					foreach ( $export_items as &$item ) {
						$ordered_item = [];
						foreach ( $column_order as $column ) {
							if ( isset( $item[ $column ] ) ) {
								$ordered_item[ $column ] = $item[ $column ];
							}
						}
						$item = $ordered_item;
					}

					$export_data['headers'] = $column_order;
				} else {
					$export_data['headers'] = $original_headers;
				}

				if ( ! empty( $custom_headers ) ) {
					$header_mapping = [];
					foreach ( $export_data['headers'] as $header ) {
						if ( isset( $custom_headers[ $header ] ) ) {
							$header_mapping[ $header ] = $custom_headers[ $header ];
						} else {
							$header_mapping[ $header ] = $header;
						}
					}

					$export_data['header_mapping'] = $header_mapping;
				}

				$this->format_handler->create_export_file(
					$file_path,
					$current_format,
					$export_items,
					$export_data['headers'] ?? array_keys( $export_items[0] ),
					$export_data['header_mapping'] ?? []
				);
			} elseif ( ! empty( $export_items ) ) {
				$this->format_handler->append_to_export_file(
					$file_path,
					$current_format,
					$export_items,
					$export_data['header_mapping'] ?? []
				);
			}

			// Update export data
			$export_data['processed_items'] += count( $batch_ids );
			$export_data['batch_number']    = $batch_number;
			update_option( $export_id, $export_data );

			// Calculate progress
			$progress = $total > 0 ? round( ( $export_data['processed_items'] / $total ) * 100, 2 ) : 100;

				// Check if current format is completed (use updated processed_items after this batch)
				if ( $export_data['processed_items'] >= $total ) {
				// Finalize the current format
				$this->format_handler->finalize_export_file( $file_path, $current_format, $export_data['headers'] );

				// Verify file exists and is readable
				if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
					throw new \RuntimeException( 'Export file could not be created. Please check server permissions.' );
				}

				// Get the current format index
				$format_index = array_search( $current_format, $export_data['formats'], true );

				// Check if there are more formats to process
				if ( $format_index !== false && $format_index < count( $export_data['formats'] ) - 1 ) {
					// Move to the next format
					$next_format = $export_data['formats'][ $format_index + 1 ];

					// Update export data for the next format
					$export_data['current_format']  = $next_format;
					$export_data['processed_items'] = 0;
					$export_data['batch_number']    = 0;

					// Save updated export data
					update_option( $export_id, $export_data );

					// Return progress for the next format
					wp_send_json_success( [
						'completed'        => false,
						'format_completed' => $current_format,
						'next_format'      => $next_format,
						'processed'        => 0,
						'total'            => $total,
						'progress'         => 0,
						'batch'            => 0
					] );

					return;
				}

				// All formats completed
				// Create download links for all formats
				$file_urls  = [];
				$file_names = [];

				foreach ( $export_data['formats'] as $format ) {
					$format_file_path = $export_data['file_paths'][ $format ] ?? '';

					if ( ! empty( $format_file_path ) && file_exists( $format_file_path ) && is_readable( $format_file_path ) ) {
						// Create a direct download URL with proper headers
						$nonce    = wp_create_nonce( 'ywce_download_' . $format );
						$file_url = admin_url( 'admin-ajax.php?action=ywce_download_export&format=' . $format .
						                       '&file=' . urlencode( base64_encode( $format_file_path ) ) .
						                       '&nonce=' . $nonce );

						$file_urls[ $format ]  = $file_url;
						$file_names[ $format ] = basename( $format_file_path );
					}
				}

				// Send the response
				wp_send_json_success( [
					'completed'       => true,
					'processed_items' => $export_data['processed_items'],
					'total_items'     => $export_data['total_items'],
					'formats'         => $export_data['formats'],
					'file_urls'       => $file_urls,
					'file_names'      => $file_names,
					'export_name'     => $export_data['export_name'] ?? 'export',
					'progress'        => 100
				] );

				return;
			}

			// Send response for continuing batch processing
			wp_send_json_success( [
				'completed' => false,
				'progress'  => $progress,
				'processed' => $export_data['processed_items'],
				'total'     => $export_data['total_items'],
				'batch'     => $batch_number,
				'format'    => $current_format,
				'export_id' => $export_id
			] );

		} catch ( \Throwable $e ) {
			$this->debug_log( 'YWCE Export Error: ' . $e->getMessage() );
			$this->debug_log( 'YWCE Export Error Stack Trace: ' . $e->getTraceAsString() );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		} finally {
			// Restore error reporting
			error_reporting( $original_error_reporting );
			ini_set( 'display_errors', 1 );

			// Clean up output buffer
			if ( ob_get_length() ) {
				ob_end_clean();
			}
		}
		exit;
	}

	/**
	 * Download an export file.
	 * @return void
	 */
	public function download_export(): void {
		// Prevent any output before our file download
		if ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();

		try {
			// Verify nonce
			if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'ywce_download_' . $_GET['format'] ) ) {
				throw new \RuntimeException( 'Security check failed' );
			}

			// Get file path
			if ( ! isset( $_GET['file'] ) || ! isset( $_GET['format'] ) ) {
				throw new \RuntimeException( 'Download invalid request - missing file or format' );
			}

			$file_path = base64_decode( urldecode( $_GET['file'] ) );
			$format    = sanitize_text_field( $_GET['format'] );

			$this->debug_log( 'YWCE: Download request for format: ' . $format . ', file: ' . $file_path );

			// Validate file is within allowed export directories
			$allowed_bases   = [];
			$allowed_bases[] = $this->export_dir;
			if ( function_exists( 'wp_get_upload_dir' ) ) {
				$uploads_base    = \YWCE\Support\Filesystem::exportBaseUploads();
				$allowed_bases[] = $uploads_base;
			}
			$real       = realpath( $file_path );
			$valid_path = false;
			if ( $real ) {
				foreach ( $allowed_bases as $base ) {
					$base_real = realpath( $base );
					if ( $base_real && strncmp( $real, $base_real, strlen( $base_real ) ) === 0 ) {
						$valid_path = true;
						break;
					}
				}
			}
			if ( ! $valid_path ) {
				throw new \RuntimeException( 'Invalid file path' );
			}

			// Verify file exists and is readable
			if ( ! file_exists( $file_path ) ) {
				$this->debug_log( 'YWCE: Download file not found: ' . $file_path );
				throw new \RuntimeException( 'File not found' );
			}

			if ( ! is_readable( $file_path ) ) {
				$this->debug_log( 'YWCE: Download file not readable: ' . $file_path );
				throw new \RuntimeException( 'File not readable' );
			}

			// Set the appropriate headers based on format
			$filename = basename( $file_path );

			// Set content type based on format
			switch ( $format ) {
				case 'csv':
					$content_type = 'text/csv';
					break;
				case 'excel':
					$content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
					break;
				case 'xml':
					$content_type = 'application/xml';
					break;
				case 'json':
					$content_type = 'application/json';
					break;
				default:
					$content_type = 'application/octet-stream';
			}

			// Log download attempt
			$this->debug_log( 'YWCE: Downloading file: ' . $file_path . ' with content type: ' . $content_type );
			$this->debug_log( 'YWCE: File size: ' . filesize( $file_path ) . ' bytes' );

			// Clear any output buffers
			while ( ob_get_level() ) {
				ob_end_clean();
			}

			// Set headers for download
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: ' . $content_type );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize( $file_path ) );

			// Output file contents
			if ( readfile( $file_path ) === false ) {
				$this->debug_log( 'YWCE: Failed to read file contents: ' . $file_path );
				throw new \Exception( 'Failed to read file contents' );
			}

			$this->debug_log( 'YWCE: File download completed: ' . $file_path );
		} catch ( \Exception $e ) {
			$this->debug_log( 'YWCE Download Error: ' . $e->getMessage() );
			wp_die( $e->getMessage() );
		} finally {
			// Clean up output buffer
			if ( ob_get_length() ) {
				ob_end_clean();
			}
		}
		exit;
	}
} 