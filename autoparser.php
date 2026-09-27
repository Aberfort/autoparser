<?php
/**
 * Plugin Name:  Autoparser
 * Plugin URI:   https://example.com/plugins/autoparser
 * Description:  Automatic parser → Gemini AI rewrite → Gutenberg autoposting.
 * Version:      0.1.10
 * Author:       Serhii Vasyliev
 * License:      GPL-2.0-or-later
 * Text Domain:  autoparser
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Global plugin constants.
 */
define( 'AUTOPARSER_FILE', __FILE__ );
define( 'AUTOPARSER_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUTOPARSER_URL', plugin_dir_url( __FILE__ ) );
define( 'AUTOPARSER_VERSION', '0.1.10' );

if ( file_exists( __DIR__ . '/src/Helpers/plugin-update.php' ) ) {
	require_once __DIR__ . '/src/Helpers/plugin-update.php';
}

/*
 * Composer autoloader.
 */
require_once AUTOPARSER_DIR . 'vendor/autoload.php';

require_once AUTOPARSER_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';

AutoParser\Core\Plugin::instance()->init();
