<?php

namespace AutoParser\Core;

use AutoParser\Feed\FeedRepository;
use AutoParser\Feed\PostMapRepository;

/**
 * Keeps the plugin's custom DB tables in sync with the code.
 *
 * Runs on activation and on every `plugins_loaded` (cheap no-op once the
 * stored "autoparser_db_version" option matches AUTOPARSER_DB_VERSION), so
 * a plain plugin update — without a deactivate/reactivate cycle — still
 * picks up schema changes via dbDelta(), which is itself idempotent.
 */
class Migrator {

	public function __construct(
		private FeedRepository $feeds,
		private PostMapRepository $post_map
	) {
	}

	public function maybe_upgrade(): void {
		if ( get_option( 'autoparser_db_version' ) === AUTOPARSER_DB_VERSION ) {
			return;
		}

		$this->feeds->create_table();
		$this->post_map->create_table();

		update_option( 'autoparser_db_version', AUTOPARSER_DB_VERSION );
	}
}
