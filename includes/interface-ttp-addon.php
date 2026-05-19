<?php
/**
 * Contract for first-party addons that register Testing Items.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for first-party addons that register Testing Items.
 */
interface TTP_Addon {

	/**
	 * Register Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry );
}
