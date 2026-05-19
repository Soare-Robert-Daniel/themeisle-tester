<?php
/**
 * Runtime availability checks for third-party integrations.
 *
 * Declare external classes, functions, and capabilities on Testing Item schemas
 * via the `requires` key; the registry resolves availability from that list.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects whether third-party APIs referenced by Themeisle Tester are loadable.
 *
 * @phpstan-type IntegrationRequirements array{
 *     classes?: array<string,string>,
 *     functions?: array<string,string>,
 *     capabilities?: array<string,string>
 * }
 */
class TTP_Integration_Checks {

	/**
	 * Whether WooCommerce product APIs used by tester utilities are available.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_product_api_ready() {
		return self::meets_requirements( self::require_woocommerce_products() );
	}

	/**
	 * Whether PPOM field-group APIs used by tester utilities are available.
	 *
	 * @return bool
	 */
	public static function is_ppom_ready() {
		return self::meets_requirements( self::require_ppom() );
	}

	/**
	 * External dependencies for WooCommerce product fixture utilities.
	 *
	 * @phpstan-return IntegrationRequirements
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function require_woocommerce_products() {
		return array(
			'classes'      => array(
				'WooCommerce'          => __( 'WooCommerce is not active on this site.', 'themeisle-tester' ),
				'WC_Product'           => __( 'WooCommerce product APIs (WC_Product) are not available on this site.', 'themeisle-tester' ),
				'WC_Product_Simple'    => __( 'WooCommerce simple product APIs are not available on this site.', 'themeisle-tester' ),
				'WC_Product_Variable'  => __( 'WooCommerce variable product APIs are not available on this site.', 'themeisle-tester' ),
				'WC_Product_Attribute' => __( 'WooCommerce product attribute APIs are not available on this site.', 'themeisle-tester' ),
			),
			'capabilities' => array(
				// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce edit_products capability.
				'edit_products' => __( 'You do not have permission to edit products on this site.', 'themeisle-tester' ),
			),
		);
	}

	/**
	 * External dependencies for PPOM inspect utilities.
	 *
	 * @phpstan-return IntegrationRequirements
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function require_ppom() {
		$requirements = self::require_woocommerce_products();

		$requirements['functions'] = array(
			'ppom_meta_repository' => __( 'PPOM for WooCommerce (woocommerce-product-addon) is not active on this site.', 'themeisle-tester' ),
		);

		return $requirements;
	}

	/**
	 * Whether a normalized item's declared external dependencies are satisfied.
	 *
	 * @param array<string,mixed> $item Normalized Testing Item.
	 * @return bool
	 */
	public static function meets_item_requirements( $item ) {
		$requires = self::normalize_requires( $item['requires'] ?? array() );

		if ( empty( $requires ) ) {
			return true;
		}

		return self::meets_requirements( $requires );
	}

	/**
	 * First unavailable reason for a normalized item's `requires` list.
	 *
	 * @param array<string,mixed> $item Normalized Testing Item.
	 * @return string
	 */
	public static function unavailable_reason_for_item( $item ) {
		$requires = self::normalize_requires( $item['requires'] ?? array() );

		if ( empty( $requires ) ) {
			return __( 'Testing Item is unavailable.', 'themeisle-tester' );
		}

		return self::unavailable_reason_for_requirements( $requires );
	}

	/**
	 * Whether all entries in a requirements map are satisfied.
	 *
	 * @param array<string,mixed> $requirements Requirements map.
	 * @return bool
	 */
	public static function meets_requirements( $requirements ) {
		return null === self::first_unmet_requirement( $requirements );
	}

	/**
	 * Human-readable reason for the first unmet requirement.
	 *
	 * @param array<string,mixed> $requirements Requirements map.
	 * @return string
	 */
	public static function unavailable_reason_for_requirements( $requirements ) {
		$unmet = self::first_unmet_requirement( $requirements );

		if ( null === $unmet ) {
			return __( 'Testing Item is unavailable.', 'themeisle-tester' );
		}

		return $unmet;
	}

	/**
	 * Sanitize a raw `requires` schema from item registration.
	 *
	 * @param mixed $requires Raw requires value.
	 * @phpstan-return IntegrationRequirements
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function normalize_requires( $requires ) {
		if ( ! is_array( $requires ) ) {
			return array();
		}

		$normalized = array();
		$kinds      = array( 'classes', 'functions', 'capabilities' );

		foreach ( $kinds as $kind ) {
			if ( ! isset( $requires[ $kind ] ) || ! is_array( $requires[ $kind ] ) ) {
				continue;
			}

			foreach ( $requires[ $kind ] as $name => $message ) {
				if ( ! is_string( $name ) || '' === $name ) {
					continue;
				}

				$normalized[ $kind ][ $name ] = is_string( $message ) && '' !== $message
					? $message
					: __( 'Required dependency is not available.', 'themeisle-tester' );
			}
		}

		return $normalized;
	}

	/**
	 * Return the message for the first unmet requirement, or null when all pass.
	 *
	 * @param array<string,mixed> $requirements Requirements map.
	 * @return string|null
	 */
	private static function first_unmet_requirement( $requirements ) {
		$fallback = __( 'Required dependency is not available.', 'themeisle-tester' );

		foreach ( array( 'classes' => 'class_exists', 'functions' => 'function_exists' ) as $kind => $checker ) {
			if ( ! isset( $requirements[ $kind ] ) || ! is_array( $requirements[ $kind ] ) ) {
				continue;
			}

			foreach ( $requirements[ $kind ] as $symbol => $message ) {
				if ( ! is_string( $symbol ) || '' === $symbol ) {
					continue;
				}

				$is_ready = 'class_exists' === $checker ? class_exists( $symbol ) : function_exists( $symbol );

				if ( ! $is_ready ) {
					return is_string( $message ) && '' !== $message ? $message : $fallback;
				}
			}
		}

		if ( isset( $requirements['capabilities'] ) && is_array( $requirements['capabilities'] ) ) {
			foreach ( $requirements['capabilities'] as $capability => $message ) {
				if ( ! is_string( $capability ) || '' === $capability ) {
					continue;
				}

				if ( ! current_user_can( $capability ) ) {
					return is_string( $message ) && '' !== $message ? $message : $fallback;
				}
			}
		}

		return null;
	}
}
