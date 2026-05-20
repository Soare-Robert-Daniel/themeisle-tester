<?php
/**
 * Loads first-party Themeisle Tester addons.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires internal addons to ttp_register_items.
 */
class TTP_Addon_Loader {

	/**
	 * Addon classes that implement TTP_Addon (hook priority on ttp_register_items).
	 *
	 * @var array<int,array{class-string<TTP_Addon>,int}>
	 */
	private $addons = array(
		array( TTP_Addon_SDK_Logger::class, 22 ),
		array( TTP_Addon_WordPress::class, 50 ),
		array( TTP_Addon_PPOM::class, 60 ),
		array( TTP_Addon_WooCommerce::class, 70 ),
		array( TTP_Addon_Super_Page_Cache::class, 80 ),
		array( TTP_Addon_Optimole::class, 90 ),
	);

	/**
	 * Require addon PHP files from the manifest (single bootstrap entry point).
	 *
	 * @return void
	 */
	public static function load_addon_files() {
		$manifest = TTP_PLUGIN_DIR . 'includes/addons/manifest.php';

		if ( ! is_readable( $manifest ) ) {
			return;
		}

		$files = require $manifest;

		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $relative_path ) {
			if ( ! is_string( $relative_path ) || '' === $relative_path ) {
				continue;
			}

			$absolute = TTP_PLUGIN_DIR . $relative_path;

			if ( is_readable( $absolute ) ) {
				require_once $absolute;
			}
		}
	}

	/**
	 * Register addon hooks.
	 *
	 * @return void
	 */
	public function init() {
		TTP_Addon_SDK::register_hooks();

		foreach ( $this->addons as $addon_entry ) {
			$addon_class = $addon_entry[0];
			$priority    = $addon_entry[1];
			$addon       = new $addon_class();
			add_action( 'ttp_register_items', array( $addon, 'register' ), $priority );
		}
	}
}
