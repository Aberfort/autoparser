<?php
/**
 * Dependency-Injection / Service Provider
 */

namespace AutoParser\Core;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use GuzzleHttp\Client;
use AutoParser\Feed\FeedRepository;
use AutoParser\Feed\PostMapRepository;
use AutoParser\Admin\Controller as AdminController;
use AutoParser\Admin\REST\FeedController as FeedRest;
use AutoParser\Admin\REST\FeedRunController as FeedRunRest;
use AutoParser\Admin\REST\LogController;
use AutoParser\Admin\REST\ScheduleController;
use AutoParser\Admin\REST\SettingsController;
use AutoParser\AI\RewriteService;
use AutoParser\AI\PredictionService;
use AutoParser\Parser\ParserService;
use AutoParser\Publisher\GutenbergPublisher;
use AutoParser\Cron\Scheduler;
use AutoParser\CLI\RunCommand;
use AutoParser\CLI\TestSelectorCommand;
use AutoParser\AI\ProviderFactory;

class ServiceProvider implements ServiceProviderInterface {

	public function register( Container $c ): void {

		/* ───────── Logger ───────── */
		$c['logger'] = static function (): Logger {
			return new Logger( WP_CONTENT_DIR . '/uploads/autoparser/logs' );
		};

		$GLOBALS['autoparser_logger'] = $c['logger'];

		/* ───────── HTTP Client ───────── */
		$c['http'] = static fn() => new Client();

		/* ───────── Feed layer ───────── */
		$c['feed.repository'] = static function () use ( $c ): FeedRepository {
			global $wpdb;

			return new FeedRepository( $wpdb, $c['logger'] );
		};

		$c['feed.post_map'] = static function (): PostMapRepository {
			global $wpdb;

			return new PostMapRepository( $wpdb );
		};

		$c['migrator'] = static function () use ( $c ): Migrator {
			return new Migrator( $c['feed.repository'], $c['feed.post_map'] );
		};

		$c['feed.rest'] = static function () use ( $c ): FeedRest {
			return new FeedRest(
				$c['feed.repository'],
				$c['cron.scheduler']
			);
		};

		/* ───────── AI Rewrite (provider-aware) ───────── */
		$c['ai.rewrite'] = static function () use ( $c ): RewriteService {
			$settings = get_option( 'autoparser_settings', array() );
			$default  = $settings['default_ai'] ?? 'gemini';

			$provider = ProviderFactory::make( $default );

			return new RewriteService( $provider );
		};

		/* ---------- Fixtures (RapidAPI) ---------- */
		$c['fixtures'] = static function () use ( $c ): \AutoParser\Modules\Predictions\FixturesService {
			$opts = get_option( 'autoparser_settings', array() );

			return new \AutoParser\Modules\Predictions\FixturesService(
				apiKey: $opts['fixtures_api_key'] ?? '',
				http: $c['http'],
				log: $c['logger'],
			);
		};

		/* ───────── AI Prediction (provider-aware) ───────── */
		$c['ai.prediction'] = static function () use ( $c ): PredictionService {
			$settings = get_option( 'autoparser_settings', array() );
			$default  = $settings['default_ai'] ?? 'gemini';

			$provider = ProviderFactory::make( $default );

			return new PredictionService( $provider );
		};

		/* ───────── Publisher ───────── */
		$c['publisher'] = static fn() => new GutenbergPublisher();

		/* ───────── Parser Service ───────── */
		$c['parser.service'] = static function () use ( $c ): ParserService {
			return new ParserService(
				$c['feed.repository'],
				$c['feed.post_map'],
				$c['ai.rewrite'],
				$c['ai.prediction'],
				$c['fixtures'],
				$c['publisher'],
				$c['http'],
				$c['logger'],
			);
		};

		/* ───────── REST: Manual run ───────── */
		$c['feed.run_rest'] = static fn() => new FeedRunRest(
			$c['parser.service'],
			$c['feed.repository']
		);

		/* ───────── Cron / Scheduler ───────── */
		$c['cron.scheduler'] = static fn() => new Scheduler( $c['parser.service'] );

		/* ───────── REST: Schedule ───────── */
		$c['schedule.rest'] = static fn() => new ScheduleController( $c['parser.service'], $c['feed.repository'] );

		/* ───────── CLI command ───────── */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command(
				'autoparser run',
				new RunCommand( $c['parser.service'] )
			);
			\WP_CLI::add_command(
				'autoparser test-selector',
				new TestSelectorCommand( $c['parser.service'] )
			);
		}

		/* ───────── Admin pages ───────── */
		$c['admin.controller'] = static fn() => new AdminController( AUTOPARSER_VERSION );

		/* ───────── REST: Logs & Settings ───────── */
		$c['log.rest']      = static fn() => new LogController( WP_CONTENT_DIR . '/uploads/autoparser/logs' );
		$c['settings.rest'] = static fn() => new SettingsController();
	}
}
