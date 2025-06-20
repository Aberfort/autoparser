<?php

namespace ScAutoParser\Admin\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;
use ScAutoParser\Parser\ParserService;

/**
 * REST-контролер для /cron
 */
class ScheduleController extends WP_REST_Controller {

	public function __construct(
		private ParserService $parser
	) {
		$this->namespace = 'sc-autoparser/v1';
		$this->rest_base = 'cron';
	}

	/**
	 * Регіструє маршрути: список, запуск, скасування
	 */
	public function register_routes(): void {
		// GET  /cron
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}",
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		// POST /cron/{id}/run
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}/(?P<id>\\d+)/run",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_now' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		// POST /cron/{id}/cancel
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}/(?P<id>\\d+)/cancel",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Повертає список запланованих і виконуваних задач лише для активної групи
	 * GET /cron
	 */
	public function list( $req ) {
		$store = \ActionScheduler::store();
		$ids   = $store->query_actions(
			array(
				'group'    => 'sc-autoparser',
				'status'   => array( 'pending', 'in-progress' ),
				'orderby'  => 'scheduled_date',
				'order'    => 'ASC',
				'per_page' => 200,
			)
		);

		$out = array();
		foreach ( $ids as $id ) {
			$action = $store->fetch_action( $id );
			$sched  = $action->get_schedule();
			$date   = $sched && $sched->get_date()
				? $sched->get_date()->format( 'Y-m-d H:i:s' )
				: '';

			$out[] = array(
				'id'        => $id,
				'feed_id'   => $action->get_args()[0] ?? null,
				'status'    => $store->get_status( $id ),
				'scheduled' => $date,
				'attempts'  => $store->get_claim_count( $id ),
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Запускає парсер негайно для конкретного feed
	 * POST /cron/{id}/run
	 */
	public function run_now( WP_REST_Request $req ) {
		try {
			$id     = (int) $req['id'];
			$action = \ActionScheduler::store()->fetch_action( $id );

			if ( ! $action ) {
				return new WP_Error( 'not_found', 'Action not found', array( 'status' => 404 ) );
			}

			$feed_id = $action->get_args()[0] ?? null;
			if ( ! $feed_id ) {
				throw new \RuntimeException( 'Invalid feed ID.' );
			}

			$this->parser->run( $feed_id );

			return rest_ensure_response( array( 'status' => 'triggered' ) );

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'schedule_run_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Скасовує таску: видаляє action з бази
	 * POST /cron/{id}/cancel
	 */
	public function cancel( WP_REST_Request $req ) {
		try {
			$id     = (int) $req['id'];
			$store  = \ActionScheduler::store();
			$action = $store->fetch_action( $id );

			if ( ! $action ) {
				return new WP_Error( 'not_found', 'Action not found', array( 'status' => 404 ) );
			}

			if ( method_exists( $store, 'delete_action' ) ) {
				$store->delete_action( $id );
			} else {
				as_unschedule_all_actions(
					$action->get_hook(),
					$action->get_args(),
					$action->get_group()
				);
			}

			return rest_ensure_response( array( 'status' => 'canceled' ) );

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'schedule_cancel_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}
