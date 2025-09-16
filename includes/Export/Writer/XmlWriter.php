<?php

namespace YWCE\Export\Writer;

class XmlWriter implements FormatWriterInterface {
	private $fh = null;
	private array $headers = [];
	private array $map = [];

	public function open( string $path, array $headers, array $map = [] ): void {
		$this->fh = fopen( $path, 'w' );
		if ( ! $this->fh ) {
			return;
		}
		$this->headers = $headers;
		$this->map     = $map;
		fwrite( $this->fh, "<?xml version=\"1.0\" encoding=\"UTF-8\"?><data>" );
	}

	public function append( array $rows ): void {
		if ( ! $this->fh ) {
			return;
		}
		foreach ( $rows as $row ) {
			fwrite( $this->fh, "<row>" );
			foreach ( $this->headers as $h ) {
				$tag = preg_replace( '/[^A-Za-z0-9_\-]/', '_', $this->map[ $h ] ?? $h );
				$val = htmlspecialchars( (string) ( $row[ $h ] ?? '' ), ENT_XML1 | ENT_COMPAT, 'UTF-8' );
				fwrite( $this->fh, "<{$tag}>{$val}</{$tag}>" );
			}
			fwrite( $this->fh, "</row>" );
		}
	}

	public function close(): void {
		if ( ! $this->fh ) {
			return;
		}
		fwrite( $this->fh, "</data>" );
		fclose( $this->fh );
		$this->fh = null;
	}
}
