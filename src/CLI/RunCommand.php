<?php

namespace ScAutoParser\CLI;

use ScAutoParser\Parser\ParserService;
use WP_CLI;

/**
 * WP-CLI: wp sc-parser run [--feed=<id>]
 */
class RunCommand {

	public function __construct(
		private ParserService $parser
	) {
	}

	/**
	 * Run parser for all feeds or for a single one.
	 *
	 * ## OPTIONS
	 * [--feed=<id>]
	 * : Run only specified feed ID.
	 *
	 * ## EXAMPLES
	 *     wp sc-parser run
	 *     wp sc-parser run --feed=3
	 *
	 * @when after_wp_load
	 */
	public function run( $args, $assoc ) {
		$id = $assoc['feed'] ?? null;
		$this->parser->run( $id ? (int) $id : null );
		WP_CLI::success( 'Parser finished.' );
	}
}
