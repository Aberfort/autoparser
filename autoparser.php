<?php
/**
 * Plugin Name:       Autoparser
 * Plugin URI:        https://example.com/plugins/autoparser
 * Description:       Automatic parser → Gemini AI rewrite → Gutenberg autoposting.
 * Version:           0.1.10
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Serhii Vasyliev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       autoparser
 *
 * @package AutoParser
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

/*
 * Schema version for the plugin's custom DB tables — independent of
 * AUTOPARSER_VERSION. Bump this whenever FeedRepository/PostMapRepository's
 * CREATE TABLE SQL changes, so Migrator re-runs dbDelta() on existing
 * installs. See src/Core/Migrator.php.
 */
define( 'AUTOPARSER_DB_VERSION', '1' );

/*
 * Composer autoloader.
 */
require_once AUTOPARSER_DIR . 'vendor/autoload.php';

require_once AUTOPARSER_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';

AutoParser\Core\Plugin::instance()->init();
