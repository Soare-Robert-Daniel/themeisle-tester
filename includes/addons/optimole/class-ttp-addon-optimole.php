<?php
/**
 * Optimole Testing Items.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a Utility that runs a sample URL through Optimole's tag-replacement
 * pipeline and surfaces the parsed CDN URL as a structured breakdown.
 */
class TTP_Addon_Optimole implements TTP_Addon {

	/**
	 * WordPress option key for the last-run transformation snapshot.
	 *
	 * @var string
	 */
	public const OPTION_LAST_RESULT = 'ttp_optimole_last_result';

	/**
	 * Register Optimole Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$registry->register(
			array(
				'id'              => 'optimole_url_transformer',
				'type'            => 'utility',
				'categories'      => array( __( 'Optimole', 'themeisle-tester' ) ),
				'product'         => __( 'Optimole', 'themeisle-tester' ),
				'label'           => __( 'Transform image URL', 'themeisle-tester' ),
				'description'     => __(
					'Send a sample image URL through Optimole\'s URL replacer and inspect the resulting CDN URL — original vs transformed, side-by-side preview, and a parsed breakdown of every path segment Optimole adds.',
					'themeisle-tester'
				),
				'width'           => 'wide',
				'requires'        => TTP_Integration_Checks::require_optimole(),
				'fields'          => array(
					array(
						'id'      => 'image_url',
						'type'    => 'url',
						'label'   => __( 'Image URL', 'themeisle-tester' ),
						'default' => $this->default_sample_url(),
					),
					array(
						'id'      => 'width',
						'type'    => 'integer',
						'label'   => __( 'Width (px)', 'themeisle-tester' ),
						'default' => 800,
					),
					array(
						'id'      => 'height',
						'type'    => 'integer',
						'label'   => __( 'Height (px)', 'themeisle-tester' ),
						'default' => 600,
					),
					array(
						'id'      => 'quality',
						'type'    => 'text',
						'label'   => __( 'Quality', 'themeisle-tester' ),
						'default' => 'auto',
					),
					array(
						'id'      => 'dpr',
						'type'    => 'integer',
						'label'   => __( 'Device pixel ratio', 'themeisle-tester' ),
						'default' => 1,
					),
					array(
						'id'      => 'format',
						'type'    => 'select',
						'label'   => __( 'Format', 'themeisle-tester' ),
						'options' => array( 'best', 'webp', 'avif', 'jpg', 'png' ),
						'default' => 'best',
					),
					array(
						'id'      => 'resize_type',
						'type'    => 'select',
						'label'   => __( 'Resize type', 'themeisle-tester' ),
						'options' => array( 'fit', 'fill', 'smart_crop' ),
						'default' => 'fit',
					),
					array(
						'id'      => 'gravity',
						'type'    => 'select',
						'label'   => __( 'Gravity', 'themeisle-tester' ),
						'options' => array( 'center', 'smart', 'north', 'south', 'west', 'east' ),
						'default' => 'center',
					),
				),
				'inspect'         => array( $this, 'inspect_url_transformer' ),
				'render_inspect'  => array( $this, 'render_inspect_url_transformer' ),
				'run'             => array( $this, 'run_transform_url' ),
				'inspect_on_load' => true,
			)
		);
	}

	/**
	 * Inspect callback — returns the active Optimole settings snapshot plus the
	 * most recent persisted transformation result for this utility.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Sanitized inspect payload (unused).
	 * @return array<string,mixed>|WP_Error
	 */
	public function inspect_url_transformer( $item, $payload ) {
		unset( $payload );

		if ( ! TTP_Integration_Checks::meets_item_requirements( $item ) ) {
			return new WP_Error(
				'ttp_optimole_unavailable',
				TTP_Integration_Checks::unavailable_reason_for_item( $item )
			);
		}

		return array(
			'settings'    => $this->read_settings_snapshot(),
			'last_result' => $this->read_last_result(),
		);
	}

	/**
	 * Delegate rendering of the inspect output to the standalone renderer class.
	 *
	 * @param array<string,mixed> $item           Item definition.
	 * @param mixed               $inspect_result Inspect callback result.
	 * @param TTP_Admin_Page      $page           Admin page.
	 * @return void
	 */
	public function render_inspect_url_transformer( $item, $inspect_result, TTP_Admin_Page $page ) {
		unset( $item );
		$page->render_optimole_result( $inspect_result );
	}

