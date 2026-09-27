<?php

namespace AutoParser\Feed;

use wpdb;
use WP_Post;
use AutoParser\Util\UrlCanonicalizer;

/**
 * Map: (feed_id, source_url) → post_id, duplicate guard.
 */
class PostMapRepository {


	private string $table;

	public function __construct( private wpdb $db ) {
		$this->table = $this->db->prefix . 'autoparser_posts_map';
	}

	/* ---------- Public API ---------- */

	/**
	 * TRUE  → post is alive (any status except ‘trash’) – skip import
	 * FALSE → post missing OR in Trash OR mapping not found
	 */
	public function exists( int|string $feedId, string $url ): bool {
		$post         = $this->getMappedPost( $feedId, $url );
		$is_duplicate = $post && $post->post_status !== 'trash';

		/**
		 * Filters whether a URL is considered a duplicate (already imported) for a feed.
		 *
		 * @param bool        $is_duplicate
		 * @param string      $url
		 * @param int|string  $feedId
		 */
		return (bool) apply_filters( 'autoparser_is_duplicate', $is_duplicate, $url, $feedId );
	}

	/**
	 * TRUE  → mapping exists but post missing / trashed → may re-import
	 * FALSE → otherwise
	 */
	public function isDeleted( int|string $feedId, string $url ): bool {
		$post = $this->getMappedPost( $feedId, $url );

		return ! $post || $post->post_status === 'trash';
	}

	/** Upsert mapping */
	public function add( int|string $feedId, string $url, int $postId ): void {
		$this->db->replace(
			$this->table,
			array(
				'feed_id'    => (int) $feedId,
				'source_url' => UrlCanonicalizer::normalize( $url ),
				'post_id'    => $postId,
			),
			array( '%d', '%s', '%d' )
		);
	}

	/* ---------- Internals ---------- */

	private function getMappedPost( int|string $feedId, string $url ): ?WP_Post {
		$postId = $this->db->get_var(
			$this->db->prepare(
				"SELECT post_id FROM {$this->table}
				 WHERE feed_id = %d AND source_url = %s LIMIT 1",
				(int) $feedId,
				UrlCanonicalizer::normalize( $url )
			)
		);

		return $postId ? get_post( (int) $postId ) : null;
	}

	/**
	 * Idempotent: dbDelta() creates the table if missing and adds any
	 * columns/keys that were added to the schema since, so this is safe
	 * to call on every migration run rather than only once.
	 */
	public function create_table(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$this->table} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			feed_id    BIGINT UNSIGNED NOT NULL,
			source_url VARCHAR(2083)   NOT NULL,
			post_id    BIGINT UNSIGNED NOT NULL,
			imported   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY feed_url (feed_id, source_url(191))
		) {$this->db->get_charset_collate()};"
		);
	}
}
