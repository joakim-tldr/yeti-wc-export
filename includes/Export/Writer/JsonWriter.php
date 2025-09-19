<?php

namespace YWCE\Export\Writer;

class JsonWriter implements FormatWriterInterface {
	private $fh = null;
	private bool $first = true;
	private array $headers = [];
	private array $map = [];

	/**
	 * Open a JSON file for writing.
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
		fwrite( $this->fh, "[" );
		$this->first = true;
	}

	/**
	 * Append rows to the JSON file.
	 * @param array $rows
	 *
	 * @return void
	 */
	public function append( array $rows ): void {
		if ( ! $this->fh ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( ! $this->first ) {
				fwrite( $this->fh, ",\n" );
			}
			$this->first = false;
			$obj         = [];
			foreach ( $this->headers as $h ) {
				$display         = $this->map[ $h ] ?? $h;
				$obj[ $display ] = $row[ $h ] ?? '';
			}
			fwrite( $this->fh, json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
	}

	/**
	 * Close the JSON file.
	 * @return void
	 */
	public function close(): void {
		if ( ! $this->fh ) {
			return;
		}
		fwrite( $this->fh, "]" );
		fclose( $this->fh );
		$this->fh = null;
	}
}
