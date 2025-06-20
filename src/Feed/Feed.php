<?php

namespace ScAutoParser\Feed;

/**
 * Value-object that represents a single Feed entity.
 */
class Feed {

	public function __construct(
		public ?int $id = null,
		public string $name = '',
		public string $url = '',
		public bool $active = true,
		public string $status = 'draft',      // 'draft' | 'publish'
		public ?string $selector = null,    // CSS-селектор
		public int $limit = 5,            // posts per run
        public int $last_ts = 0,
		public string $post_type = 'post',
		public int $author_id = 1,
		public array $categories = array(),           // term IDs
		public string $prompt = '',
		public string $detail_prompt = '',
		public string $thumbnail_mode = 'first', // 'first' | 'manual'
		public ?int $thumbnail_id = null,
		public string $image_dir = '/sc-autoparser',
		public ?string $created_at = null,
		public ?string $updated_at = null,
		public string $last_status = 'never',
		public ?string $last_run = null,
		public string $last_msg = '',
		public string $meta_title = '',
		public string $meta_description = '',
		public string $post_time = '08:00',
		public bool $predict_only = false,
		public string $ai_provider = 'gemini',
		public ?string $selector_end = null
	) {
	}
}
