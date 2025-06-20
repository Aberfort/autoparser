<?php

namespace ScAutoParser\Core;

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
	 * @return int|WP_Error Attachment ID.
	 */
	public static function sideload_image( string $url ): int|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		return media_sideload_image( $url, 0, null, 'id' );
	}
}
