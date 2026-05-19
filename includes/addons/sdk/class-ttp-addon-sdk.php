<?php
/**
 * Shared Themeisle SDK Testing Items (registration wiring).
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks SDK feature modules to ttp_register_items at fixed priorities.
 */
class TTP_Addon_SDK {

	/**
	 * Register prioritized SDK module callbacks on ttp_register_items.
	 *
	 * Order: Black Friday (10), license (20), logger addon via TTP_Addon_Loader (22), surveys (30), install timestamp (40).
	 *
	 * @return void
	 */
	public static function register_hooks() {
		$licensing = new TTP_SDK_Licensing();

		add_action( 'ttp_register_items', array( new TTP_SDK_Black_Friday(), 'register' ), 10 );
		add_action( 'ttp_register_items', array( $licensing, 'register_license_item' ), 20 );
		add_action( 'ttp_register_items', array( $licensing, 'register_force_license_refresh' ), 21 );
		add_action( 'ttp_register_items', array( new TTP_SDK_Surveys(), 'register' ), 30 );
		add_action( 'ttp_register_items', array( $licensing, 'register_install_item' ), 40 );
	}
}
