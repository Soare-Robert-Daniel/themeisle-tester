<?php
/**
 * WooCommerce fixture product factory for Themeisle Tester.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates simple and variable WooCommerce products tagged for tester cleanup.
 */
class TTP_WooCommerce_Product_Factory {

	/**
	 * Maximum variations generated per variable product.
	 */
	public const MAX_VARIATIONS_PER_PRODUCT = 50;

	/**
	 * Cached name word catalog.
	 *
	 * @var array{prefixes:array<int,string>,words:array<int,string>}|null
	 */
	private $name_words = null;

	/**
	 * Post meta key marking tester-generated products.
	 */
	public const META_GENERATED = '_ttp_generated';

	/**
	 * Post meta key for generation timestamp.
	 */
	public const META_GENERATED_AT = '_ttp_generated_at';

	/**
	 * Create one simple product.
	 *
	 * @param string $batch      Batch identifier.
	 * @param int    $index      Product index within the batch.
	 * @param string $status     Post status (publish|draft).
	 * @param float  $price_min  Minimum regular price.
	 * @param float  $price_max  Maximum regular price.
	 * @return int|WP_Error Product ID or error.
	 */
	public function create_simple( $batch, $index, $status, $price_min, $price_max ) {
		if ( ! TTP_Integration_Checks::meets_requirements( TTP_Integration_Checks::require_woocommerce_products() ) ) {
			return new WP_Error( 'ttp_wc_unavailable', __( 'WooCommerce product classes are not available.', 'themeisle-tester' ) );
		}

		$product = new WC_Product_Simple();
		$name    = $this->product_name( $batch, $index );

		$product->set_name( $name );
		$product->set_status( $status );
		$product->set_regular_price( $this->random_price( $price_min, $price_max ) );
		$product->set_sku( $this->unique_sku( $name ) );
		$this->stamp_tester_meta( $product, $batch );

		$product_id = $product->save();

		if ( ! $product_id || $product_id <= 0 ) {
			return new WP_Error( 'ttp_wc_save_failed', __( 'Failed to save a simple product.', 'themeisle-tester' ) );
		}

		return $product_id;
	}

	/**
	 * Create one variable product with variations from attribute definitions.
	 *
	 * @param string                                                  $batch      Batch identifier.
	 * @param int                                                     $index      Product index within the batch.
	 * @param string                                                  $status     Post status (publish|draft).
	 * @param float                                                   $price_min  Minimum regular price for variations.
	 * @param float                                                   $price_max  Maximum regular price for variations.
	 * @param array<int,array{name:string,options:array<int,string>}> $attributes Parsed attribute rows.
	 * @return int|WP_Error Product ID or error.
	 */
	public function create_variable( $batch, $index, $status, $price_min, $price_max, $attributes ) {
		if ( ! TTP_Integration_Checks::meets_requirements( TTP_Integration_Checks::require_woocommerce_products() ) ) {
			return new WP_Error(
				'ttp_wc_unavailable',
				TTP_Integration_Checks::unavailable_reason_for_requirements( TTP_Integration_Checks::require_woocommerce_products() )
			);
		}

		$product = new WC_Product_Variable();
		$name    = $this->product_name( $batch, $index );

		$product->set_name( $name );
		$product->set_status( $status );
		$product->set_sku( $this->unique_sku( $name ) );
		$product->set_attributes( $this->build_wc_attributes( $attributes ) );
		$this->stamp_tester_meta( $product, $batch );

		$product_id = $product->save();

		if ( ! $product_id || $product_id <= 0 ) {
			return new WP_Error( 'ttp_wc_save_failed', __( 'Failed to save a variable product.', 'themeisle-tester' ) );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product_Variable ) {
			return new WP_Error( 'ttp_wc_reload_failed', __( 'Failed to reload the variable product after save.', 'themeisle-tester' ) );
		}

		$data_store   = WC_Data_Store::load( 'product' );
		$sample_price = $this->random_price( $price_min, $price_max );

		$created = $data_store->create_all_product_variations(
			$product,
			self::MAX_VARIATIONS_PER_PRODUCT,
			array(
				'regular_price' => $sample_price,
			)
		);

		if ( $created <= 0 ) {
			return new WP_Error( 'ttp_wc_no_variations', __( 'No variations were created for the variable product.', 'themeisle-tester' ) );
		}

		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( ! $variation ) {
				continue;
			}

			$variation->set_regular_price( $this->random_price( $price_min, $price_max ) );
			$variation->save();
		}

		WC_Product_Variable::sync( $product, true );

