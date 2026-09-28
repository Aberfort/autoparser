<?php
/**
 * Uninstall script for Autoparser.
 *
 * WordPress calls this file automatically on full plugin removal.
 *
 * @package AutoParser
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Cancel any pending/recurring Action Scheduler actions this plugin owns.
 * Deactivation already does this, but uninstall can run without a prior
 * deactivation step (e.g. `wp plugin delete` on an active plugin), so it
 * must not depend on that. Action Scheduler's own tables are shared with
 * other plugins and are intentionally left untouched.
 */
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'autoparser_run_feed', array(), 'autoparser' );
}

global $wpdb;

/*
 * Drop custom DB tables. Table names are built solely from $wpdb->prefix
 * and a hardcoded suffix (no user input), so $wpdb->prepare() — which
 * quotes values, not identifiers — does not apply here.
 */
$feeds_table = $wpdb->prefix . 'autoparser_feeds';
$map_table   = $wpdb->prefix . 'autoparser_posts_map';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$feeds_table}" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$map_table}" );

/* Remove options */
delete_option( 'autoparser_settings' );
