<?php
/**
 * First-party addon PHP files (require order).
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'includes/addons/sdk/class-ttp-sdk-black-friday.php',
	'includes/addons/sdk/class-ttp-sdk-surveys.php',
	'includes/addons/sdk/class-ttp-sdk-licensing.php',
	'includes/addons/sdk/class-ttp-addon-sdk.php',
	'includes/addons/wordpress/class-ttp-addon-wordpress.php',
);
