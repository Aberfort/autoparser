<?php

namespace ScAutoParser\Admin\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

/**
 * /settings
 */
class SettingsController extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'sc-autoparser/v1';
		$this->rest_base = 'settings';
	}

	public function register_routes(): void {

		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}",
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => [ 'POST', 'PUT', 'PATCH' ],
					'callback'            => [ $this, 'save' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
			]
		);
	}

	/* ===== permissions ===== */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/* ====== GET ====== */
	public function get(): \WP_HTTP_Response {
		return rest_ensure_response(
			get_option(
				'scap_settings',
				[
					'gemini_api_key' => '',
					'openai_api_key' => '',
					'global_prompt'  => '',
					'openai_model'   => '',
				]
			)
		);
	}

	/* ====== SAVE (POST|PUT|PATCH) ====== */
	public function save( WP_REST_Request $r ): \WP_HTTP_Response {

		$opts = get_option( 'scap_settings', [] );

		$opts['fixtures_api_key'] = sanitize_text_field( $r->get_param( 'fixtures_api_key' ) ?? '' );

		// API-key
		if ( $r->has_param( 'gemini_api_key' ) ) {
			$opts['gemini_api_key'] = sanitize_text_field( $r['gemini_api_key'] );
		}

		// OpenAI key
		if ( $r->has_param( 'openai_api_key' ) ) {
			$opts['openai_api_key'] = sanitize_text_field( $r['openai_api_key'] );
		}

		// OpenAI model
		if ( $r->has_param( 'openai_model' ) ) {
			$opts['openai_model'] = sanitize_text_field( $r['openai_model'] );
		}

		// глобальний промпт
		if ( $r->has_param( 'global_prompt' ) ) {
			$opts['global_prompt'] = sanitize_textarea_field( $r['global_prompt'] );
		}

		update_option( 'scap_settings', $opts );

		return rest_ensure_response( $opts );
	}
}
