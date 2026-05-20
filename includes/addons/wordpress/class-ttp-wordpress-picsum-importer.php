<?php
/**
 * Picsum random image importer.
 *
 * Downloads placeholder images from https://picsum.photos and sideloads
 * them into the WordPress media library as attachments. Used by QA to
 * populate Optimole / WooCommerce / PPOM test setups with realistic
 * images without bundling binary fixtures.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the import_random_images utility and runs the per-image step.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_WordPress_Picsum_Importer {

	/**
	 * Maximum images created per run.
	 */
	private const MAX_IMAGES_PER_RUN = 25;

	/**
	 * Default image count when the field is empty or zero.
	 */
	private const DEFAULT_IMAGE_COUNT = 5;

	/**
	 * Smallest accepted side length in pixels.
	 */
	private const MIN_DIMENSION = 16;

	/**
	 * Largest accepted side length in pixels.
	 */
	private const MAX_DIMENSION = 4096;

	/**
	 * Maximum blur intensity accepted by picsum.
	 */
	private const MAX_BLUR = 10;

	/**
	 * Picsum API base URL.
	 */
	private const PICSUM_BASE = 'https://picsum.photos';

	/**
	 * Polite-courtesy delay in microseconds between non-progressive iterations.
	 */
	private const SYNC_DELAY_US = 100000;

	/**
	 * Post meta key marking tester-generated attachments.
	 */
	public const META_GENERATED = '_ttp_generated';

	/**
	 * Post meta key for generation timestamp.
	 */
	public const META_GENERATED_AT = '_ttp_generated_at';

	/**
	 * Schema sanitizer for run payloads.
	 *
	 * @var TTP_Schema_Sanitizer
	 */
	private $schema_sanitizer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->schema_sanitizer = new TTP_Schema_Sanitizer();
	}

	/**
	 * Register the import_random_images utility under the given tab.
	 *
	 * @param TTP_Item_Registry $registry  Item registry.
	 * @param string            $tab_label Category label (tab title).
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry, $tab_label ) {
		$registry->register(
			array(
				'id'                 => 'import_random_images',
				'type'               => 'utility',
				'categories'         => array( $tab_label ),
				'product'            => $tab_label,
				'label'              => __( 'Import random images', 'themeisle-tester' ),
				'description'        => __( 'Downloads placeholder images from picsum.photos into the media library. One image is fetched per request to avoid hammering picsum or the local server.', 'themeisle-tester' ),
				'width'              => 'wide',
				'fields'             => array(
					array(
						'id'      => 'count',
						'type'    => 'integer',
						'label'   => __( 'Number of images (max 25)', 'themeisle-tester' ),
						'default' => self::DEFAULT_IMAGE_COUNT,
					),
					array(
						'id'      => 'width',
						'type'    => 'integer',
						'label'   => __( 'Width (px)', 'themeisle-tester' ),
						'default' => 1200,
					),
					array(
						'id'      => 'height',
						'type'    => 'integer',
						'label'   => __( 'Height (px, 0 = square)', 'themeisle-tester' ),
						'default' => 800,
					),
					array(
						'id'    => 'grayscale',
						'type'  => 'toggle',
						'label' => __( 'Grayscale', 'themeisle-tester' ),
					),
					array(
						'id'      => 'blur',
						'type'    => 'integer',
						'label'   => __( 'Blur (0–10, 0 = off)', 'themeisle-tester' ),
						'default' => 0,
					),
				),
				'is_available'       => array( $this, 'is_available' ),
				'unavailable_reason' => array( $this, 'unavailable_reason' ),
				'run_ui'             => array(
					'transport' => 'progressive',
				),
				'run'                => array( $this, 'run' ),
			)
		);
	}

	/**
	 * Whether the current user can upload files.
	 *
	 * @param array<string,mixed> $item Item definition (unused).
	 * @return bool
	 */
	public function is_available( $item ) {
		unset( $item );
		return current_user_can( 'upload_files' );
	}

	/**
	 * Reason shown when upload_files capability is missing.
	 *
	 * @param array<string,mixed> $item Item definition (unused).
	 * @return string
	 */
	public function unavailable_reason( $item ) {
		unset( $item );
		return __( 'You do not have permission to upload files on this site.', 'themeisle-tester' );
	}

	/**
	 * Run callback: import one image (progressive step) or all at once.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Posted params.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run( $item, $payload ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'ttp_picsum_forbidden', $this->unavailable_reason( $item ) );
		}

		$params = $this->schema_sanitizer->sanitize_params( $item, $payload );

		if ( is_wp_error( $params ) ) {
			return $params;
		}

		$resolved = $this->resolve_params( $params );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$progress_index = $this->progressive_index( $payload );
		$progress_total = $this->progressive_total( $payload );

		if ( $progress_index > 0 && $progress_total > 0 ) {
			return $this->run_progressive_step(
				$resolved,
				$progress_index,
				$progress_total,
				$this->progressive_batch_id( $payload )
			);
		}

		return $this->run_bulk( $resolved );
	}

	/**
	 * Resolve and clamp posted params.
	 *
	 * @param array<string,mixed> $params Sanitized params.
	 * @return array{count:int,width:int,height:int,grayscale:bool,blur:int}|WP_Error
	 */
	private function resolve_params( $params ) {
		$count = isset( $params['count'] ) && is_numeric( $params['count'] ) ? (int) $params['count'] : self::DEFAULT_IMAGE_COUNT;

		if ( $count <= 0 ) {
			$count = self::DEFAULT_IMAGE_COUNT;
		}

		$count = min( $count, self::MAX_IMAGES_PER_RUN );

		$width  = isset( $params['width'] ) && is_numeric( $params['width'] ) ? (int) $params['width'] : 0;
		$height = isset( $params['height'] ) && is_numeric( $params['height'] ) ? (int) $params['height'] : 0;

		if ( $width < self::MIN_DIMENSION || $width > self::MAX_DIMENSION ) {
			return new WP_Error(
				'ttp_picsum_invalid_width',
				sprintf(
					/* translators: 1: min width, 2: max width. */
					__( 'Width must be between %1$d and %2$d pixels.', 'themeisle-tester' ),
					self::MIN_DIMENSION,
					self::MAX_DIMENSION
				)
			);
		}

		if ( $height < 0 || $height > self::MAX_DIMENSION ) {
			return new WP_Error(
				'ttp_picsum_invalid_height',
				sprintf(
					/* translators: 1: max height. */
					__( 'Height must be 0 (square) or between %1$d and %2$d pixels.', 'themeisle-tester' ),
					self::MIN_DIMENSION,
					self::MAX_DIMENSION
				)
			);
		}

		if ( 0 !== $height && $height < self::MIN_DIMENSION ) {
			return new WP_Error(
				'ttp_picsum_invalid_height',
				sprintf(
					/* translators: 1: min height, 2: max height. */
					__( 'Height must be 0 (square) or between %1$d and %2$d pixels.', 'themeisle-tester' ),
					self::MIN_DIMENSION,
					self::MAX_DIMENSION
				)
			);
		}

		$blur = isset( $params['blur'] ) && is_numeric( $params['blur'] ) ? (int) $params['blur'] : 0;
		$blur = max( 0, min( self::MAX_BLUR, $blur ) );

		return array(
			'count'     => $count,
			'width'     => $width,
			'height'    => $height,
			'grayscale' => ! empty( $params['grayscale'] ),
			'blur'      => $blur,
		);
	}

	/**
	 * Import one image and return a progressive-step payload.
	 *
	 * @param array{count:int,width:int,height:int,grayscale:bool,blur:int} $resolved Resolved params.
	 * @param int                                                           $index    Current 1-based step.
	 * @param int                                                           $total    Total steps for the batch.
	 * @param string                                                        $batch_id Existing batch id, or empty.
	 * @return array<string,mixed>|WP_Error
	 */
	private function run_progressive_step( $resolved, $index, $total, $batch_id ) {
		if ( $index > $total ) {
			return new WP_Error( 'ttp_picsum_progress_invalid', __( 'Image index exceeds the requested total.', 'themeisle-tester' ) );
		}

		$batch = '' !== $batch_id ? $batch_id : $this->new_batch_id();
		$url   = $this->build_picsum_url( $resolved, $batch, $index );

		$attach_id = $this->download_and_sideload( $url, $batch );

		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}

		$detail = sprintf(
			/* translators: 1: attachment ID, 2: picsum URL. */
			__( 'Attachment #%1$d — %2$s', 'themeisle-tester' ),
			$attach_id,
			$url
		);

		$percent = (int) round( ( $index / $total ) * 100 );

		return array(
			'message'  => sprintf(
				/* translators: 1: current index, 2: total images. */
				__( 'Imported image %1$d of %2$d.', 'themeisle-tester' ),
				$index,
				$total
			),
			'details'  => array( $detail ),
			'batch'    => $batch,
			'ids'      => array( $attach_id ),
			'progress' => array(
				'current' => $index,
				'total'   => $total,
				'percent' => $percent,
				'done'    => $index >= $total,
			),
		);
	}

	/**
	 * Import all images synchronously (non-progressive fallback).
	 *
	 * @param array{count:int,width:int,height:int,grayscale:bool,blur:int} $resolved Resolved params.
	 * @return array<string,mixed>
	 */
	private function run_bulk( $resolved ) {
		$batch     = $this->new_batch_id();
		$ids       = array();
		$details   = array();
		$succeeded = 0;
		$failed    = 0;

		for ( $index = 1; $index <= $resolved['count']; $index++ ) {
			$url       = $this->build_picsum_url( $resolved, $batch, $index );
			$attach_id = $this->download_and_sideload( $url, $batch );

			if ( is_wp_error( $attach_id ) ) {
				++$failed;
				$details[] = sprintf(
					/* translators: 1: picsum URL, 2: error message. */
					__( '%1$s — failed: %2$s', 'themeisle-tester' ),
					$url,
					$attach_id->get_error_message()
				);
				continue;
			}

			++$succeeded;
			$ids[]     = $attach_id;
			$details[] = sprintf(
				/* translators: 1: attachment ID, 2: picsum URL. */
				__( 'Attachment #%1$d — %2$s', 'themeisle-tester' ),
				$attach_id,
				$url
			);

			if ( $index < $resolved['count'] ) {
				usleep( self::SYNC_DELAY_US );
			}
		}

		return array(
			'message' => sprintf(
				/* translators: 1: succeeded count, 2: failed count. */
				_n(
					'%1$d image imported, %2$d failed.',
					'%1$d images imported, %2$d failed.',
					max( $succeeded, 1 ),
					'themeisle-tester'
				),
				$succeeded,
				$failed
			),
			'details' => $details,
			'batch'   => $batch,
			'ids'     => $ids,
		);
	}

	/**
	 * Build the picsum.photos URL for a given step.
	 *
	 * @param array{count:int,width:int,height:int,grayscale:bool,blur:int} $resolved Resolved params.
	 * @param string                                                        $batch    Batch identifier (used as cache-buster prefix).
	 * @param int                                                           $index    Step index.
	 * @return string
	 */
	private function build_picsum_url( $resolved, $batch, $index ) {
		$path = self::PICSUM_BASE . '/' . $resolved['width'];

		if ( $resolved['height'] > 0 && $resolved['height'] !== $resolved['width'] ) {
			$path .= '/' . $resolved['height'];
		}

		$query = array(
			'random' => $batch . '-' . $index,
		);

		if ( $resolved['grayscale'] ) {
			$query['grayscale'] = '1';
		}

		if ( $resolved['blur'] > 0 ) {
			$query['blur'] = (string) $resolved['blur'];
		}

		return add_query_arg( $query, $path );
	}

	/**
	 * Download a remote image and sideload it into the media library.
	 *
	 * @param string $url   Remote image URL.
	 * @param string $batch Batch identifier (stamped on the attachment).
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function download_and_sideload( $url, $batch ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 30 );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array(
			'name'     => $this->derive_filename( $url, $batch ),
			'tmp_name' => $tmp,
		);

		$attach_id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $attach_id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}

			return $attach_id;
		}

		update_post_meta( $attach_id, self::META_GENERATED, $batch );
		update_post_meta( $attach_id, self::META_GENERATED_AT, (string) time() );

		return (int) $attach_id;
	}

	/**
	 * Derive a deterministic filename for the sideloaded image.
	 *
	 * @param string $url   Source URL.
	 * @param string $batch Batch id.
	 * @return string
	 */
	private function derive_filename( $url, $batch ) {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );

		parse_str( $query, $parsed );

		$random = isset( $parsed['random'] ) && is_string( $parsed['random'] ) ? sanitize_file_name( $parsed['random'] ) : $batch;

		return 'ttp-picsum-' . $random . '.jpg';
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
	 * Progressive run: 1-based step index from the client.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return int
	 */
	private function progressive_index( $payload ) {
		if ( ! isset( $payload['ttp_product_index'] ) || ! is_numeric( $payload['ttp_product_index'] ) ) {
			return 0;
		}

		return max( 0, (int) $payload['ttp_product_index'] );
	}

	/**
	 * Progressive run: total steps in the batch.
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
}
