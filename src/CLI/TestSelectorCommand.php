<?php

namespace AutoParser\CLI;

use AutoParser\Parser\ParserService;
use WP_CLI;

/**
 * WP-CLI: wp autoparser test-selector --url=<url> [--selector=<css>] [--selector-end=<css>]
 */
class TestSelectorCommand {

	public function __construct(
		private ParserService $parser
	) {
	}

	/**
	 * Fetch a URL and show what a CSS selector would extract from it —
	 * without saving a feed, publishing anything, or calling the AI
	 * provider. Useful for checking a selector before creating or
	 * editing a feed.
	 *
	 * ## OPTIONS
	 * --url=<url>
	 * : The page to fetch.
	 *
	 * [--selector=<css>]
	 * : Content start selector. If omitted or not found on the page,
	 * common fallbacks (article, main, .entry-content, .post-content,
	 * .content, #content, body) are tried in order.
	 *
	 * [--selector-end=<css>]
	 * : Optional content end selector.
	 *
	 * ## EXAMPLES
	 *     wp autoparser test-selector --url=https://example.com/news/123
	 *     wp autoparser test-selector --url=https://example.com/news/123 --selector=.article-body
	 *
	 * @when after_wp_load
	 */
	public function run( $args, $assoc ) {
		$url          = $assoc['url'] ?? '';
		$selector     = $assoc['selector'] ?? '';
		$selector_end = $assoc['selector-end'] ?? null;

		if ( '' === $url ) {
			WP_CLI::error( 'Missing --url.' );

			return;
		}

		try {
			$result = $this->parser->preview_extract( $url, $selector, $selector_end );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );

			return;
		}

		WP_CLI::log( 'Title: ' . $result['title'] );
		WP_CLI::log( 'Content length: ' . $result['content_length'] . ' chars' );
		WP_CLI::log( '' );
		WP_CLI::log( wp_trim_words( wp_strip_all_tags( $result['content'] ), 60, '…' ) );
		WP_CLI::success( 'Selector resolved successfully.' );
	}
}
