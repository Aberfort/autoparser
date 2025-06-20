<?php
/**
 * Uninstall script for SC Autoparser.
 *
 * WordPress calls this file automatically on full plugin removal.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/* Drop custom DB tables */
$feeds_table = $wpdb->prefix . 'scap_feeds';
$map_table   = $wpdb->prefix . 'scap_posts_map';

$wpdb->query( "DROP TABLE IF EXISTS {$feeds_table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$map_table}" );

/* Remove options */
delete_option( 'scap_settings' );
