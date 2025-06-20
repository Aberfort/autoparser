<?php

namespace ScAutoParser\Cron;

use ScAutoParser\Feed\Feed;
use ScAutoParser\Parser\ParserService;

class Scheduler {

	public function __construct(
		private ParserService $parser
	) {
	}

	public function register_hook(): void {
		add_action(
			'sc_autoparser_run_feed',
			[ $this, 'handle' ],
			10,
			1
		);
	}

	public function schedule_feed( Feed $feed ): void {

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions(
				'sc_autoparser_run_feed',
				[ $feed->id ],
				'sc-autoparser'
			);
		}

		if ( ! $feed->active ) {
			return;
		}

		$timeParts = explode( ':', $feed->post_time );
		$firstRun  = mktime( (int) $timeParts[0], (int) $timeParts[1], 0 );

		if ( $firstRun <= time() ) {
			$firstRun += DAY_IN_SECONDS;
		}

		$interval = DAY_IN_SECONDS;

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action(
				$firstRun,
				$interval,
				'sc_autoparser_run_feed',
				[ $feed->id ],
				'sc-autoparser'
			);
		}
	}

	public function handle( int $feed_id ): void {
		$this->parser->run( $feed_id );
	}
}
