<?php

namespace AutoParser\Admin;

/**
 * Рендер адмін-сторінок та підключення React-бандла.
 */
class Controller {

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	/* ---------- MENU ---------- */

	public function menu(): void {

		add_menu_page(
			__( 'Autoparser', 'autoparser' ),
			__( 'Autoparser', 'autoparser' ),
			'manage_options',
			'autoparser',
			array( $this, 'render_feeds' ),
			'dashicons-rss',
			65
		);

		add_submenu_page(
			'autoparser',
			__( 'Ленти', 'autoparser' ),
			__( 'Ленти', 'autoparser' ),
			'manage_options',
			'autoparser',
			array( $this, 'render_feeds' )
		);

		add_submenu_page(
			'autoparser',
			__( 'Активність', 'autoparser' ),
			__( 'Активність', 'autoparser' ),
			'manage_options',
			'autoparser-activity',
			array( $this, 'render_activity' )
		);

		add_submenu_page(
			'autoparser',
			__( 'Налаштування', 'autoparser' ),
			__( 'Налаштування', 'autoparser' ),
			'manage_options',
			'autoparser-settings',
			array( $this, 'render_settings' )
		);
	}

	/* ---------- SCRIPTS / STYLES ---------- */

	/**
	 * Підключаємо скрипти на всіх сторінках нашого плагіну.
	 */
	public function enqueue( string $hook ): void {

		if ( ! str_starts_with( $hook, 'toplevel_page_autoparser' )
			&& ! str_contains( $hook, 'autoparser-' ) ) {
			return;
		}

		$asset = include AUTOPARSER_DIR . 'assets/build/index.asset.php';

		wp_enqueue_style(
			'autoparser-admin',
			AUTOPARSER_URL . 'assets/build/style-style.scss.css',
			array( 'wp-components' ),
			$this->version
		);

		wp_enqueue_script(
			'autoparser-admin',
			AUTOPARSER_URL . 'assets/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/* ---------- RENDERS ---------- */

	private function wrapper( string $id ): void {
		echo '<div class="wrap"><h1 class="wp-heading-inline">Autoparser</h1><hr class="wp-header-end">';
		printf( '<div id="%s"></div>', esc_attr( $id ) );
		echo '</div>';
	}

	public function render_feeds(): void {
		$this->wrapper( 'autoparser-root-feeds' );
	}

	public function render_activity(): void {
		$this->wrapper( 'autoparser-root-activity' );
	}

	public function render_settings(): void {
		$this->wrapper( 'autoparser-root-settings' );
	}

	/**
	 * Dismissible notice on the plugin's own screens when no AI provider
	 * key is configured yet — rewrite will fail on the first run otherwise.
	 */
	public function maybe_missing_api_key_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( $screen->id, 'autoparser' ) ) {
			return;
		}

		$opts = get_option( 'autoparser_settings', array() );
		if ( ! empty( $opts['gemini_api_key'] ) || ! empty( $opts['openai_api_key'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Щоб рерайт статей працював, додайте ключ Gemini або OpenAI.', 'autoparser' ),
			esc_url( admin_url( 'admin.php?page=autoparser-settings' ) ),
			esc_html__( 'Перейти в налаштування', 'autoparser' )
		);
	}
}
