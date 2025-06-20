<?php

namespace ScAutoParser\Util;

class UrlCanonicalizer
{
    /** Convert any URL to a deterministic canonical string */
    public static function normalize( string $url ): string {

        $parts = wp_parse_url( $url );

        if ( ! $parts || empty( $parts['host'] ) ) {
            return $url; // malformed – return as-is
        }

        // Lower-case scheme + host
        $scheme = strtolower( $parts['scheme'] ?? 'https' );
        $host   = strtolower( $parts['host'] );

        // Remove default ports
        $port = isset( $parts['port'] ) && ! in_array( $parts['port'], [ 80, 443 ], true )
            ? ':' . $parts['port']
            : '';

        // Clean path
        $path = '/' . ltrim( $parts['path'] ?? '/', '/' );

        // Sort query-string alphabetically
        $query = '';
        if ( ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $q );
            ksort( $q );
            $query = '?' . http_build_query( $q, '', '&', PHP_QUERY_RFC3986 );
        }

        return "{$scheme}://{$host}{$port}{$path}{$query}";
    }
}