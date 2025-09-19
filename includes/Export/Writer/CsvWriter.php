<?php

namespace YWCE\Export\Writer;

class CsvWriter implements FormatWriterInterface {
	private $fh = null;
	private array $headers = [];
	private array $map = [];

	/**
	 * Open a CSV file for writing.
	 * @param string $path
	 * @param array $headers
	 * @param array $map
	 *
	 * @return void
	 */
	public function open( string $path, array $headers, array $map = [] ): void {
		$this->fh = fopen( $path, 'w' );
		if ( ! $this->fh ) {
			return;
		}
		$this->headers = $headers;
		$this->map     = $map;
		$display       = array_map( fn( $h ) => $map[ $h ] ?? $h, $headers );
		fputcsv( $this->fh, $display );
	}

	/**
	 * Append rows to the CSV file.
	 * @param array $rows
	 *
	 * @return void
	 */
	public function append( array $rows ): void {
		if ( ! $this->fh ) {
			return;
		}
		foreach ( $rows as $row ) {
			$ordered = [];
			foreach ( $this->headers as $h ) {
				$ordered[] = $row[ $h ] ?? '';
			}
			fputcsv( $this->fh, $ordered );
		}
	}

	/**
	 * Close the CSV file.
	 * @return void
	 */
	public function close(): void {
		if ( $this->fh ) {
			fclose( $this->fh );
			$this->fh = null;
		}
	}
}
