<?php

namespace AutoParser\Admin\REST;

use WP_REST_Controller;
use AutoParser\Parser\ParserService;
use AutoParser\Feed\FeedRepository;

class FeedRunController extends WP_REST_Controller {

	public function __construct(
		private ParserService $parser,
		private FeedRepository $repo,
	) {
		$this->namespace = 'autoparser/v1';
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

		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}/preview",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'url'          => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'selector'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'selector_end' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Dry-run: fetch a URL and show what a selector would extract,
	 * without saving anything, publishing, or calling the AI provider.
	 * Lets an admin check a selector before creating/editing a feed.
	 */
	public function preview( $req ) {
		try {
			$result = $this->parser->previewExtract(
				(string) $req->get_param( 'url' ),
				(string) $req->get_param( 'selector' ),
				$req->get_param( 'selector_end' ) ?: null
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'preview_failed', $e->getMessage(), array( 'status' => 422 ) );
		}

		// Never return raw scraped HTML to the browser — it's third-party
		// content the admin's browser has no reason to trust or render.
		return rest_ensure_response(
			array(
				'title'           => $result['title'],
				'content_excerpt' => wp_trim_words( wp_strip_all_tags( $result['content'] ), 80, '…' ),
				'content_length'  => $result['content_length'],
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
