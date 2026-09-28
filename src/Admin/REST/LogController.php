<?php

namespace AutoParser\Admin\REST;

use WP_REST_Controller;
use WP_Error;

/**
 * /autoparser/v1/logs?date=YYYY-MM-DD&limit=500
 */
class LogController extends WP_REST_Controller {

	private string $log_dir;

	public function __construct( string $log_dir ) {
		$this->log_dir   = $log_dir;
		$this->namespace = 'autoparser/v1';
		$this->rest_base = 'logs';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}",
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
				),
			)
		);
	}

	/** @inheritDoc */
	public function get_items( $request ) {
		/* параметри */
		$date  = sanitize_file_name( $request->get_param( 'date' ) ?? gmdate( 'Y-m-d' ) );
		$limit = (int) ( $request->get_param( 'limit' ) ?? 500 );

		$file = "{$this->log_dir}/autoparser-{$date}.log";
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'autoparser_no_log', 'Log file not found', array( 'status' => 404 ) );
		}

		$lines = array_slice( file( $file, FILE_IGNORE_NEW_LINES ), - $limit );
		$data  = array_map(
			fn( $l ) => preg_split( '/\s+\|\s+/', $l, 3 ),
			$lines
		);

		return rest_ensure_response( $data );
	}
}
