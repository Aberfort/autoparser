<?php

namespace AutoParser\Admin\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

/**
 * /settings
 */
class SettingsController extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'autoparser/v1';
		$this->rest_base = 'settings';
	}

	public function register_routes(): void {

		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}",
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => array( 'POST', 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
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
				'autoparser_settings',
				array(
					'gemini_api_key'        => '',
					'openai_api_key'        => '',
					'global_prompt'         => '',
					'openai_model'          => '',
					'enable_fallback_proxy' => false,
				)
			)
		);
	}

	/* ====== SAVE (POST|PUT|PATCH) ====== */
	public function save( WP_REST_Request $r ): \WP_HTTP_Response {

		$opts = get_option( 'autoparser_settings', array() );

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

		// резервний проксі r.jina.ai при 403/503 від джерела — вимкнено за замовчуванням
		if ( $r->has_param( 'enable_fallback_proxy' ) ) {
			$opts['enable_fallback_proxy'] = (bool) $r['enable_fallback_proxy'];
		}

		update_option( 'autoparser_settings', $opts );

		return rest_ensure_response( $opts );
	}
}
