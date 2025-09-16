<?php

namespace YWCE\Export\Writer;

interface FormatWriterInterface {
	public function open( string $path, array $headers, array $headerMap = [] ): void;

	public function append( array $rows ): void;

	public function close(): void;
}
