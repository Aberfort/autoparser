<?php

namespace ScAutoParser\Admin\REST;

use WP_REST_Controller;
use ScAutoParser\Parser\ParserService;
use ScAutoParser\Feed\FeedRepository;

class FeedRunController extends WP_REST_Controller {

	public function __construct(
		private ParserService $parser,
		private FeedRepository $repo,
	) {
		$this->namespace = 'sc-autoparser/v1';
		$this->rest_base = 'feeds';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}/(?P<id>\\d+)/run",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	public function run( $req ) {
		$id   = (int) $req['id'];
		$feed = $this->repo->find( $id );
		if ( ! $feed ) {
			return new \WP_Error( 'not_found', 'Feed not found', array( 'status' => 404 ) );
		}

		// Позначаємо, що щойно запустили
		$this->repo->update_status( $id, 'running' );

		// СИНХРОННО викликаємо парсер — без Action Scheduler
		try {
			$this->parser->run( $id );
			$this->repo->update_status( $id, 'ok', 'Finished sync' );
		} catch ( \Throwable $e ) {
			$this->repo->update_status( $id, 'error', $e->getMessage() );
			throw $e;
		}

		// Повертаємо новий стан фіди
		$updated = $this->repo->find( $id );
		return rest_ensure_response(
			array(
				'status'       => $updated->last_status,
				'last_run'     => $updated->last_run,
				'last_message' => $updated->last_msg,
			)
		);
	}
}
