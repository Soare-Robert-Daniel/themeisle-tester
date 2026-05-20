<?php
/**
 * PHPStan stubs for Optimole plugin APIs used by Themeisle Tester.
 *
 * Optimole ships its own classes in the global namespace; we only stub the
 * surface our addon touches so static analysis stays green when the plugin
 * is not present in CI.
 *
 * @package Themeisle_Tester
 */

/**
 * Minimal Optimole URL replacer stub.
 */
class Optml_Url_Replacer {

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance() {
		return new self();
	}

	/**
	 * Transform a raw image URL into an Optimole CDN URL.
	 *
	 * @param string              $url  Source image URL.
	 * @param array<string,mixed> $args Transformation args (width/height/quality/etc.).
	 * @return string
	 */
	public function build_url( $url, $args = array() ) {
		unset( $args );
		return $url;
	}
}

/**
 * Minimal Optimole settings stub.
 */
class Optml_Settings {

	/**
	 * Read a single setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get( $key ) {
		unset( $key );
		return '';
	}
}
