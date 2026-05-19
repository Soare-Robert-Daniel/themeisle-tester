<?php
/**
 * Admin view partial loader.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads admin/views/{name}.php partials with an isolated variable scope.
 */
class TTP_View_Loader {

	/**
	 * Render a view partial from admin/views/{name}.php.
	 *
	 * Partials run in their own function scope (no leakage into the caller).
	 * They receive `$page` (the Dashboard context) plus every key in `$vars`
	 * extracted as a local variable.
	 *
	 * @param TTP_Admin_Page      $page Dashboard context passed to the partial.
	 * @param string              $name View slug (no `.php`, no path separators).
	 * @param array<string,mixed> $vars Locals passed to the partial.
	 * @return void
	 */
	public function render( TTP_Admin_Page $page, $name, array $vars = array() ) {
		if ( ! preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
			return;
		}

		$path = __DIR__ . '/views/' . $name . '.php';

		if ( ! is_file( $path ) ) {
			return;
		}

		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require $path;
	}
}