		return $product_id;
	}

	/**
	 * Count the Cartesian product size for attribute option sets.
	 *
	 * @param array<int,array{name:string,options:array<int,string>}> $attributes Parsed attributes.
	 * @return int
	 */
	public function count_variation_combinations( $attributes ) {
		$total = 1;

		foreach ( $attributes as $attribute ) {
			$option_count = count( $attribute['options'] );

			if ( $option_count <= 0 ) {
				return 0;
			}

			$total *= $option_count;
		}

		return $total;
	}

	/**
	 * Build WC_Product_Attribute objects for a variable parent.
	 *
	 * @param array<int,array{name:string,options:array<int,string>}> $attributes Parsed attributes.
	 * @return array<int,WC_Product_Attribute>
	 */
	private function build_wc_attributes( $attributes ) {
		$wc_attributes = array();
		$position      = 0;

		foreach ( $attributes as $attribute ) {
			if ( '' === $attribute['name'] || empty( $attribute['options'] ) ) {
				continue;
			}

			$attribute_object = new WC_Product_Attribute();
			$attribute_object->set_name( $attribute['name'] );
			$attribute_object->set_options( $attribute['options'] );
			$attribute_object->set_position( (string) $position );
			$attribute_object->set_visible( 1 );
			$attribute_object->set_variation( 1 );
			$wc_attributes[] = $attribute_object;
			++$position;
		}

		return $wc_attributes;
	}

	/**
	 * Stamp tester meta on a product object before save.
	 *
	 * @param WC_Product $product Product instance.
	 * @param string     $batch   Batch identifier.
	 * @return void
	 */
	private function stamp_tester_meta( $product, $batch ) {
		$product->update_meta_data( self::META_GENERATED, $batch );
		$product->update_meta_data( self::META_GENERATED_AT, (string) time() );
	}

	/**
	 * Human-readable product title for fixtures (prefix + random words).
	 *
	 * @param string $batch Batch identifier (unused; kept for call-site stability).
	 * @param int    $index Product index (unused; kept for call-site stability).
	 * @return string
	 */
	private function product_name( $batch, $index ) {
		unset( $batch, $index );

		$catalog = $this->get_name_words();
		$prefix  = $this->random_catalog_word( $catalog['prefixes'] );
		$first   = $this->random_catalog_word( $catalog['words'] );
		$second  = $this->random_catalog_word( $catalog['words'] );

		while ( $second === $first ) {
			$second = $this->random_catalog_word( $catalog['words'] );
		}

		return $prefix . ' ' . $first . ' ' . $second;
	}

	/**
	 * Load prefix and word lists for product naming.
	 *
	 * @return array{prefixes:array<int,string>,words:array<int,string>}
	 */
	private function get_name_words() {
		if ( null !== $this->name_words ) {
			return $this->name_words;
		}

		$path = TTP_PLUGIN_DIR . 'includes/addons/woocommerce/ttp-woocommerce-name-words.php';

		if ( ! is_readable( $path ) ) {
			$this->name_words = array(
				'prefixes' => array( 'TTP', 'QA' ),
				'words'    => array( 'Sample', 'Product' ),
			);

			return $this->name_words;
		}

		$catalog = require $path;

		$prefixes = isset( $catalog['prefixes'] ) && is_array( $catalog['prefixes'] ) ? $catalog['prefixes'] : array();
		$words    = isset( $catalog['words'] ) && is_array( $catalog['words'] ) ? $catalog['words'] : array();

		$this->name_words = array(
			'prefixes' => array_values(
				array_filter(
					$prefixes,
					static function ( $word ) {
						return is_string( $word ) && '' !== trim( $word );
					}
				)
			),
			'words'    => array_values(
				array_filter(
					$words,
					static function ( $word ) {
						return is_string( $word ) && '' !== trim( $word );
					}
				)
			),
		);

		if ( empty( $this->name_words['prefixes'] ) ) {
			$this->name_words['prefixes'] = array( 'TTP' );
		}

		if ( empty( $this->name_words['words'] ) ) {
			$this->name_words['words'] = array( 'Sample', 'Product' );
		}

		return $this->name_words;
	}

	/**
	 * Pick one random entry from a non-empty word list.
	 *
	 * @param array<int,string> $words Word list.
	 * @return string
	 */
	private function random_catalog_word( $words ) {
		$index = wp_rand( 0, count( $words ) - 1 );

		return $words[ $index ];
	}

	/**
	 * Generate a unique SKU derived from the product name.
	 *
	 * @param string $name Product title.
	 * @return string
	 */
	private function unique_sku( $name ) {
		$slug = sanitize_title( $name );
		$base = 'ttp-' . ( '' !== $slug ? $slug : 'product' );

		if ( strlen( $base ) > 28 ) {
			$base = substr( $base, 0, 28 );
		}

		if ( ! function_exists( 'wc_product_has_unique_sku' ) || wc_product_has_unique_sku( 0, $base ) ) {
			return $base;
		}

		return $base . '-' . wp_rand( 100, 999 );
	}

	/**
	 * Random price string within bounds (min <= max).
	 *
	 * @param float $price_min Minimum price.
	 * @param float $price_max Maximum price.
	 * @return string
	 */
	private function random_price( $price_min, $price_max ) {
		$min_cents = (int) round( $price_min * 100 );
		$max_cents = (int) round( $price_max * 100 );

		if ( $max_cents < $min_cents ) {
			$max_cents = $min_cents;
		}

		$cents = wp_rand( $min_cents, $max_cents );

		return wc_format_decimal( $cents / 100, 2 );
	}
}
