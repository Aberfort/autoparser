<?php

namespace AutoParser\Core;

/**
 * Misc helper methods (static).
 */
final class Helpers {

	/**
	 * Generate a random realistic desktop UA-string.
	 */
	public static function random_ua(): string {
		$uas = array(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36',
		);

		return $uas[ array_rand( $uas ) ];
	}

	/**
	 * Download remote image and sideload to Media Library.
	 *
	 * @param string $url    Remote image URL.
	 * @param string $subdir Optional uploads subdirectory (e.g. Feed::$image_dir), relative to the uploads base dir.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function sideload_image( string $url, string $subdir = '' ): int|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( '' === trim( $subdir ) ) {
			return media_sideload_image( $url, 0, null, 'id' );
		}

		$subdir           = '/' . trim( $subdir, '/' );
		$redirect_uploads = static function ( array $uploads ) use ( $subdir ): array {
			$uploads['subdir'] = $subdir;
			$uploads['path']   = $uploads['basedir'] . $subdir;
			$uploads['url']    = $uploads['baseurl'] . $subdir;

			return $uploads;
		};

		add_filter( 'upload_dir', $redirect_uploads );
		$attachment_id = media_sideload_image( $url, 0, null, 'id' );
		remove_filter( 'upload_dir', $redirect_uploads );

		return $attachment_id;
	}
}
