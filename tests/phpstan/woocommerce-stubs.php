<?php
/**
 * PHPStan stubs for WooCommerce product APIs used by Themeisle Tester.
 *
 * @package Themeisle_Tester
 */

/**
 * Minimal WC_Product stub for static analysis.
 */
class WC_Product {

	/**
	 * @param mixed $key   Meta key.
	 * @param mixed $value Meta value.
	 * @return void
	 */
	public function update_meta_data( $key, $value ) {
	}

	/**
	 * @param string $price Regular price.
	 * @return void
	 */
	public function set_regular_price( $price ) {
	}

	/**
	 * @return int
	 */
	public function save() {
		return 0;
	}

	/**
	 * @param bool $force_delete Force delete flag.
	 * @return void
	 */
	public function delete( $force_delete = false ) {
	}
}

/**
 * Simple product stub.
 */
class WC_Product_Simple extends WC_Product {

	/**
	 * @param string $name Product name.
	 * @return void
	 */
	public function set_name( $name ) {
	}

	/**
	 * @param string $status Post status.
	 * @return void
	 */
	public function set_status( $status ) {
	}

	/**
	 * @param string $price Regular price.
	 * @return void
	 */
	public function set_regular_price( $price ) {
	}

	/**
	 * @param string $sku SKU.
	 * @return void
	 */
	public function set_sku( $sku ) {
	}
}

/**
 * Variable product stub.
 */
class WC_Product_Variable extends WC_Product {

	/**
	 * @param string $name Product name.
	 * @return void
	 */
	public function set_name( $name ) {
	}

	/**
	 * @param string $status Post status.
	 * @return void
	 */
	public function set_status( $status ) {
	}

	/**
	 * @param string $sku SKU.
	 * @return void
	 */
	public function set_sku( $sku ) {
	}

	/**
	 * @param array<int,WC_Product_Attribute> $attributes Attributes.
	 * @return void
	 */
	public function set_attributes( $attributes ) {
	}

	/**
	 * @return array<int,int>
	 */
	public function get_children() {
		return array();
	}

	/**
	 * @param WC_Product_Variable|int $product Product or ID.
	 * @param bool                      $save    Whether to save.
	 * @return void
	 */
	public static function sync( $product, $save = true ) {
	}
}

/**
 * Product attribute stub.
 */
class WC_Product_Attribute {

	/**
	 * @param string $name Attribute name.
	 * @return void
	 */
	public function set_name( $name ) {
	}

	/**
	 * @param array<int,string> $options Option values.
	 * @return void
	 */
	public function set_options( $options ) {
	}

	/**
	 * @param string $position Position.
	 * @return void
	 */
	public function set_position( $position ) {
	}

	/**
	 * @param int $visible Visible flag.
	 * @return void
	 */
	public function set_visible( $visible ) {
	}

	/**
	 * @param int $variation Used for variations flag.
	 * @return void
	 */
	public function set_variation( $variation ) {
	}
}

/**
 * Product data store stub.
 */
class WC_Product_Data_Store_CPT {

	/**
	 * @param WC_Product_Variable $product        Variable product.
	 * @param int                 $limit          Variation limit.
	 * @param array<string,mixed> $default_values Default variation values.
	 * @param array<int,mixed>    $metadata       Variation meta.
	 * @return int
	 */
	public function create_all_product_variations( $product, $limit = -1, $default_values = array(), $metadata = array() ) {
		return 0;
	}
}

/**
 * WooCommerce data store loader stub.
 */
class WC_Data_Store {

	/**
	 * @param string $object_type Object type.
	 * @return WC_Product_Data_Store_CPT
	 */
	public static function load( $object_type ) {
		return new WC_Product_Data_Store_CPT();
	}
}

/**
 * @param int $product_id Product ID.
 * @return WC_Product_Variable|false
 */
function wc_get_product( $product_id = 0 ) {
	return new WC_Product_Variable();
}

/**
 * @param float|int $number   Number.
 * @param int       $decimals Decimal places.
 * @return string
 */
function wc_format_decimal( $number, $decimals = 2 ) {
	return (string) $number;
}

/**
 * @param int    $product_id Product ID.
 * @param string $sku        SKU.
 * @return bool
 */
function wc_product_has_unique_sku( $product_id, $sku ) {
	return true;
}

/**
 * @param string $value Value to clean.
 * @return string
 */
function wc_clean( $value ) {
	return $value;
}
