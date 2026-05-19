<?php
/**
 * Curated popular plugins for one-click install (wordpress.org or ZIP).
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Popular plugin catalog keyed by catalog slug.
 *
 * Each entry:
 * - name (string): Display name.
 * - source (string): 'wordpress.org' | 'zip'.
 * - slug (string): Required when source is wordpress.org (directory slug).
 * - zip_url (string): Required when source is zip (https URL only).
 *
 * @return array<string,array{name:string,source:string,slug?:string,zip_url?:string}>
 */
return array(
	'woocommerce' => array(
		'name'   => 'WooCommerce',
		'source' => 'wordpress.org',
		'slug'   => 'woocommerce',
	),
);
