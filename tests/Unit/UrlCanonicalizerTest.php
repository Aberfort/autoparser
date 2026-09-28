<?php

namespace AutoParser\Tests\Unit;

use AutoParser\Util\UrlCanonicalizer;
use Brain\Monkey\Functions;

/**
 * @covers \AutoParser\Util\UrlCanonicalizer
 */
class UrlCanonicalizerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// wp_parse_url() is a WP compat wrapper around parse_url() for the
		// inputs these tests use; aliasing is a faithful stand-in without
		// needing a WordPress install.
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	public function test_lowercases_scheme_and_host(): void {
		$this->assertSame(
			'https://example.com/path',
			UrlCanonicalizer::normalize( 'HTTPS://EXAMPLE.com/path' )
		);
	}

	public function test_defaults_missing_scheme_to_https(): void {
		// wp_parse_url() on a scheme-relative URL leaves 'scheme' unset,
		// same as native parse_url().
		$this->assertSame(
			'https://example.com/',
			UrlCanonicalizer::normalize( '//example.com' )
		);
	}

	public function test_strips_default_ports(): void {
		$this->assertSame(
			'https://example.com/',
			UrlCanonicalizer::normalize( 'https://example.com:443/' )
		);
		$this->assertSame(
			'http://example.com/',
			UrlCanonicalizer::normalize( 'http://example.com:80/' )
		);
	}

	public function test_keeps_non_default_port(): void {
		$this->assertSame(
			'https://example.com:8443/',
			UrlCanonicalizer::normalize( 'https://example.com:8443/' )
		);
	}

	public function test_adds_leading_slash_to_empty_path(): void {
		$this->assertSame(
			'https://example.com/',
			UrlCanonicalizer::normalize( 'https://example.com' )
		);
	}

	/**
	 * This is the exact real-world case the plugin's own ltrim() bug
	 * (fixed elsewhere) was trying and failing to solve — confirming
	 * normalize() doesn't share that bug: a host that starts with letters
	 * also found in "https://" must survive intact.
	 */
	public function test_preserves_hosts_starting_with_scheme_like_letters(): void {
		$this->assertSame(
			'https://ttt.example.com/page',
			UrlCanonicalizer::normalize( 'https://ttt.example.com/page' )
		);
	}

	public function test_sorts_query_params_alphabetically(): void {
		$this->assertSame(
			'https://example.com/?a=1&b=2&c=3',
			UrlCanonicalizer::normalize( 'https://example.com/?c=3&a=1&b=2' )
		);
	}

	public function test_two_urls_differing_only_in_query_order_are_equal(): void {
		$a = UrlCanonicalizer::normalize( 'https://example.com/article?utm_source=x&id=42' );
		$b = UrlCanonicalizer::normalize( 'https://example.com/article?id=42&utm_source=x' );

		$this->assertSame( $a, $b );
	}

	public function test_returns_url_that_fails_to_parse_unchanged(): void {
		// parse_url() itself returns false for this input.
		$this->assertSame(
			'http:///example.com',
			UrlCanonicalizer::normalize( 'http:///example.com' )
		);
	}

	public function test_returns_hostless_input_unchanged(): void {
		// A bare relative path (or arbitrary text) has no 'host' key at
		// all in parse_url()'s result — must pass through untouched
		// rather than being coerced into something host-shaped.
		$this->assertSame(
			'/some/relative/path',
			UrlCanonicalizer::normalize( '/some/relative/path' )
		);
	}

	public function test_normalizes_scheme_host_virtual_urls_too(): void {
		// The plugin's own "match://team1-team2-date" virtual URLs (used
		// as dedup keys for AI-only forecast feeds, which have no real
		// source URL) parse as scheme=match, host=<the rest> — same as
		// any other URL, they get a trailing slash added. This is fine:
		// both the write and the exists()-check paths always run the
		// value through normalize(), so the dedup key stays consistent.
		$this->assertSame(
			'match://team-a-vs-team-b-20260101/',
			UrlCanonicalizer::normalize( 'match://team-a-vs-team-b-20260101' )
		);
	}
}
