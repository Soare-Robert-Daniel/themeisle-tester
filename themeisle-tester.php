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
	'includes/class-ttp-hook-applicator.php',
	'includes/class-ttp-bundled-items.php',
	'includes/class-ttp-rest-controller.php',
	'admin/class-ttp-admin-notices.php',
	'admin/class-ttp-admin-page.php',
	'includes/class-ttp-plugin.php',
);

foreach ( $ttp_files as $ttp_file ) {
	require_once TTP_PLUGIN_DIR . $ttp_file;
}

TTP_Plugin::instance()->init();
