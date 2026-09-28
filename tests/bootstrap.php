<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * These tests exercise pure logic only (see tests/Unit/) — no database,
 * no HTTP, no real WordPress install. WordPress functions used by the
 * classes under test are stubbed with Brain\Monkey per-test (see
 * TestCase::setUp()), not loaded from a real WP core.
 */

require_once __DIR__ . '/../vendor/autoload.php';
