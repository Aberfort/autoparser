<?php
/**
 * Dependency-Injection / Service Provider
 *
 */

namespace ScAutoParser\Core;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use GuzzleHttp\Client;
use ScAutoParser\Feed\FeedRepository;
use ScAutoParser\Feed\PostMapRepository;
use ScAutoParser\Feed\PostType;
use ScAutoParser\Admin\Controller as AdminController;
use ScAutoParser\Admin\REST\FeedController as FeedRest;
use ScAutoParser\Admin\REST\FeedRunController as FeedRunRest;
use ScAutoParser\Admin\REST\LogController;
use ScAutoParser\Admin\REST\ScheduleController;
use ScAutoParser\Admin\REST\SettingsController;
use ScAutoParser\AI\RewriteService;
use ScAutoParser\AI\PredictionService;
use ScAutoParser\Parser\ParserService;
use ScAutoParser\Publisher\GutenbergPublisher;
use ScAutoParser\Cron\Scheduler;
use ScAutoParser\CLI\RunCommand;
use ScAutoParser\AI\ProviderFactory;

class ServiceProvider implements ServiceProviderInterface {

	public function register( Container $c ): void {

		/* ───────── Logger ───────── */
		$c['logger'] = static function (): Logger {
			return new Logger( WP_CONTENT_DIR . '/uploads/sc-autoparser/logs' );
		};

		$GLOBALS['scap_logger'] = $c['logger'];

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

		$c['feed.post_type'] = static fn() => new PostType();

		$c['feed.rest'] = static function () use ( $c ): FeedRest {
			return new FeedRest(
				$c['feed.repository'],
				$c['cron.scheduler']
			);
		};

		/* ───────── AI Rewrite (provider-aware) ───────── */
		$c['ai.rewrite'] = static function () use ( $c ): RewriteService {
			$settings = get_option( 'scap_settings', [] );
			$default  = $settings['default_ai'] ?? 'gemini';

			$provider = ProviderFactory::make( $default );

			return new RewriteService( $provider );
		};

		/* ---------- Fixtures (RapidAPI) ---------- */
		$c['fixtures'] = static function () use ( $c ): \ScAutoParser\Fixtures\FixturesService {
			$opts = get_option( 'scap_settings', [] );

			return new \ScAutoParser\Fixtures\FixturesService(
				apiKey: $opts['fixtures_api_key'] ?? '',
				http: $c['http'],
				log: $c['logger'],
			);
		};

		/* ───────── AI Prediction (provider-aware) ───────── */
		$c['ai.prediction'] = static function () use ( $c ): PredictionService {
			$settings = get_option( 'scap_settings', [] );
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
		$c['schedule.rest'] = static fn() => new ScheduleController( $c['parser.service'] );

		/* ───────── CLI command ───────── */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command(
				'sc-parser run',
				new RunCommand( $c['parser.service'] )
			);
		}

		/* ───────── Admin pages ───────── */
		$c['admin.controller'] = static fn() => new AdminController( SC_AUTOPARSER_VERSION );

		/* ───────── REST: Logs & Settings ───────── */
		$c['log.rest']      = static fn() => new LogController( WP_CONTENT_DIR . '/uploads/sc-autoparser/logs' );
		$c['settings.rest'] = static fn() => new SettingsController();
	}
}
