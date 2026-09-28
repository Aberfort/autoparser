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
	public function exists( int|string $feed_id, string $url ): bool {
		$post         = $this->get_mapped_post( $feed_id, $url );
		$is_duplicate = $post && $post->post_status !== 'trash';

		/**
		 * Filters whether a URL is considered a duplicate (already imported) for a feed.
		 *
		 * @param bool        $is_duplicate
		 * @param string      $url
		 * @param int|string  $feed_id
		 */
		return (bool) apply_filters( 'autoparser_is_duplicate', $is_duplicate, $url, $feed_id );
	}

	/**
	 * TRUE  → mapping exists but post missing / trashed → may re-import
	 * FALSE → otherwise
	 */
	public function is_deleted( int|string $feed_id, string $url ): bool {
		$post = $this->get_mapped_post( $feed_id, $url );

		return ! $post || $post->post_status === 'trash';
	}

	/** Upsert mapping */
	public function add( int|string $feed_id, string $url, int $post_id ): void {
		$this->db->replace(
			$this->table,
			array(
				'feed_id'    => (int) $feed_id,
				'source_url' => UrlCanonicalizer::normalize( $url ),
				'post_id'    => $post_id,
			),
			array( '%d', '%s', '%d' )
		);
	}

	/* ---------- Internals ---------- */

	private function get_mapped_post( int|string $feed_id, string $url ): ?WP_Post {
		$post_id = $this->db->get_var(
			$this->db->prepare(
				"SELECT post_id FROM {$this->table}
				 WHERE feed_id = %d AND source_url = %s LIMIT 1",
				(int) $feed_id,
				UrlCanonicalizer::normalize( $url )
			)
		);

		return $post_id ? get_post( (int) $post_id ) : null;
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
