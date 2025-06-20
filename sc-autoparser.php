<?php
/**
 * Plugin Name:  SC Autoparser
 * Plugin URI:   https://example.com/plugins/sc-autoparser
 * Description:  Automatic parser → Gemini AI rewrite → Gutenberg autoposting.
 * Version:      0.1.10
 * Author:       Serhii Vasyliev
 * License:      GPL-2.0-or-later
 * Text Domain:  sc-autoparser
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Global plugin constants.
 */
define( 'SC_AUTOPARSER_FILE', __FILE__ );
define( 'SC_AUTOPARSER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SC_AUTOPARSER_URL', plugin_dir_url( __FILE__ ) );
define( 'SC_AUTOPARSER_VERSION', '0.1.10' );

if ( file_exists( __DIR__ . '/src/Helpers/plugin-update.php' ) ) {
	require_once __DIR__ . '/src/Helpers/plugin-update.php';
}

/*
 * Composer autoloader.
 */
require_once SC_AUTOPARSER_DIR . 'vendor/autoload.php';

require_once SC_AUTOPARSER_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';

ScAutoParser\Core\Plugin::instance()->init();
