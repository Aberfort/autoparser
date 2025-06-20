<?php

namespace ScAutoParser\Admin;

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

		// Головна сторінка — список лент
		add_menu_page(
			'SC Autoparser',
			'SC Autoparser',
			'manage_options',
			'sc-autoparser',
			array( $this, 'render_list' ),
			'dashicons-rss',
			65
		);

		// Окрема сторінка «Додати ленту» (можна відкривати напряму)
		add_submenu_page(
			'sc-autoparser',
			'Додати ленту',
			'Додати ленту',
			'manage_options',
			'sc-autoparser-add',
			array( $this, 'render_add' )
		);

		add_submenu_page(
			null,
			'Редагувати ленту',
			'Редагувати ленту',
			'manage_options',
			'sc-autoparser-edit',
			array( $this, 'render_edit' )
		);

		add_submenu_page(
			'sc-autoparser',
			'Журнал',
			'Журнал',
			'manage_options',
			'sc-autoparser-log',
			array( $this, 'render_log' )
		);

		add_submenu_page(
			'sc-autoparser',
			'Розклад',
			'Розклад',
			'manage_options',
			'sc-autoparser-cron',
			fn() => $this->wrapper( 'scap-root-cron' )
		);

		// Сторінка налаштувань
		add_submenu_page(
			'sc-autoparser',
			'Налаштування',
			'Налаштування',
			'manage_options',
			'sc-autoparser-settings',
			array( $this, 'render_settings' )
		);
	}

	/* ---------- SCRIPTS / STYLES ---------- */

	/**
	 * Підключаємо скрипти на всіх сторінках нашого плагіну.
	 */
	public function enqueue( string $hook ): void {

		if ( ! str_starts_with( $hook, 'toplevel_page_sc-autoparser' )
			&& ! str_contains( $hook, 'sc-autoparser-' ) ) {
			return;
		}

		$asset = include SC_AUTOPARSER_DIR . 'assets/build/index.asset.php';

		wp_enqueue_style(
			'sc-autoparser-admin',
			SC_AUTOPARSER_URL . 'assets/build/style-style.scss.css',
			array( 'wp-components' ),
			$this->version
		);

		wp_enqueue_script(
			'sc-autoparser-admin',
			SC_AUTOPARSER_URL . 'assets/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'sc-autoparser-admin',
			'scapAjax',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_localize_script(
			'sc-autoparser-admin',
			'scapSettings',
			get_option( 'scap_settings', array() )
		);
	}

	/* ---------- RENDERS ---------- */

	private function wrapper( string $id ): void {
		echo '<div class="wrap"><h1 class="wp-heading-inline">SC Autoparser</h1><hr class="wp-header-end">';
		printf( '<div id="%s"></div>', esc_attr( $id ) );
		echo '</div>';
	}

	public function render_list(): void {
		$this->wrapper( 'scap-root-list' );
	}

	public function render_add(): void {
		$this->wrapper( 'scap-root-add' );
	}

	public function render_settings(): void {
		$this->wrapper( 'scap-root-settings' );
	}

	public function render_edit(): void {
		$this->wrapper( 'scap-root-edit' );
	}

	public function render_log(): void {
		$this->wrapper( 'scap-root-log' );
	}
}
