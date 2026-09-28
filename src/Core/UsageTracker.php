<?php

namespace AutoParser\Core;

/**
 * Lightweight per-provider AI call counters, stored in a single option.
 *
 * Deliberately not a cost calculator: provider pricing changes too often
 * to hardcode reliably, and neither vendor PHP client used here exposes
 * token usage through a stable-enough API to build one safely without a
 * live account to verify against. This tracks call counts instead — a
 * simple, honest "how much is each provider actually being used"
 * indicator an agency can sanity-check across sites.
 */
class UsageTracker {

	private const OPTION   = 'autoparser_usage_stats';
	private const MAX_DAYS = 30;

	public function record( string $provider, string $action ): void {
		$stats = get_option( self::OPTION, array() );
		$day   = gmdate( 'Y-m-d' );

		$stats[ $provider ]['total']                = ( $stats[ $provider ]['total'] ?? 0 ) + 1;
		$stats[ $provider ]['by_day'][ $day ]       = ( $stats[ $provider ]['by_day'][ $day ] ?? 0 ) + 1;
		$stats[ $provider ]['by_action'][ $action ] = ( $stats[ $provider ]['by_action'][ $action ] ?? 0 ) + 1;

		if ( isset( $stats[ $provider ]['by_day'] ) && count( $stats[ $provider ]['by_day'] ) > self::MAX_DAYS ) {
			ksort( $stats[ $provider ]['by_day'] );
			$stats[ $provider ]['by_day'] = array_slice( $stats[ $provider ]['by_day'], -self::MAX_DAYS, null, true );
		}

		// Not needed on every page load — no reason to autoload it.
		update_option( self::OPTION, $stats, false );
	}

	public function get_stats(): array {
		return get_option( self::OPTION, array() );
	}
}
