<?php
/**
 * Plugin Name:       WP Education Map
 * Plugin URI:        https://github.com/WordPress/wordpress.org/issues/584
 * Description:       Displays a world map with city-level markers for WordPress Campus Connect (WPCC), WPCredits, and Student Club activity, with a Dashboard settings screen for adding and managing institutions. Implements https://github.com/WordPress/wordpress.org/issues/584.
 * Version:           1.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-education-map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEIM_VERSION', '1.3.0' );
define( 'WEIM_PLUGIN_FILE', __FILE__ );
define( 'WEIM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEIM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WEIM_DB_VERSION', '1.0' );

require_once WEIM_PLUGIN_DIR . 'includes/class-weim-db.php';
require_once WEIM_PLUGIN_DIR . 'includes/class-weim-programs.php';
require_once WEIM_PLUGIN_DIR . 'includes/class-weim-settings.php';
require_once WEIM_PLUGIN_DIR . 'includes/class-weim-admin.php';
require_once WEIM_PLUGIN_DIR . 'includes/class-weim-rest.php';
require_once WEIM_PLUGIN_DIR . 'includes/class-weim-shortcode.php';

/**
 * Create/upgrade the institutions table on activation.
 */
function weim_activate() {
	WEIM_DB::maybe_upgrade();
}
register_activation_hook( __FILE__, 'weim_activate' );

/**
 * Keep the table schema current if the plugin is updated without deactivation.
 */
add_action( 'plugins_loaded', array( 'WEIM_DB', 'maybe_upgrade' ) );

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'wp-education-map', false, dirname( plugin_basename( WEIM_PLUGIN_FILE ) ) . '/languages' );
	}
);

new WEIM_Admin();
new WEIM_REST();
new WEIM_Shortcode();
