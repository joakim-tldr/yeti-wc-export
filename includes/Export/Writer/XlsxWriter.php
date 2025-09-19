<?php

namespace YWCE\Export\Writer;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class XlsxWriter implements FormatWriterInterface {
	private ?Spreadsheet $sheet = null;
	private string $path = '';
	private int $rowIdx = 1;
	private array $headers = [];

	/**
	 * Open a XLSX file for writing.
	 * @param string $path
	 * @param array $headers
	 * @param array $map
	 *
	 * @return void
	 */
	public function open( string $path, array $headers, array $map = [] ): void {
		$this->path    = $path;
		$this->headers = $headers;
		$this->sheet   = new Spreadsheet();
		$ws            = $this->sheet->getActiveSheet();
		$display       = array_map( fn( $h ) => $map[ $h ] ?? $h, $headers );
		$ws->fromArray( $display, null, 'A1' );
		$this->rowIdx = 2;
	}

	/**
	 * Append rows to the XLSX file.
	 * @param array $rows
	 *
	 * @return void
	 */
	public function append( array $rows ): void {
		if ( ! $this->sheet ) {
			return;
		}
		$ws = $this->sheet->getActiveSheet();
		foreach ( $rows as $row ) {
			$ordered = [];
			foreach ( $this->headers as $h ) {
				$ordered[] = $row[ $h ] ?? '';
			}
			$ws->fromArray( $ordered, null, 'A' . $this->rowIdx ++ );
		}
	}

	/**
	 * Close the XLSX file.
	 * @return void
	 * @throws Exception
	 */
	public function close(): void {
		if ( ! $this->sheet ) {
			return;
		}
		( new Xlsx( $this->sheet ) )->save( $this->path );
		$this->sheet->disconnectWorksheets();
		$this->sheet = null;
	}
}
