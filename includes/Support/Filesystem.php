<?php

namespace YWCE\Support;

class Filesystem {
	public static function exportBaseUploads(): string {
		$upload = wp_get_upload_dir();

		return trailingslashit( $upload['basedir'] ) . 'ywce-exports/';
	}

	public static function isSafePath( string $path, array $allowedBases ): bool {
		$real = realpath( $path );
		if ( ! $real ) {
			return false;
		}
		foreach ( $allowedBases as $base ) {
			$baseReal = realpath( $base );
			if ( $baseReal && strncmp( $real, $baseReal, strlen( $baseReal ) ) === 0 ) {
				return true;
			}
		}

		return false;
	}
}
