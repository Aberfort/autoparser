<?php

namespace AutoParser\Core;

use Pimple\Container;
use AutoParser\Feed\Feed;

final class Plugin {

	private static ?self $instance = null;
	private Container $c;

	private function __construct() {
		$this->c = new Container();
		( new ServiceProvider() )->register( $this->c );
	}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Hook into WordPress lifecycle.
	 */
	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'i18n' ) );
		add_action( 'plugins_loaded', array( $this->c['migrator'], 'maybe_upgrade' ) );
		add_action( 'init', array( $this, 'maybe_create_upload_dir' ) );

		/* Register REST controllers */
		add_action(
			'rest_api_init',
			function () {
				$this->c['feed.rest']->register_routes();
				$this->c['feed.run_rest']->register_routes();
				$this->c['log.rest']->register_routes();
				$this->c['schedule.rest']->register_routes();
				$this->c['settings.rest']->register_routes();
			}
		);

		/* Admin UI */
		add_action( 'admin_menu', array( $this->c['admin.controller'], 'menu' ) );
		add_action(
			'admin_enqueue_scripts',
			array(
				$this->c['admin.controller'],
				'enqueue',
			)
		);

		/* Scheduler hook */
		$this->c['cron.scheduler']->register_hook();

		/* Activation & Deactivation */
		register_activation_hook( AUTOPARSER_FILE, array( $this, 'activate' ) );
		register_deactivation_hook(
			AUTOPARSER_FILE,
			array(
				$this,
				'deactivate',
			)
		);
	}

	/**
	 * Plugin activation: create tables and schedule active feeds.
	 */
	public function activate(): void {
		$this->c['migrator']->maybe_upgrade();

		foreach ( $this->c['feed.repository']->all() as $feed ) {
			if ( $feed->active ) {
				$this->c['cron.scheduler']->schedule_feed( $feed );
			}
		}
	}

	/**
	 * Plugin deactivation: unschedule all feed runs.
	 */
	public function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'autoparser_run_feed', array(), 'autoparser' );
		}
	}

	/** Load plugin textdomain */
	public function i18n(): void {
		load_plugin_textdomain(
			'autoparser',
			false,
			dirname( plugin_basename( AUTOPARSER_FILE ) ) . '/languages'
		);
	}

	/** Ensure upload directories exist */
	public function maybe_create_upload_dir(): void {
		$base = WP_CONTENT_DIR . '/uploads/autoparser';
		$log  = "$base/logs";
		if ( ! is_dir( $base ) ) {
			wp_mkdir_p( $base );
		}
		if ( ! is_dir( $log ) ) {
			wp_mkdir_p( $log );
		}
	}

	/** Expose container for testing or introspection */
	public function container(): Container {
		return $this->c;
	}
}
