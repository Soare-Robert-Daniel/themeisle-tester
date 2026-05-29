<?php
/**
 * Plugin Name:     Themeisle Tester
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     themeisle-tester
 * Domain Path:     /languages
 * Version:         0.1.0
 * Requires PHP:    7.4
 *
 * @package         Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TTP_PLUGIN_FILE', __FILE__ );
define( 'TTP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TTP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TTP_VERSION', '0.1.0' );

$ttp_files = array(
	'includes/class-ttp-schema-sanitizer.php',
	'includes/class-ttp-item-registry.php',
	'includes/class-ttp-scenario-store.php',
	'includes/class-ttp-danger-backup-store.php',
	'includes/class-ttp-activity-store.php',
	'includes/class-ttp-hook-applicator.php',
	'includes/interface-ttp-addon.php',
	'includes/class-ttp-addon-loader.php',
	'includes/class-ttp-integration-checks.php',
	'includes/class-ttp-dashboard-actions.php',
	'includes/class-ttp-rest-html.php',
	'includes/class-ttp-rest-controller.php',
	'admin/class-ttp-view-loader.php',
	'admin/class-ttp-dashboard-layout-renderer.php',
	'admin/class-ttp-admin-form-handler.php',
	'admin/class-ttp-admin-assets.php',
	'admin/class-ttp-datastar.php',
	'admin/class-ttp-field-renderer.php',
	'admin/class-ttp-flash-renderer.php',
	'admin/class-ttp-danger-table-renderer.php',
	'admin/class-ttp-popular-plugins-renderer.php',
	'admin/class-ttp-activity-renderer.php',
	'admin/class-ttp-inspect-result-renderer.php',
	'admin/class-ttp-ppom-inspect-renderer.php',
	'admin/class-ttp-ppom-last-target-renderer.php',
	'admin/class-ttp-spc-cached-files-renderer.php',
	'admin/class-ttp-optimole-result-renderer.php',
	'admin/class-ttp-logger-inspect-renderer.php',
	'admin/class-ttp-scenario-summary-renderer.php',
	'admin/class-ttp-admin-notices.php',
	'admin/class-ttp-card-presenter.php',
	'admin/class-ttp-admin-page.php',
	'includes/class-ttp-dashboard-renderer.php',
	'includes/class-ttp-plugin.php',
);

foreach ( $ttp_files as $ttp_file ) {
	require_once TTP_PLUGIN_DIR . $ttp_file;
}

TTP_Addon_Loader::load_addon_files();

add_action( 'plugins_loaded', array( TTP_Plugin::instance(), 'init' ) );
