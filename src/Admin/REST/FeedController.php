<?php

namespace ScAutoParser\Admin\REST;

use ScAutoParser\Feed\FeedRepository;
use ScAutoParser\Feed\Feed;
use ScAutoParser\Cron\Scheduler;
use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

class FeedController extends WP_REST_Controller {

	public function __construct(
		private FeedRepository $repo,
		private Scheduler $scheduler
	) {
		$this->namespace = 'sc-autoparser/v1';
		$this->rest_base = 'feeds';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}",
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}/(?P<id>\d+)",
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions' ),
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions' ),
				),
			)
		);
	}

	public function get_items( $request ) {
		return rest_ensure_response(
			array_map( array( $this, 'to_array' ), $this->repo->all() )
		);
	}

	public function get_item( $request ) {
		$feed = $this->repo->find( (int) $request['id'] );

		return $feed
			? rest_ensure_response( $this->to_array( $feed ) )
			: new WP_Error( 'scap_not_found', 'Feed not found', array( 'status' => 404 ) );
	}

	public function create_item( $request ) {
		$feed     = $this->from_request( $request );
		$feed->id = $this->repo->save( $feed );
		if ( $feed->active ) {
			$this->scheduler->schedule_feed( $feed );
		}

		return rest_ensure_response( $this->to_array( $feed ) );
	}

	public function update_item( $request ) {
		$feed = $this->repo->find( (int) $request['id'] );
		if ( ! $feed ) {
			return new WP_Error( 'scap_not_found', 'Feed not found', array( 'status' => 404 ) );
		}
		$feed = $this->from_request( $request, $feed );
		$this->repo->save( $feed );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sc_autoparser_run_feed', array( $feed->id ), 'sc-autoparser' );
		}
		if ( $feed->active ) {
			$this->scheduler->schedule_feed( $feed );
		}

		return rest_ensure_response( $this->to_array( $feed ) );
	}

	public function delete_item( $request ) {
		$id = (int) $request['id'];
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sc_autoparser_run_feed', array( $id ), 'sc-autoparser' );
		}
		$ok = $this->repo->delete( $id );

		return $ok
			? rest_ensure_response( array( 'deleted' => true ) )
			: new WP_Error( 'scap_delete_failed', 'Delete failed', array( 'status' => 500 ) );
	}

	public function permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	private function from_request( WP_REST_Request $req, ?Feed $feed = null ): Feed {
		$feed                   ??= new Feed();
		$feed->name             = $req->get_param( 'name' ) ?? $feed->name;
		$feed->url              = $req->get_param( 'url' ) ?? $feed->url;
		$feed->active           = $req->has_param( 'active' ) ? (bool) $req['active'] : $feed->active;
		$feed->status           = $req->get_param( 'status' ) ?? $feed->status;
		$feed->selector         = $req->get_param( 'selector' ) ?? $feed->selector;
		$feed->selector_end     = $req->get_param( 'selector_end' ) ?? $feed->selector_end;
		$feed->limit            = (int) ( $req->get_param( 'limit' ) ?? $feed->limit );
		$feed->post_type        = $req->get_param( 'post_type' ) ?? $feed->post_type;
		$feed->author_id        = (int) ( $req->get_param( 'author_id' ) ?? $feed->author_id );
		$feed->categories       = $req->get_param( 'categories' ) ?? $feed->categories;
		$feed->prompt           = $req->get_param( 'prompt' ) ?? $feed->prompt;
		$feed->detail_prompt    = $req->get_param( 'detail_prompt' ) ?? $feed->detail_prompt;
		$feed->thumbnail_mode   = $req->get_param( 'thumbnail_mode' ) ?? $feed->thumbnail_mode;
		$feed->thumbnail_id     = $req->get_param( 'thumbnail_id' ) ?? $feed->thumbnail_id;
		$feed->post_time        = $req->get_param( 'post_time' ) ?? $feed->post_time;
		$feed->meta_title       = $req->get_param( 'meta_title' ) ?? $feed->meta_title;
		$feed->meta_description = $req->get_param( 'meta_description' ) ?? $feed->meta_description;
		$feed->image_dir        = $req->get_param( 'image_dir' ) ?? $feed->image_dir;
		$feed->predict_only     = $req->has_param( 'predict_only' ) ? (bool) $req['predict_only'] : $feed->predict_only;
		$feed->ai_provider      = $req->get_param( 'ai_provider' ) ?? $feed->ai_provider;

		return $feed;
	}

	private function to_array( Feed $f ): array {
		return array(
			'id'               => $f->id,
			'name'             => $f->name,
			'url'              => $f->url,
			'active'           => $f->active,
			'status'           => $f->status,
			'selector'         => $f->selector,
			'selector_end'     => $f->selector_end,
			'limit'            => $f->limit,
			'post_type'        => $f->post_type,
			'author_id'        => $f->author_id,
			'categories'       => $f->categories,
			'prompt'           => $f->prompt,
			'detail_prompt'    => $f->detail_prompt,
			'thumbnail_mode'   => $f->thumbnail_mode,
			'thumbnail_id'     => $f->thumbnail_id,
			'post_time'        => $f->post_time,
			'meta_title'       => $f->meta_title,
			'meta_description' => $f->meta_description,
			'image_dir'        => $f->image_dir,
			'created_at'       => $f->created_at,
			'updated_at'       => $f->updated_at,
			'last_status'      => $f->last_status,
			'last_run'         => $f->last_run,
			'last_msg'         => $f->last_msg,
			'predict_only'     => $f->predict_only,
			'ai_provider'      => $f->ai_provider,
		);
	}
}
