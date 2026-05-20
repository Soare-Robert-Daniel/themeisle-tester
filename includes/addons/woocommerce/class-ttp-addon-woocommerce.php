<?php
/**
 * WooCommerce Testing Items (fixture product utilities).
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WooCommerce product fixture utilities for QA workflows.
 */
class TTP_Addon_WooCommerce implements TTP_Addon {

	/**
	 * Maximum products created per run.
	 */
	private const MAX_PRODUCTS_PER_RUN = 25;

	/**
	 * Default product count when the field is empty or zero.
	 */
	private const DEFAULT_PRODUCT_COUNT = 5;

	/**
	 * Default minimum price.
	 */
	private const DEFAULT_PRICE_MIN = 9.99;

	/**
	 * Default maximum price.
	 */
	private const DEFAULT_PRICE_MAX = 99.99;

	/**
	 * Default variation attribute DSL (pipe- or newline-separated attributes).
	 */
	private const DEFAULT_ATTRIBUTES_DSL = 'Color: Red, Blue | Size: S, M, L';

	/**
	 * Schema sanitizer for run payloads.
	 *
	 * @var TTP_Schema_Sanitizer
	 */
	private $schema_sanitizer;

	/**
	 * Product factory.
	 *
	 * @var TTP_WooCommerce_Product_Factory
	 */
	private $product_factory;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->schema_sanitizer = new TTP_Schema_Sanitizer();
		$this->product_factory  = new TTP_WooCommerce_Product_Factory();
	}

	/**
	 * Register WooCommerce Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$wc_tab           = __( 'WooCommerce', 'themeisle-tester' );
		$wc_product       = __( 'WooCommerce', 'themeisle-tester' );
		$product_fixtures = __( 'Product fixtures', 'themeisle-tester' );

		$registry->register(
			array(
				'id'          => 'woocommerce_generate_random_products',
				'type'        => 'utility',
				'categories'  => array( $wc_tab ),
				'group'       => $product_fixtures,
				'product'     => $wc_product,
				'label'       => __( 'Generate random products', 'themeisle-tester' ),
				'description' => __( 'Creates simple or variable WooCommerce products with randomized names, SKUs, and prices. Generated products are tagged for bulk deletion.', 'themeisle-tester' ),
				'width'       => 'wide',
				'fields'      => array(
					array(
						'id'      => 'count',
						'type'    => 'integer',
						'label'   => __( 'Number of products', 'themeisle-tester' ),
						'default' => 5,
					),
					array(
						'id'      => 'product_type',
						'type'    => 'select',
						'label'   => __( 'Product type', 'themeisle-tester' ),
						'options' => array( 'simple', 'variable' ),
						'default' => 'simple',
					),
					array(
						'id'      => 'attributes',
						'type'    => 'text',
						'label'   => __( 'Variation attributes (variable only)', 'themeisle-tester' ),
						'default' => self::DEFAULT_ATTRIBUTES_DSL,
					),
					array(
						'id'      => 'status',
						'type'    => 'select',
						'label'   => __( 'Status', 'themeisle-tester' ),
						'options' => array( 'publish', 'draft' ),
						'default' => 'publish',
					),
					array(
						'id'      => 'price_min',
						'type'    => 'number',
						'label'   => __( 'Minimum price', 'themeisle-tester' ),
						'default' => 9.99,
					),
					array(
						'id'      => 'price_max',
						'type'    => 'number',
						'label'   => __( 'Maximum price', 'themeisle-tester' ),
						'default' => 99.99,
					),
				),
				'requires'    => TTP_Integration_Checks::require_woocommerce_products(),
				'run_ui'      => array(
					'transport' => 'progressive',
				),
				'run'         => array( $this, 'run_generate_random_products' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'woocommerce_delete_generated_products',
				'type'        => 'utility',
				'categories'  => array( $wc_tab ),
				'group'       => $product_fixtures,
				'product'     => $wc_product,
				'label'       => __( 'Delete generated products', 'themeisle-tester' ),
				'description' => __( 'Permanently deletes all WooCommerce products previously created by Themeisle Tester (tagged with _ttp_generated meta).', 'themeisle-tester' ),
				'width'       => 'normal',
				'requires'    => TTP_Integration_Checks::require_woocommerce_products(),
				'run'         => array( $this, 'run_delete_generated_products' ),
			)
		);
	}

	/**
	 * Generate random WooCommerce products.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Posted params.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_generate_random_products( $item, $payload ) {
		if ( ! TTP_Integration_Checks::meets_item_requirements( $item ) ) {
			return new WP_Error( 'ttp_wc_forbidden', TTP_Integration_Checks::unavailable_reason_for_item( $item ) );
		}

		$params = $this->schema_sanitizer->sanitize_params( $item, $payload );

		if ( is_wp_error( $params ) ) {
			return $params;
		}

		$generation = $this->resolve_generation_params( $params );

		if ( is_wp_error( $generation ) ) {
			return $generation;
		}

		$progress_index = $this->progressive_product_index( $payload );
		$progress_total = $this->progressive_total( $payload );

		if ( $progress_index > 0 && $progress_total > 0 ) {
			return $this->run_generate_progressive_step(
				$generation,
				$progress_index,
				$progress_total,
				$this->progressive_batch_id( $payload )
			);
		}

		return $this->run_generate_bulk( $generation );
	}

	/**
	 * Create products one request at a time for Dashboard progress UI.
	 *
	 * @param array{count:int,product_type:string,status:string,price_min:float,price_max:float,attributes:array<int,array{name:string,options:array<int,string>}>} $generation Resolved generation params.
	 * @param int                                                                                                                                                   $index      Current product index (1-based).
	 * @param int                                                                                                                                                   $total      Total products in the run.
	 * @param string                                                                                                                                                $batch_id   Existing batch id, or empty to start a new batch.
	 * @return array<string,mixed>|WP_Error
	 */
	private function run_generate_progressive_step( $generation, $index, $total, $batch_id ) {
		if ( $index > $total ) {
			return new WP_Error( 'ttp_wc_progress_invalid', __( 'Product index exceeds the requested total.', 'themeisle-tester' ) );
		}

		$batch = '' !== $batch_id ? $batch_id : $this->new_batch_id();

		$created = $this->create_product_at_index(
			$generation['product_type'],
			$batch,
			$index,
			$generation['status'],
			$generation['price_min'],
			$generation['price_max'],
			$generation['attributes']
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$detail = sprintf(
			/* translators: 1: product ID, 2: product title. */
			__( 'Product #%1$d — %2$s', 'themeisle-tester' ),
			$created,
			get_the_title( $created )
		);

		$percent = (int) round( ( $index / $total ) * 100 );

		return array(
			'message'  => sprintf(
				/* translators: 1: current index, 2: total products. */
				__( 'Created product %1$d of %2$d.', 'themeisle-tester' ),
				$index,
				$total
			),
			'details'  => array( $detail ),
			'batch'    => $batch,
			'ids'      => array( $created ),
			'progress' => array(
				'current' => $index,
				'total'   => $total,
				'percent' => $percent,
				'done'    => $index >= $total,
			),
		);
	}

	/**
	 * Create all products in one request (non-progressive fallback).
	 *
	 * @param array{count:int,product_type:string,status:string,price_min:float,price_max:float,attributes:array<int,array{name:string,options:array<int,string>}>} $generation Resolved generation params.
	 * @return array<string,mixed>|WP_Error
	 */
	private function run_generate_bulk( $generation ) {
		$batch   = $this->new_batch_id();
		$ids     = array();
		$details = array();

		for ( $index = 1; $index <= $generation['count']; $index++ ) {
			$created = $this->create_product_at_index(
				$generation['product_type'],
				$batch,
				$index,
				$generation['status'],
				$generation['price_min'],
				$generation['price_max'],
				$generation['attributes']
			);

			if ( is_wp_error( $created ) ) {
				return $created;
			}

			$ids[]     = $created;
			$details[] = sprintf(
				/* translators: 1: product ID, 2: product title. */
				__( 'Product #%1$d — %2$s', 'themeisle-tester' ),
				$created,
				get_the_title( $created )
			);
		}

		return array(
			'message' => sprintf(
				/* translators: 1: number created, 2: batch id. */
				__( 'Created %1$d products (batch %2$s).', 'themeisle-tester' ),
				count( $ids ),
				$batch
			),
			'details' => $details,
			'batch'   => $batch,
			'ids'     => $ids,
		);
	}

	/**
	 * Resolve and validate generation parameters from sanitized form fields.
	 *
	 * @param array<string,mixed> $params Sanitized params.
	 * @return array{count:int,product_type:string,status:string,price_min:float,price_max:float,attributes:array<int,array{name:string,options:array<int,string>}>}|WP_Error
	 */
	private function resolve_generation_params( $params ) {
		$count = isset( $params['count'] ) && is_int( $params['count'] ) ? $params['count'] : 0;

		if ( $count <= 0 ) {
			$count = self::DEFAULT_PRODUCT_COUNT;
		}

		if ( $count > self::MAX_PRODUCTS_PER_RUN ) {
			return new WP_Error(
				'ttp_wc_count_cap',
				sprintf(
					/* translators: %d: maximum products per run. */
					__( 'You can create at most %d products per run.', 'themeisle-tester' ),
					self::MAX_PRODUCTS_PER_RUN
				)
			);
		}

		$product_type = isset( $params['product_type'] ) && is_string( $params['product_type'] ) ? $params['product_type'] : 'simple';

		if ( ! in_array( $product_type, array( 'simple', 'variable' ), true ) ) {
			$product_type = 'simple';
		}

		$status = isset( $params['status'] ) && is_string( $params['status'] ) ? $params['status'] : 'publish';

		if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$status = 'publish';
		}

		$price_min = $this->normalize_price( $params['price_min'] ?? self::DEFAULT_PRICE_MIN, self::DEFAULT_PRICE_MIN );
		$price_max = $this->normalize_price( $params['price_max'] ?? self::DEFAULT_PRICE_MAX, self::DEFAULT_PRICE_MAX );

		if ( $price_max < $price_min ) {
			$price_max = $price_min;
		}

		$attributes = array();

		if ( 'variable' === $product_type ) {
			$raw_attributes = isset( $params['attributes'] ) && is_string( $params['attributes'] ) ? $params['attributes'] : '';
			$attributes     = $this->parse_attributes_dsl( $raw_attributes );

			if ( is_wp_error( $attributes ) ) {
				return $attributes;
			}

			$combination_count = $this->product_factory->count_variation_combinations( $attributes );

			if ( $combination_count <= 0 ) {
				return new WP_Error( 'ttp_wc_invalid_attributes', __( 'Each attribute must have at least one option.', 'themeisle-tester' ) );
			}

			if ( $combination_count > TTP_WooCommerce_Product_Factory::MAX_VARIATIONS_PER_PRODUCT ) {
				return new WP_Error(
					'ttp_wc_variation_cap',
					sprintf(
						/* translators: 1: combination count, 2: maximum allowed. */
						__( 'Attribute combinations produce %1$d variations; the maximum is %2$d per product.', 'themeisle-tester' ),
						$combination_count,
						TTP_WooCommerce_Product_Factory::MAX_VARIATIONS_PER_PRODUCT
					)
				);
			}
		}

		return array(
			'count'        => $count,
			'product_type' => $product_type,
			'status'       => $status,
			'price_min'    => $price_min,
			'price_max'    => $price_max,
			'attributes'   => $attributes,
		);
	}

	/**
	 * Create one product in a batch.
	 *
	 * @param string                                                  $product_type Product type.
	 * @param string                                                  $batch        Batch id.
	 * @param int                                                     $index        Product index in batch.
	 * @param string                                                  $status       Post status.
	 * @param float                                                   $price_min    Minimum price.
	 * @param float                                                   $price_max    Maximum price.
	 * @param array<int,array{name:string,options:array<int,string>}> $attributes Parsed attributes for variable products.
	 * @return int|WP_Error
	 */
	private function create_product_at_index( $product_type, $batch, $index, $status, $price_min, $price_max, $attributes ) {
		if ( 'variable' === $product_type ) {
			return $this->product_factory->create_variable( $batch, $index, $status, $price_min, $price_max, $attributes );
		}

		return $this->product_factory->create_simple( $batch, $index, $status, $price_min, $price_max );
	}

	/**
	 * Generate a new batch identifier.
	 *
	 * @return string
	 */
	private function new_batch_id() {
		return gmdate( 'YmdHis' ) . '-' . strtolower( wp_generate_password( 4, false, false ) );
	}

	/**
	 * Progressive run: 1-based product index from the client.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return int
	 */
	private function progressive_product_index( $payload ) {
		if ( ! isset( $payload['ttp_product_index'] ) || ! is_numeric( $payload['ttp_product_index'] ) ) {
			return 0;
		}

		return max( 0, (int) $payload['ttp_product_index'] );
	}

	/**
	 * Progressive run: total products in the batch.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return int
	 */
	private function progressive_total( $payload ) {
		if ( ! isset( $payload['ttp_total'] ) || ! is_numeric( $payload['ttp_total'] ) ) {
			return 0;
		}

		return max( 0, (int) $payload['ttp_total'] );
	}

	/**
	 * Progressive run: existing batch id from the client.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return string
	 */
	private function progressive_batch_id( $payload ) {
		if ( ! isset( $payload['ttp_batch'] ) || ! is_scalar( $payload['ttp_batch'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $payload['ttp_batch'] );
	}

	/**
	 * Delete all tester-generated WooCommerce products.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Posted params.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_delete_generated_products( $item, $payload ) {
		unset( $payload );

		if ( ! TTP_Integration_Checks::meets_item_requirements( $item ) ) {
			return new WP_Error( 'ttp_wc_forbidden', TTP_Integration_Checks::unavailable_reason_for_item( $item ) );
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => TTP_WooCommerce_Product_Factory::META_GENERATED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- QA utility; bounded to tagged fixtures.
				'no_found_rows'  => true,
			)
		);

		$deleted = 0;
		$details = array();

		foreach ( $query->posts as $post_id ) {
			if ( ! is_numeric( $post_id ) ) {
				continue;
			}

			$product_id = absint( $post_id );
			$title      = get_the_title( $product_id );
			$product    = wc_get_product( $product_id );

			if ( $product ) {
				$product->delete( true );
			} else {
				wp_delete_post( $product_id, true );
			}

			++$deleted;
			$details[] = sprintf(
				/* translators: 1: product ID, 2: product title. */
				__( 'Deleted product #%1$d — %2$s', 'themeisle-tester' ),
				$product_id,
				'' !== $title ? $title : __( '(no title)', 'themeisle-tester' )
			);
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of deleted products. */
				_n(
					'Deleted %d tester-generated product.',
					'Deleted %d tester-generated products.',
					$deleted,
					'themeisle-tester'
				),
				$deleted
			),
			'details' => $details,
		);
	}

	/**
	 * Parse the attribute DSL into structured rows.
	 *
	 * Format: one attribute per line, or pipe-separated on one line:
	 * Color: Red, Blue
	 * Size: S, M, L
	 *
	 * @param string $raw Raw DSL from the form.
	 * @return array<int,array{name:string,options:array<int,string>}>|WP_Error
	 */
	private function parse_attributes_dsl( $raw ) {
		$raw = trim( $raw );

		if ( '' === $raw ) {
			$raw = self::DEFAULT_ATTRIBUTES_DSL;
		}

		$segments = preg_split( '/\r\n|\r|\n/', $raw );
		$lines    = array();

		if ( is_array( $segments ) ) {
			foreach ( $segments as $segment ) {
				$segment = trim( $segment );

				if ( '' === $segment ) {
					continue;
				}

				if ( false !== strpos( $segment, '|' ) ) {
					$pipe_parts = explode( '|', $segment );

					foreach ( $pipe_parts as $pipe_part ) {
						$pipe_part = trim( $pipe_part );

						if ( '' !== $pipe_part ) {
							$lines[] = $pipe_part;
						}
					}
				} else {
					$lines[] = $segment;
				}
			}
		}

		if ( empty( $lines ) ) {
			$raw   = self::DEFAULT_ATTRIBUTES_DSL;
			$lines = preg_split( '/\r\n|\r|\n/', $raw );
			$lines = is_array( $lines ) ? array_filter( array_map( 'trim', $lines ) ) : array();
		}

		if ( empty( $lines ) ) {
			return new WP_Error( 'ttp_wc_attribute_format', __( 'Each attribute must include a name and at least one option.', 'themeisle-tester' ) );
		}

		$attributes = array();

		foreach ( $lines as $line ) {
			$parsed = $this->parse_attribute_line( $line );

			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}

			$attributes[] = $parsed;
		}

		return $attributes;
	}

	/**
	 * Parse one attribute line (Name: opt1, opt2).
	 *
	 * @param string $line Single DSL line.
	 * @return array{name:string,options:array<int,string>}|WP_Error
	 */
	private function parse_attribute_line( $line ) {
		$colon_pos = strpos( $line, ':' );

		if ( false === $colon_pos ) {
			return new WP_Error(
				'ttp_wc_attribute_format',
				__( 'Each attribute line must use the format Name: option1, option2.', 'themeisle-tester' )
			);
		}

		$name    = trim( substr( $line, 0, $colon_pos ) );
		$options = trim( substr( $line, $colon_pos + 1 ) );

		if ( '' === $name || '' === $options ) {
			return new WP_Error(
				'ttp_wc_attribute_format',
				__( 'Each attribute must include a name and at least one option.', 'themeisle-tester' )
			);
		}

		$name = function_exists( 'wc_clean' ) ? wc_clean( $name ) : sanitize_text_field( $name );

		$raw_options = array_map( 'trim', explode( ',', $options ) );
		$cleaned     = array();

		foreach ( $raw_options as $option ) {
			if ( '' === $option ) {
				continue;
			}

			$cleaned[] = function_exists( 'wc_clean' ) ? wc_clean( $option ) : sanitize_text_field( $option );
		}

		if ( empty( $cleaned ) ) {
			return new WP_Error(
				'ttp_wc_attribute_format',
				__( 'Each attribute must include at least one option.', 'themeisle-tester' )
			);
		}

		return array(
			'name'    => $name,
			'options' => $cleaned,
		);
	}

	/**
	 * Normalize a price field to a non-negative float.
	 *
	 * @param mixed $value        Raw value.
	 * @param float $fallback     Fallback when value is zero or invalid.
	 * @return float
	 */
	private function normalize_price( $value, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		$price = (float) $value;

		if ( $price <= 0 ) {
			return $fallback;
		}

		return $price;
	}
}
