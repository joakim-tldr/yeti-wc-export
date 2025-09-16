<?php
namespace YWCE;
/**
 * Format Handler Class
 */
class YWCE_Format_Handler {

	/**
	 * Remember headers used to create each file so appends keep exact column order.
	 * @var array<string, array>
	 */
	private array $file_headers = [];
	/**
	 * Remember display header mapping per file.
	 * @var array<string, array>
	 */
	private array $file_header_mapping = [];
	/**
	 * Active format writers keyed by file path
	 * @var array<string, \YWCE\Export\Writer\FormatWriterInterface>
	 */
	private array $writers = [];

	/**
	 * Debug log utility
	 *
	 * @param string $message The message to log
	 *
	 * @return void
	 */
	private function debug_log( $message ) {
		// Only log in development environments
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $message );
		}
	}

	/**
	 * Get file extension based on format
	 */
	public function get_file_extension( $format ) {
		switch ( $format ) {
			case 'excel':
				return 'xlsx';
			case 'xml':
				return 'xml';
			case 'json':
				return 'json';
			case 'csv':
			default:
				return 'csv';
		}
	}

	/**
	 * Create export file
	 *
	 * @param string $file_path Path to the export file
	 * @param string $format Export format (csv, excel, xml, json)
	 * @param array $data Export data
	 * @param array $headers Column headers in the desired order
	 * @param array $header_mapping Mapping of original headers to custom headers
	 */
	private function get_or_create_writer( string $file_path, string $format, array $headers, array $header_mapping ) {
		if ( isset( $this->writers[ $file_path ] ) ) {
			return $this->writers[ $file_path ];
		}
		try {
			switch ( $format ) {
				case 'csv':
					$writer = new \YWCE\Export\Writer\CsvWriter();
					break;
				case 'json':
					$writer = new \YWCE\Export\Writer\JsonWriter();
					break;
				case 'xml':
					$writer = new \YWCE\Export\Writer\XmlWriter();
					break;
				case 'excel':
					if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\Spreadsheet' ) ) {
						return null;
					}
					$writer = new \YWCE\Export\Writer\XlsxWriter();
					break;
				default:
					return null;
			}
			$writer->open( $file_path, $headers, $header_mapping );
			$this->writers[ $file_path ] = $writer;
			return $writer;
		} catch ( \Throwable $e ) {
			$this->debug_log( 'Failed to create writer: ' . $e->getMessage() );
			return null;
		}
	}

	public function create_export_file( $file_path, $format, $data, $headers = [], $header_mapping = [] ): void {
		// Ensure the directory exists
		$dir = dirname( $file_path );
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				$this->debug_log( 'Export directory is not writable: ' . $dir );

				return;
			}
		}

		// Check if directory is writable
		if ( ! is_writable( $dir ) ) {
			$this->debug_log( 'Export directory is not writable: ' . $dir );

			return;
		}

		if ( empty( $headers ) && ! empty( $data ) ) {
			$headers = array_keys( $data[0] );
		}

		$display_headers = $headers;
		if ( ! empty( $header_mapping ) ) {
			$display_headers = array_map( function ( $header ) use ( $header_mapping ) {
				return $header_mapping[ $header ] ?? $header;
			}, $headers );
		}
		// Remember headers and mapping for this file so appends preserve order
		$this->file_headers[ $file_path ]        = $headers;
		$this->file_header_mapping[ $file_path ] = $header_mapping;

		// New writer-based path: open writer and append initial data, fallback to legacy on failure
		$writer = $this->get_or_create_writer( $file_path, $format, $headers, $header_mapping );
		if ( $writer ) {
			if ( ! empty( $data ) ) {
				$writer->append( $data );
			}
			return;
		}

		try {
			switch ( $format ) {
				case 'csv':
					$output = @fopen( $file_path, 'w' );
					if ( ! $output ) {
						$this->debug_log( 'Failed to open file for writing: ' . $file_path );

						return;
					}
					fputcsv( $output, $display_headers );
					foreach ( $data as $row ) {
						$ordered_row = [];
						foreach ( $headers as $header ) {
							$ordered_row[] = $row[ $header ] ?? '';
						}
						fputcsv( $output, $ordered_row );
					}
					fclose( $output );
					break;

				case 'json':
					if ( ! empty( $header_mapping ) ) {
						$json_data = [];
						foreach ( $data as $row ) {
							$json_row = [];
							foreach ( $row as $key => $value ) {
								$display_key              = $header_mapping[ $key ] ?? $key;
								$json_row[ $display_key ] = $value;
							}
							$json_data[] = $json_row;
						}
						$json_content = json_encode( $json_data, JSON_PRETTY_PRINT );
					} else {
						$json_content = json_encode( $data, JSON_PRETTY_PRINT );
					}

					if ( $json_content === false ) {
						$this->debug_log( 'JSON encoding failed for export file: ' . $file_path );

						return;
					}

					$result = @file_put_contents( $file_path, $json_content );
					if ( $result === false ) {
						$this->debug_log( 'Failed to write JSON to file: ' . $file_path );
					}
					break;

				case 'xml':
					try {
						$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><data></data>' );
						if ( ! empty( $header_mapping ) ) {
							$xml_data = [];
							foreach ( $data as $row ) {
								$xml_row = [];
								foreach ( $row as $key => $value ) {
									$display_key             = $header_mapping[ $key ] ?? $key;
									$xml_row[ $display_key ] = $value;
								}
								$xml_data[] = $xml_row;
							}
							$this->array_to_xml( $xml_data, $xml );
						} else {
							$this->array_to_xml( $data, $xml );
						}
						$xml_content = $xml->asXML();
						if ( $xml_content === false ) {
							$this->debug_log( 'XML generation failed for export file: ' . $file_path );

							return;
						}

						$result = @file_put_contents( $file_path, $xml_content );
						if ( $result === false ) {
							$this->debug_log( 'Failed to write XML to file: ' . $file_path );
						}
					} catch ( \Exception $e ) {
						$this->debug_log( 'XML processing error: ' . $e->getMessage() );
					}
					break;

				case 'excel':
					try {
						$temp_csv = $file_path . '.temp.csv';
						$output   = @fopen( $temp_csv, 'w' );
						if ( ! $output ) {
							$this->debug_log( 'Failed to open temporary CSV file for Excel export: ' . $temp_csv );

							return;
						}
						fputcsv( $output, $display_headers );
						foreach ( $data as $row ) {
							$ordered_row = [];
							foreach ( $headers as $header ) {
								$ordered_row[] = $row[ $header ] ?? '';
							}
							fputcsv( $output, $ordered_row );
						}
						fclose( $output );
					} catch ( \Exception $e ) {
						$this->debug_log( 'Excel processing error: ' . $e->getMessage() );
					}
					break;
			}
		} catch ( \Exception $e ) {
			$this->debug_log( 'Error creating export file: ' . $e->getMessage() );
		}
	}

	/**
	 * Append to export file
	 *
	 * @param string $file_path Path to the export file
	 * @param string $format Export format (csv, excel, xml, json)
	 * @param array $data Export data
	 * @param array $header_mapping Mapping of original headers to custom headers
	 */
	public function append_to_export_file( $file_path, $format, $data, $header_mapping = [] ): void {
		// Check if file exists and is writable
		if ( ! file_exists( $file_path ) ) {
			$this->debug_log( 'Export file does not exist: ' . $file_path );

			return;
		}

		if ( ! is_writable( $file_path ) ) {
			$this->debug_log( 'Export file is not writable: ' . $file_path );

			return;
		}

		// If a streaming writer is active for this file, append via writer and return
		if ( isset( $this->writers[ $file_path ] ) ) {
			try {
				$this->writers[ $file_path ]->append( $data );
				return;
			} catch ( \Throwable $e ) {
				$this->debug_log( 'Writer append failed, falling back to legacy: ' . $e->getMessage() );
			}
		}

		try {
			switch ( $format ) {
				case 'csv':
					$output = @fopen( $file_path, 'a' );
					if ( ! $output ) {
						$this->debug_log( 'Failed to open file for appending: ' . $file_path );

						return;
					}
					$headers = $this->file_headers[ $file_path ] ?? [];
					foreach ( $data as $row ) {
						$ordered_row = [];
						$use_headers = ! empty( $headers ) ? $headers : array_keys( $row );
						foreach ( $use_headers as $header ) {
							$ordered_row[] = $row[ $header ] ?? '';
						}
						fputcsv( $output, $ordered_row );
					}
					fclose( $output );
					break;

				case 'json':
					$json_content = @file_get_contents( $file_path );
					if ( $json_content === false ) {
						$this->debug_log( 'Failed to read existing JSON file: ' . $file_path );

						return;
					}

					$existing_data = json_decode( $json_content, true );
					if ( $existing_data === null && json_last_error() !== JSON_ERROR_NONE ) {
						$this->debug_log( 'JSON decoding failed: ' . json_last_error_msg() );
						$existing_data = [];
					}

					if ( ! empty( $header_mapping ) ) {
						$json_data = [];
						foreach ( $data as $row ) {
							$json_row = [];
							foreach ( $row as $key => $value ) {
								$display_key              = $header_mapping[ $key ] ?? $key;
								$json_row[ $display_key ] = $value;
							}
							$json_data[] = $json_row;
						}
						$new_data = array_merge( $existing_data, $json_data );
					} else {
						$new_data = array_merge( $existing_data, $data );
					}

					$json_content = json_encode( $new_data, JSON_PRETTY_PRINT );
					if ( $json_content === false ) {
						$this->debug_log( 'JSON encoding failed for export file: ' . $file_path );

						return;
					}

					$result = @file_put_contents( $file_path, $json_content );
					if ( $result === false ) {
						$this->debug_log( 'Failed to write JSON to file: ' . $file_path );
					}
					break;

				case 'xml':
					try {
						$temp_file     = $file_path . '.temp';
						$existing_data = [];
						if ( file_exists( $temp_file ) ) {
							$temp_content = @file_get_contents( $temp_file );
							if ( $temp_content === false ) {
								$this->debug_log( 'Failed to read temporary XML file: ' . $temp_file );

								return;
							}
							$existing_data = unserialize( $temp_content, [ 'allowed_classes' => false ] );
							if ( $existing_data === false ) {
								$this->debug_log( 'Failed to unserialize temporary XML data' );
								$existing_data = [];
							}
						}

						if ( ! empty( $header_mapping ) ) {
							$xml_data = [];
							foreach ( $data as $row ) {
								$xml_row = [];
								foreach ( $row as $key => $value ) {
									$display_key             = $header_mapping[ $key ] ?? $key;
									$xml_row[ $display_key ] = $value;
								}
								$xml_data[] = $xml_row;
							}
							$new_data = array_merge( $existing_data, $xml_data );
						} else {
							$new_data = array_merge( $existing_data, $data );
						}

						$result = @file_put_contents( $temp_file, serialize( $new_data ) );
						if ( $result === false ) {
							$this->debug_log( 'Failed to write temporary XML data: ' . $temp_file );
						}
					} catch ( \Exception $e ) {
						$this->debug_log( 'XML processing error: ' . $e->getMessage() );
					}
					break;

				case 'excel':
					try {
						$temp_csv = $file_path . '.temp.csv';
						$output   = @fopen( $temp_csv, 'a' );
						if ( ! $output ) {
							$this->debug_log( 'Failed to open temporary CSV file for Excel export: ' . $temp_csv );
							
							return;
						}
						$headers = $this->file_headers[ $file_path ] ?? [];
						foreach ( $data as $row ) {
							$ordered_row = [];
							$use_headers = ! empty( $headers ) ? $headers : array_keys( $row );
							foreach ( $use_headers as $header ) {
								$ordered_row[] = $row[ $header ] ?? '';
							}
							fputcsv( $output, $ordered_row );
						}
						fclose( $output );
					} catch ( \Exception $e ) {
						$this->debug_log( 'Excel processing error: ' . $e->getMessage() );
					}
					break;
			}
		} catch ( \Exception $e ) {
			$this->debug_log( 'Error appending to export file: ' . $e->getMessage() );
		}
	}

	/**
	 * Finalize export file (for formats that need post-processing)
	 */
	public function finalize_export_file( $file_path, $format, $headers ): void {
		try {
			// Check if the file path is valid
			if ( empty( $file_path ) ) {
				$this->debug_log( 'YWCE: Empty file path for finalization' );

				return;
			}

			$this->debug_log( 'YWCE: Finalizing file: ' . $file_path . ' with format: ' . $format );

				// If a streaming writer is active for this file, close and return
				if ( isset( $this->writers[ $file_path ] ) ) {
					try {
						$this->writers[ $file_path ]->close();
					} catch ( \Throwable $e ) {
						$this->debug_log( 'Error closing writer: ' . $e->getMessage() );
					}
					unset( $this->writers[ $file_path ] );
					return;
				}

				switch ( $format ) {
				case 'xml':
					$temp_file = $file_path . '.temp';
					if ( file_exists( $temp_file ) ) {
						$temp_content = @file_get_contents( $temp_file );
						if ( $temp_content === false ) {
							$this->debug_log( 'Failed to read temporary XML file during finalization: ' . $temp_file );

							return;
						}

						$all_data = unserialize( $temp_content, [ 'allowed_classes' => false ] );
						if ( $all_data === false ) {
							$this->debug_log( 'Failed to unserialize temporary XML data during finalization' );

							return;
						}

						try {
							$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><data></data>' );
							$this->array_to_xml( $all_data, $xml );
							$xml_content = $xml->asXML();

							if ( $xml_content === false ) {
								$this->debug_log( 'XML generation failed during finalization: ' . $file_path );

								return;
							}

							$result = @file_put_contents( $file_path, $xml_content );
							if ( $result === false ) {
								$this->debug_log( 'Failed to write XML to file during finalization: ' . $file_path );

								return;
							}

							if ( file_exists( $temp_file ) ) {
								@unlink( $temp_file );
							}
						} catch ( \Exception $e ) {
							$this->debug_log( 'XML processing error during finalization: ' . $e->getMessage() );
						}
					}
					break;

				case 'excel':
					$temp_csv = $file_path . '.temp.csv';
					if ( file_exists( $temp_csv ) ) {
						try {
							$this->debug_log( 'YWCE: Finalizing Excel file: ' . $file_path );
							$this->debug_log( 'YWCE: Temporary CSV file exists: ' . $temp_csv );

							if ( class_exists( 'PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
								$this->debug_log( 'YWCE: PhpSpreadsheet class exists, using it to convert CSV to XLSX' );

								try {
									$this->debug_log( 'YWCE: Loading CSV file with PhpSpreadsheet' );
									$reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();

									// Set options for PHP 8.0 compatibility
									$reader->setInputEncoding( 'UTF-8' );
									$reader->setDelimiter( ',' );
									$reader->setEnclosure( '"' );
									$reader->setSheetIndex( 0 );

									$spreadsheet = $reader->load( $temp_csv );

									$this->debug_log( 'YWCE: Creating Xlsx writer' );
									$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );

									// Set options for PHP 8.0 compatibility
									$writer->setOffice2003Compatibility( false );

									$this->debug_log( 'YWCE: Saving Excel file to: ' . $file_path );
									$writer->save( $file_path );

									if ( file_exists( $file_path ) ) {
										$this->debug_log( 'YWCE: Successfully created Excel file: ' . $file_path . ', size: ' . filesize( $file_path ) . ' bytes' );
									} else {
										$this->debug_log( 'YWCE: Failed to create Excel file after save operation: ' . $file_path );
									}
								} catch ( \PhpOffice\PhpSpreadsheet\Reader\Exception $e ) {
									$this->debug_log( 'YWCE: PhpSpreadsheet Reader error: ' . $e->getMessage() );

									$this->debug_log( 'YWCE: Falling back to copying CSV file' );
									if ( ! copy( $temp_csv, $file_path ) ) {
										$this->debug_log( 'YWCE: Failed to copy CSV as fallback for Excel: ' . $file_path );
									} else {
										$this->debug_log( 'YWCE: Successfully copied CSV as fallback: ' . $file_path );
									}
								} catch ( \PhpOffice\PhpSpreadsheet\Writer\Exception $e ) {
									$this->debug_log( 'YWCE: PhpSpreadsheet Writer error: ' . $e->getMessage() );

									$this->debug_log( 'YWCE: Falling back to copying CSV file' );
									if ( ! copy( $temp_csv, $file_path ) ) {
										$this->debug_log( 'YWCE: Failed to copy CSV as fallback for Excel: ' . $file_path );
									} else {
										$this->debug_log( 'YWCE: Successfully copied CSV as fallback: ' . $file_path );
									}
								} catch ( \Exception $e ) {
									$this->debug_log( 'YWCE: PhpSpreadsheet error: ' . $e->getMessage() );

									$this->debug_log( 'YWCE: Falling back to copying CSV file' );
									if ( ! copy( $temp_csv, $file_path ) ) {
										$this->debug_log( 'YWCE: Failed to copy CSV as fallback for Excel: ' . $file_path );
									} else {
										$this->debug_log( 'YWCE: Successfully copied CSV as fallback: ' . $file_path );
									}
								}
							} else {
								// PhpSpreadsheet not available, use CSV as fallback
								$this->debug_log( 'YWCE: PhpSpreadsheet not available, using CSV as fallback' );

								if ( ! copy( $temp_csv, $file_path ) ) {
									$this->debug_log( 'YWCE: Failed to finalize Excel file (PhpSpreadsheet not available): ' . $file_path );
								} else {
									$this->debug_log( 'YWCE: Successfully copied CSV as fallback for Excel: ' . $file_path );
								}
							}

							if ( file_exists( $temp_csv ) ) {
								@unlink( $temp_csv );
								$this->debug_log( 'YWCE: Removed temporary CSV file: ' . $temp_csv );
							}
						} catch ( \Exception $e ) {
							$this->debug_log( 'YWCE: Excel processing error during finalization: ' . $e->getMessage() );
						}

						if ( ! file_exists( $temp_csv ) ) {
							$this->debug_log( 'YWCE: Temporary CSV file not found for Excel finalization: ' . $temp_csv );
						}
					}
					break;
			}
		} catch ( \Exception $e ) {
			$this->debug_log( 'Error finalizing export file: ' . $e->getMessage() );
		}
	}

	/**
	 * Helper function to convert array to XML
	 */
	private function array_to_xml( $data, &$xml ): void {
		foreach ( $data as $key => $value ) {
			// For numeric keys, use 'item' as the tag name
			if ( is_numeric( $key ) ) {
				$key = 'item';
			}

			// Sanitize the key to be a valid XML element name
			$key = preg_replace( '/[^a-zA-Z0-9_]/', '_', $key );
			if ( is_numeric( substr( $key, 0, 1 ) ) ) {
				$key = 'item_' . $key;
			}

			if ( is_array( $value ) ) {
				$subnode = $xml->addChild( $key );
				$this->array_to_xml( $value, $subnode );
			} else {
				// Convert null values to empty string
				if ( $value === null ) {
					$value = '';
				}

				// Convert boolean values to string
				if ( is_bool( $value ) ) {
					$value = $value ? 'true' : 'false';
				}

				// Convert numeric values to string
				if ( is_numeric( $value ) ) {
					$value = (string) $value;
				}

				// Properly escape special characters
				$value = htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );

				// Add the value as a child element
				$xml->addChild( $key, $value );
			}
		}
	}
} 