<?php

namespace YWCE\Support;

/**
 * Filesystem utilities for YWCE.
 * - exportBaseUploads(): returns the uploads-based export directory path.
 * - isSafePath(): validates a path is inside one of the allowed base directories (prevents traversal).
 * - safePath(): alias to isSafePath() kept for readability and future BC.
 */
class Filesystem {
	/**
	 * Base directory inside uploads used for storing export files.
	 */
	public static function exportBaseUploads(): string {
		$upload = wp_get_upload_dir();

		return trailingslashit( $upload['basedir'] ) . 'ywce-exports/';
	}

	/**
	 * Check that the given path resolves within at least one of the allowed base directories.
	 *
	 * @param string $path          Full file path to validate.
	 * @param array  $allowedBases  List of base directory paths that are considered safe roots.
	 * @return bool                 True if the real path starts with one of the allowed base paths.
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
	 * Alias for isSafePath(). Provided for readability and potential backward compatibility
	 * with older references to a method named "safePath".
	 */
	public static function safePath( string $path, array $allowedBases ): bool {
		return self::isSafePath( $path, $allowedBases );
	}
}
