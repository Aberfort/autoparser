<?php

namespace AutoParser\Tests\Unit;

use AutoParser\Parser\ParserService;

/**
 * Tests ParserService::shouldIncludeByRecency() — the dedup/cutoff
 * decision previously duplicated verbatim in discover_from_urlset() and
 * discover_from_rss(), now a single pure static method. No WordPress
 * functions involved, so no Brain\Monkey needed here.
 *
 * @covers \AutoParser\Parser\ParserService::shouldIncludeByRecency
 */
class ParserServiceRecencyTest extends TestCase {

	public function test_previously_deleted_post_is_always_reimported(): void {
		// wasDeleted=true must win regardless of how old/dateless the item is.
		$this->assertTrue( ParserService::shouldIncludeByRecency( true, 0, 9999999999 ) );
		$this->assertTrue( ParserService::shouldIncludeByRecency( true, 100, 200 ) );
	}

	public function test_item_newer_than_cutoff_is_included(): void {
		$this->assertTrue( ParserService::shouldIncludeByRecency( false, 200, 100 ) );
	}

	public function test_item_older_than_or_equal_to_cutoff_is_excluded(): void {
		$this->assertFalse( ParserService::shouldIncludeByRecency( false, 100, 200 ) );
		$this->assertFalse( ParserService::shouldIncludeByRecency( false, 100, 100 ) );
	}

	public function test_dateless_item_is_included_only_on_first_import(): void {
		// ts=0 (no date found) + cutOff=0 (feed never ran before) → include.
		$this->assertTrue( ParserService::shouldIncludeByRecency( false, 0, 0 ) );
	}

	public function test_dateless_item_is_excluded_once_feed_has_a_cutoff(): void {
		// ts=0 but the feed has already run before (cutOff > 0) → too
		// risky to import (could be an old item resurfacing) → exclude.
		$this->assertFalse( ParserService::shouldIncludeByRecency( false, 0, 12345 ) );
	}
}
