<?php

namespace ScAutoParser\Feed;

use ScAutoParser\Core\Logger;
use wpdb;

/**
 * CRUD around the `scap_feeds` table.
 */
class FeedRepository {

	private string $table;

	public function __construct(
		private wpdb $db,
		private Logger $log
	) {
		$this->table = $this->db->prefix . 'scap_feeds';
	}

	/* ---------- Installation ---------- */

	public function create_table(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$this->table} (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name          VARCHAR(255)    NOT NULL,
			url           TEXT            NOT NULL,
			active        TINYINT(1)      NOT NULL DEFAULT 1,
			status        VARCHAR(20)     NOT NULL DEFAULT 'draft',
			selector      VARCHAR(255)    NOT NULL,
			selector_end      VARCHAR(255) NULL,
			`limit`       INT             NOT NULL DEFAULT 5,
			last_ts         BIGINT UNSIGNED NOT NULL DEFAULT 0,
			post_type     VARCHAR(20)     NOT NULL DEFAULT 'post',
			author_id     BIGINT UNSIGNED NOT NULL DEFAULT 1,
			categories    TEXT            NULL,
			prompt        LONGTEXT        NULL,
			detail_prompt   LONGTEXT        NULL,
			thumbnail_mode    VARCHAR(10)     NOT NULL DEFAULT 'first',
			thumbnail_id      BIGINT UNSIGNED NULL,
			post_time    TIME NOT NULL DEFAULT '08:00',
			meta_title TEXT NOT NULL,          
    		meta_description TEXT NOT NULL, 
			image_dir     VARCHAR(255)    NOT NULL,
			last_status VARCHAR(20)    NOT NULL DEFAULT 'never',
 			last_run    DATETIME NULL,
 			last_msg    TEXT NULL,
 			predict_only  TINYINT(1) NOT NULL DEFAULT 0,
 			ai_provider    VARCHAR(20) NOT NULL DEFAULT 'gemini',
 			created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$this->db->get_charset_collate()};";

		dbDelta( $sql );
	}

	/* ---------- CRUD ---------- */

	public function all(): array {
		$rows = $this->db->get_results( "SELECT * FROM {$this->table} ORDER BY id DESC", ARRAY_A );

		return array_map( array( $this, 'row_to_entity' ), $rows );
	}

	public function find( int $id ): ?Feed {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? $this->row_to_entity( $row ) : null;
	}

	public function save( Feed $feed ): int {
		$data = array(
			'name'             => $feed->name,
			'url'              => $feed->url,
			'active'           => (int) $feed->active,
			'status'           => $feed->status,
			'selector'         => $feed->selector,
			'selector_end'     => $feed->selector_end,
			'limit'            => $feed->limit,
			'post_type'        => $feed->post_type,
			'author_id'        => $feed->author_id,
			'categories'       => wp_json_encode( $feed->categories ),
			'prompt'           => $feed->prompt,
			'detail_prompt'    => $feed->detail_prompt,
			'thumbnail_mode'   => $feed->thumbnail_mode,
			'thumbnail_id'     => $feed->thumbnail_id,
			'post_time'        => $feed->post_time,
			'meta_title'       => $feed->meta_title,
			'meta_description' => $feed->meta_description,
			'image_dir'        => $feed->image_dir,
			'predict_only'     => (int) $feed->predict_only,
			'ai_provider'      => $feed->ai_provider,
		);

		if ( $feed->id ) {
			$this->db->update( $this->table, $data, array( 'id' => $feed->id ) );

			return $feed->id;
		}

		$this->db->insert( $this->table, $data );

		return (int) $this->db->insert_id;
	}

	public function delete( int $id ): bool {
		return (bool) $this->db->delete( $this->table, array( 'id' => $id ) );
	}

	/* ---------- Helpers ---------- */

	private function row_to_entity( array $row ): Feed {
		return new Feed(
			id: (int) $row['id'],
			name: $row['name'],
			url: $row['url'],
			active: (bool) $row['active'],
			status: $row['status'],
			selector: $row['selector'],
			selector_end: $row['selector_end'],
			limit: (int) $row['limit'],
            last_ts: isset( $row['last_ts'] ) ? (int) $row['last_ts'] : 0,
            post_type: $row['post_type'],
			author_id: (int) $row['author_id'],
			categories: (array) json_decode( $row['categories'], true ),
			prompt: $row['prompt'],
			detail_prompt: $row['detail_prompt'] ?? '',
			thumbnail_mode: $row['thumbnail_mode'] ?? 'first',
			thumbnail_id: isset( $row['thumbnail_id'] ) ? (int) $row['thumbnail_id'] : null,
			post_time: $row['post_time'],
			meta_title: $row['meta_title'] ?? '',
			meta_description: $row['meta_description'] ?? '',
			image_dir: $row['image_dir'],
			created_at: $row['created_at'],
			updated_at: $row['updated_at'],
			last_status: $row['last_status'] ?? 'never',
			last_run: $row['last_run'] ?? null,
			last_msg: $row['last_msg'] ?? '',
			predict_only: (bool) $row['predict_only'],
			ai_provider: $row['ai_provider'] ?? 'gemini',
		);
	}

	public function update_status( int $id, string $status, string $msg = '' ): void {
		$this->db->update(
			$this->table,
			array(
				'last_status' => $status,
				'last_run'    => current_time( 'mysql' ),
				'last_msg'    => $msg,
			),
			array( 'id' => $id )
		);
	}

    public function update_last_ts( int $id, int $ts ): void {
        $this->db->update(
            $this->table,
            [ 'last_ts' => $ts ],
            [ 'id' => $id ],
            [ '%d' ],
            [ '%d' ]
        );
    }
}
