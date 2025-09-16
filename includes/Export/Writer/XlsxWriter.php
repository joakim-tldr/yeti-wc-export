<?php

namespace YWCE\Export\Writer;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class XlsxWriter implements FormatWriterInterface {
	private ?Spreadsheet $sheet = null;
	private string $path = '';
	private int $rowIdx = 1;
	private array $headers = [];

	public function open( string $path, array $headers, array $map = [] ): void {
		$this->path    = $path;
		$this->headers = $headers;
		$this->sheet   = new Spreadsheet();
		$ws            = $this->sheet->getActiveSheet();
		$display       = array_map( fn( $h ) => $map[ $h ] ?? $h, $headers );
		$ws->fromArray( $display, null, 'A1' );
		$this->rowIdx = 2;
	}

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

	public function close(): void {
		if ( ! $this->sheet ) {
			return;
		}
		( new Xlsx( $this->sheet ) )->save( $this->path );
		$this->sheet->disconnectWorksheets();
		$this->sheet = null;
	}
}
