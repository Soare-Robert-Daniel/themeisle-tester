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
	'includes/addons/sdk/logger/class-ttp-addon-sdk-logger.php',
	'includes/addons/sdk/class-ttp-addon-sdk.php',
	'includes/addons/wordpress/class-ttp-wordpress-picsum-importer.php',
	'includes/addons/wordpress/class-ttp-addon-wordpress.php',
	'includes/addons/woocommerce/ttp-woocommerce-name-words.php',
	'includes/addons/woocommerce/class-ttp-woocommerce-product-factory.php',
	'includes/addons/woocommerce/class-ttp-addon-woocommerce.php',
	'includes/addons/ppom/class-ttp-ppom-free-fields-generator.php',
	'includes/addons/ppom/class-ttp-addon-ppom.php',
	'includes/addons/super-page-cache/class-ttp-addon-super-page-cache.php',
	'includes/addons/optimole/class-ttp-addon-optimole.php',
);
