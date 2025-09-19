<?php

namespace YWCE\Support;

/**
 * Filesystem utilities.
 * - exportBaseUploads(): returns the uploads-based export directory path.
 * - isSafePath(): validates a path is inside one of the allowed base directories (prevents traversal).
 * - safePath(): alias to isSafePath() kept for readability and future BC.
 */
class Filesystem {

	/**
	 * Get the uploads-based export directory path.
	 * @return string
	 */
	public static function exportBaseUploads(): string {
		$upload = wp_get_upload_dir();

		return trailingslashit( $upload['basedir'] ) . 'ywce-exports/';
	}

	/**
	 * Validate a path is inside one of the allowed base directories (prevents traversal).
	 *
	 * @param string $path
	 * @param array $allowedBases
	 *
	 * @return bool
	 */
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

	/**
	 * Alias to isSafePath() kept for readability and future BC.
	 *
	 * @param string $path
	 * @param array $allowedBases
	 *
	 * @return bool
	 */
	public static function safePath( string $path, array $allowedBases ): bool {
		return self::isSafePath( $path, $allowedBases );
	}
}