	/**
	 * Run callback — runs the URL through Optimole's URL replacer, persists the
	 * result so the inspect re-render picks it up, and returns a flash payload.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Sanitized run payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_transform_url( $item, $payload ) {
		if ( ! TTP_Integration_Checks::meets_item_requirements( $item ) ) {
			return new WP_Error(
				'ttp_optimole_unavailable',
				TTP_Integration_Checks::unavailable_reason_for_item( $item )
			);
		}

		$url = isset( $payload['image_url'] ) && is_string( $payload['image_url'] )
			? esc_url_raw( $payload['image_url'] )
			: '';

		if ( '' === $url ) {
			return new WP_Error(
				'ttp_optimole_missing_url',
				__( 'Image URL is required.', 'themeisle-tester' )
			);
		}

		$args = $this->build_replacer_args( $payload );

		// Production code path — same call the Optimole output-buffer replacement uses.
		// We invoke build_url() directly rather than apply_filters('optml_content_url', ...)
		// so the result is deterministic and not skewed by third-party filter handlers.
		$transformed = Optml_Url_Replacer::instance()->build_url( $url, $args );

		$breakdown = $this->parse_optimole_url( $url, $transformed );

		$last_result = array(
			'has_result'   => true,
			'original'     => $url,
			'transformed'  => $transformed,
			'args_used'    => $args,
			'breakdown'    => $breakdown,
			'generated_at' => time(),
		);

		update_option( self::OPTION_LAST_RESULT, $last_result, false );

		return array(
			'message' => $breakdown['unchanged']
				? __( 'Optimole returned the URL unchanged — see the panel above for likely reasons.', 'themeisle-tester' )
				: __( 'URL transformed. See the breakdown above.', 'themeisle-tester' ),
		);
	}

	/**
	 * Build the args array passed to Optml_Url_Replacer::build_url().
	 *
	 * @param array<string,mixed> $payload Sanitized run payload.
	 * @return array<string,mixed>
	 */
	private function build_replacer_args( array $payload ) {
		$gravity_map = array(
			'center' => 'ce',
			'smart'  => 'sm',
			'north'  => 'no',
			'south'  => 'so',
			'west'   => 'we',
			'east'   => 'ea',
		);

		$width   = $this->payload_int( $payload, 'width', 800 );
		$height  = $this->payload_int( $payload, 'height', 600 );
		$dpr     = max( 1, $this->payload_int( $payload, 'dpr', 1 ) );
		$quality = $this->payload_string( $payload, 'quality', 'auto' );
		$format  = $this->payload_string( $payload, 'format', 'best' );
		$resize  = $this->payload_string( $payload, 'resize_type', 'fit' );
		$gravity = $this->payload_string( $payload, 'gravity', 'center' );

		return array(
			'width'   => $width,
			'height'  => $height,
			'quality' => is_numeric( $quality ) ? (int) $quality : $quality,
			'dpr'     => $dpr,
			'format'  => $format,
			'resize'  => array(
				'type'    => $resize,
				'gravity' => isset( $gravity_map[ $gravity ] ) ? $gravity_map[ $gravity ] : 'ce',
				'enlarge' => false,
			),
		);
	}

	/**
	 * Read the active Optimole settings snapshot used by the URL replacer.
	 *
	 * @return array<string,mixed>
	 */
	private function read_settings_snapshot() {
		$settings = new Optml_Settings();

		$watermark    = $settings->get( 'watermark' );
		$watermark_on = false;

		if ( is_array( $watermark ) && isset( $watermark['id'] ) && is_numeric( $watermark['id'] ) && (int) $watermark['id'] > 0 ) {
			$watermark_on = true;
		}

		return array(
			'quality'      => $this->scalar( $settings->get( 'quality' ) ),
			'best_format'  => $this->scalar( $settings->get( 'best_format' ) ),
			'retina'       => $this->scalar( $settings->get( 'retina_images' ) ),
			'avif'         => $this->scalar( $settings->get( 'avif' ) ),
			'strip_meta'   => $this->scalar( $settings->get( 'strip_metadata' ) ),
			'watermark_on' => $watermark_on,
			'connected'    => '' !== $this->scalar( $settings->get( 'api_key' ) ),
		);
	}

	/**
	 * Read the persisted last-run transformation snapshot.
	 *
	 * @return array<string,mixed>
	 */
	private function read_last_result() {
		$stored = get_option( self::OPTION_LAST_RESULT, array() );

		if ( ! is_array( $stored ) || empty( $stored['has_result'] ) ) {
			return array( 'has_result' => false );
		}

		$breakdown = isset( $stored['breakdown'] ) && is_array( $stored['breakdown'] )
			? $stored['breakdown']
			: array(
				'host'         => '',
				'segments'     => array(),
				'original_src' => '',
				'unchanged'    => true,
			);

		return array(
			'has_result'   => true,
			'original'     => isset( $stored['original'] ) && is_string( $stored['original'] ) ? $stored['original'] : '',
			'transformed'  => isset( $stored['transformed'] ) && is_string( $stored['transformed'] ) ? $stored['transformed'] : '',
			'args_used'    => isset( $stored['args_used'] ) && is_array( $stored['args_used'] ) ? $stored['args_used'] : array(),
			'breakdown'    => $breakdown,
			'generated_at' => isset( $stored['generated_at'] ) && is_numeric( $stored['generated_at'] ) ? (int) $stored['generated_at'] : 0,
		);
	}

	/**
	 * Parse an Optimole CDN URL into host, path segments, and the original
	 * source URL embedded at the end.
	 *
	 * Optimole URLs look like:
	 *   https://<key>.i.optimole.com/w:800/h:600/q:auto/cb:abc/https://example.com/img.jpg
	 *
	 * @param string $original    Original URL submitted by the user.
	 * @param string $transformed URL returned by Optml_Url_Replacer::build_url().
	 * @return array<string,mixed>
	 */
	private function parse_optimole_url( $original, $transformed ) {
		$empty = array(
			'host'         => '',
			'segments'     => array(),
			'original_src' => '',
			'unchanged'    => true,
		);

		if ( '' === $transformed ) {
			return $empty;
		}

		if ( $transformed === $original ) {
			$host = wp_parse_url( $original, PHP_URL_HOST );

			return array(
				'host'         => is_string( $host ) ? $host : '',
				'segments'     => array(),
				'original_src' => $original,
				'unchanged'    => true,
			);
		}

		$host = wp_parse_url( $transformed, PHP_URL_HOST );
		$path = wp_parse_url( $transformed, PHP_URL_PATH );

		if ( ! is_string( $host ) || ! is_string( $path ) ) {
			return $empty;
		}

		if ( false === strpos( $host, 'optimole.com' ) ) {
			return array(
				'host'         => $host,
				'segments'     => array(),
				'original_src' => $transformed,
				'unchanged'    => true,
			);
		}

		$segments     = array();
		$source_parts = array();
		$hit_source   = false;
		$parts        = explode( '/', ltrim( $path, '/' ) );

		foreach ( $parts as $part ) {
			if ( $hit_source ) {
				$source_parts[] = $part;
				continue;
			}

			if ( 'http:' === $part || 'https:' === $part ) {
				$hit_source     = true;
				$source_parts[] = $part;
				continue;
			}

			$colon = strpos( $part, ':' );

			if ( false === $colon || 0 === $colon ) {
				$hit_source     = true;
				$source_parts[] = $part;
				continue;
			}

			$key   = substr( $part, 0, $colon );
			$value = substr( $part, $colon + 1 );

			$segments[ $key ] = $value;
		}

		$original_src = implode( '/', $source_parts );

		$query = wp_parse_url( $transformed, PHP_URL_QUERY );
		if ( is_string( $query ) && '' !== $query ) {
			$original_src .= '?' . $query;
		}

		return array(
			'host'         => $host,
			'segments'     => $segments,
			'original_src' => $original_src,
			'unchanged'    => false,
		);
	}

	/**
	 * Coerce a payload field to integer with a fallback default.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @param string              $key     Payload key.
	 * @param int                 $fallback Fallback when missing or non-numeric.
	 * @return int
	 */
	private function payload_int( array $payload, $key, $fallback ) {
		return isset( $payload[ $key ] ) && is_numeric( $payload[ $key ] )
			? (int) $payload[ $key ]
			: $fallback;
	}

	/**
	 * Coerce a payload field to a trimmed string with a fallback default.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @param string              $key     Payload key.
	 * @param string              $fallback Fallback when missing.
	 * @return string
	 */
	private function payload_string( array $payload, $key, $fallback ) {
		if ( ! isset( $payload[ $key ] ) ) {
			return $fallback;
		}

		if ( ! is_scalar( $payload[ $key ] ) ) {
			return $fallback;
		}

		$value = trim( (string) $payload[ $key ] );

		return '' === $value ? $fallback : $value;
	}

	/**
	 * A reasonable default image URL to seed the form with so the user can
	 * submit the utility once without typing anything first.
	 *
	 * Prefers the current site's first attachment if one exists (most likely
	 * to land on Optimole's allowed-domain list); falls back to a stable
	 * public Unsplash sample.
	 *
	 * @return string
	 */
	private function default_sample_url() {
		if ( function_exists( 'get_posts' ) ) {
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			if ( ! empty( $attachments ) ) {
				$attachment_id = (int) $attachments[0];
				$url           = wp_get_attachment_url( $attachment_id );

				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}

		return 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=1600';
	}

	/**
	 * Coerce a settings value to a scalar string for display.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string
	 */
	private function scalar( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
