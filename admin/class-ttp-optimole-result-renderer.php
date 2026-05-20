<?php
/**
 * Renderer for the Optimole "Transform image URL" inspect output.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the active Optimole settings strip plus the most recent transformation
 * breakdown (parsed CDN URL segments + side-by-side preview).
 *
 * @phpstan-type OptmlSettings array{
 *     quality: string,
 *     best_format: string,
 *     retina: string,
 *     avif: string,
 *     strip_meta: string,
 *     watermark_on: bool,
 *     connected: bool
 * }
 * @phpstan-type OptmlBreakdown array{
 *     host: string,
 *     segments: array<string,string>,
 *     original_src: string,
 *     unchanged: bool
 * }
 * @phpstan-type OptmlInspectResult array{
 *     settings: OptmlSettings,
 *     last_result: array{
 *         has_result: bool,
 *         original?: string,
 *         transformed?: string,
 *         args_used?: array<string,mixed>,
 *         breakdown?: OptmlBreakdown,
 *         generated_at?: int
 *     }
 * }
 */
class TTP_Optimole_Result_Renderer {

	/**
	 * Human-readable labels for Optimole URL path segments.
	 *
	 * @var array<string,string>
	 */
	private const SEGMENT_LABELS = array(
		'w'   => 'Width',
		'h'   => 'Height',
		'q'   => 'Quality',
		'f'   => 'Format',
		'dpr' => 'Device pixel ratio',
		'rt'  => 'Resize type',
		'g'   => 'Gravity',
		'cb'  => 'Cache buster',
		'ig'  => 'Ignore',
		'bg'  => 'Background',
		'sm'  => 'Smart focus',
		'wm'  => 'Watermark',
	);

	/**
	 * Render the inspect output panel.
	 *
	 * @param mixed $result Inspect callback result (array or WP_Error).
	 * @return void
	 */
	public function render( $result ) {
		if ( is_wp_error( $result ) ) {
			echo '<p class="ttp-note ttp-note--error">' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}

		if ( ! is_array( $result ) ) {
			return;
		}

		$settings    = isset( $result['settings'] ) && is_array( $result['settings'] ) ? $result['settings'] : array();
		$last_result = isset( $result['last_result'] ) && is_array( $result['last_result'] ) ? $result['last_result'] : array();

		$this->render_settings_strip( $settings );

		if ( empty( $last_result['has_result'] ) ) {
			echo '<p class="ttp-empty">';
			esc_html_e( 'No transformation has been run yet. Fill in the form below and click Run to see how Optimole rewrites the URL.', 'themeisle-tester' );
			echo '</p>';
			return;
		}

		$this->render_last_result( $last_result );
	}

	/**
	 * Render the compact active-settings strip.
	 *
	 * @param array<string,mixed> $settings Settings snapshot.
	 * @return void
	 */
	private function render_settings_strip( array $settings ) {
		if ( empty( $settings ) ) {
			return;
		}

		$connected = ! empty( $settings['connected'] );

		echo '<p class="ttp-optimole-settings">';
		echo '<strong>' . esc_html__( 'Active Optimole settings:', 'themeisle-tester' ) . '</strong> ';

		$parts = array();

		if ( ! $connected ) {
			$parts[] = '<span class="ttp-optimole-settings__warn">' . esc_html__( 'Not connected to Optimole API', 'themeisle-tester' ) . '</span>';
		}

		$parts[] = sprintf(
			/* translators: %s: quality setting value (e.g. auto, 85). */
			esc_html__( 'Quality: %s', 'themeisle-tester' ),
			esc_html( $this->format_value( $settings['quality'] ?? '' ) )
		);
		$parts[] = $this->format_toggle_label( __( 'Best format', 'themeisle-tester' ), $settings['best_format'] ?? '' );
		$parts[] = $this->format_toggle_label( __( 'Retina', 'themeisle-tester' ), $settings['retina'] ?? '' );
		$parts[] = $this->format_toggle_label( __( 'AVIF', 'themeisle-tester' ), $settings['avif'] ?? '' );
		$parts[] = $this->format_toggle_label( __( 'Strip metadata', 'themeisle-tester' ), $settings['strip_meta'] ?? '' );
		$parts[] = sprintf(
			/* translators: %s: yes/no whether a watermark is configured. */
			esc_html__( 'Watermark: %s', 'themeisle-tester' ),
			esc_html( ! empty( $settings['watermark_on'] ) ? __( 'on', 'themeisle-tester' ) : __( 'off', 'themeisle-tester' ) )
		);

		echo wp_kses(
			implode( ' &middot; ', $parts ),
			array(
				'span'   => array( 'class' => array() ),
				'strong' => array(),
			)
		);
		echo '</p>';
	}

	/**
	 * Render the last transformation result.
	 *
	 * @param array<string,mixed> $last Last-result payload.
	 * @return void
	 */
	private function render_last_result( array $last ) {
		$original    = isset( $last['original'] ) && is_string( $last['original'] ) ? $last['original'] : '';
		$transformed = isset( $last['transformed'] ) && is_string( $last['transformed'] ) ? $last['transformed'] : '';
		$breakdown   = isset( $last['breakdown'] ) && is_array( $last['breakdown'] ) ? $last['breakdown'] : array();
		$generated   = isset( $last['generated_at'] ) && is_numeric( $last['generated_at'] ) ? (int) $last['generated_at'] : 0;
		$unchanged   = ! empty( $breakdown['unchanged'] );

		echo '<section class="ttp-optimole-result" aria-label="' . esc_attr__( 'Generated Optimole transformation', 'themeisle-tester' ) . '">';
		echo '<header class="ttp-optimole-result__header">';
		echo '<span class="ttp-optimole-result__badge">' . esc_html__( 'Generated', 'themeisle-tester' ) . '</span>';
		echo '<span class="ttp-optimole-result__subtitle">' . esc_html__( 'Optimole tag-replacer output', 'themeisle-tester' ) . '</span>';
		echo '</header>';

		if ( $unchanged ) {
			echo '<p class="ttp-optimole-info">';
			esc_html_e( 'Optimole returned the URL unchanged. The URL is likely outside the allowed-domain list, already optimized, or filtered out at runtime.', 'themeisle-tester' );
			echo '</p>';
		}

		$this->render_url_block( __( 'Original URL', 'themeisle-tester' ), $original );
		$this->render_url_block( __( 'Transformed URL', 'themeisle-tester' ), $transformed );

		if ( ! $unchanged && '' !== $original && '' !== $transformed ) {
			$this->render_preview_pair( $original, $transformed );
		}

		$this->render_breakdown_table( $breakdown );

		if ( $generated > 0 ) {
			$formatted = wp_date( 'Y-m-d H:i:s', $generated );

			if ( is_string( $formatted ) ) {
				echo '<p class="ttp-optimole-result__timestamp">';
				printf(
					/* translators: %s: timestamp of the last transformation. */
					esc_html__( 'Last run: %s', 'themeisle-tester' ),
					esc_html( $formatted )
				);
				echo '</p>';
			}
		}

		echo '</section>';
	}

	/**
	 * Render a single labeled URL row.
	 *
	 * @param string $label Field label.
	 * @param string $url   URL string.
	 * @return void
	 */
	private function render_url_block( $label, $url ) {
		if ( '' === $url ) {
			return;
		}

		echo '<div class="ttp-optimole-result__url">';
		echo '<span class="ttp-optimole-result__url-label">' . esc_html( $label ) . '</span>';
		echo '<a class="ttp-optimole-result__url-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"><code>' . esc_html( $url ) . '</code></a>';
		echo '</div>';
	}

	/**
	 * Render side-by-side image previews.
	 *
	 * @param string $original    Original image URL.
	 * @param string $transformed Transformed Optimole URL.
	 * @return void
	 */
	private function render_preview_pair( $original, $transformed ) {
		echo '<div class="ttp-optimole-result__previews">';
		$this->render_preview_cell( __( 'Original', 'themeisle-tester' ), $original );
		$this->render_preview_cell( __( 'Optimole', 'themeisle-tester' ), $transformed );
		echo '</div>';
	}

	/**
	 * Render a single preview cell.
	 *
	 * @param string $label Cell label.
	 * @param string $url   Image URL.
	 * @return void
	 */
	private function render_preview_cell( $label, $url ) {
		echo '<figure class="ttp-optimole-result__preview">';
		echo '<figcaption class="ttp-optimole-result__preview-caption">' . esc_html( $label ) . '</figcaption>';
		echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $label ) . '" loading="lazy" referrerpolicy="no-referrer">';
		echo '</figure>';
	}

	/**
	 * Render the parsed-segments breakdown table.
	 *
	 * @param array<string,mixed> $breakdown Parsed breakdown.
	 * @return void
	 */
	private function render_breakdown_table( array $breakdown ) {
		$host     = isset( $breakdown['host'] ) && is_string( $breakdown['host'] ) ? $breakdown['host'] : '';
		$source   = isset( $breakdown['original_src'] ) && is_string( $breakdown['original_src'] ) ? $breakdown['original_src'] : '';
		$segments = isset( $breakdown['segments'] ) && is_array( $breakdown['segments'] ) ? $breakdown['segments'] : array();

		if ( '' === $host && '' === $source && empty( $segments ) ) {
			return;
		}

		echo '<table class="widefat striped ttp-optimole-result__table">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Component', 'themeisle-tester' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Key', 'themeisle-tester' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Value', 'themeisle-tester' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( '' !== $host ) {
			echo '<tr><td>' . esc_html__( 'CDN host', 'themeisle-tester' ) . '</td><td>host</td><td>' . esc_html( $host ) . '</td></tr>';
		}

		foreach ( $segments as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key || ! is_string( $value ) ) {
				continue;
			}

			$label = isset( self::SEGMENT_LABELS[ $key ] ) ? self::SEGMENT_LABELS[ $key ] : $key;

			echo '<tr>';
			echo '<td>' . esc_html( $label ) . '</td>';
			echo '<td>' . esc_html( $key ) . '</td>';
			echo '<td>' . esc_html( $value ) . '</td>';
			echo '</tr>';
		}

		if ( '' !== $source ) {
			echo '<tr><td>' . esc_html__( 'Original source', 'themeisle-tester' ) . '</td><td>src</td><td>' . esc_html( $source ) . '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Format a scalar value for inline display.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string
	 */
	private function format_value( $value ) {
		if ( is_scalar( $value ) ) {
			$out = (string) $value;
			return '' === $out ? '—' : $out;
		}

		return '—';
	}

	/**
	 * Format an enabled/disabled toggle as "Label: on|off".
	 *
	 * @param string $label Setting label.
	 * @param mixed  $value Raw setting value.
	 * @return string
	 */
	private function format_toggle_label( $label, $value ) {
		$state = ( 'enabled' === $value || true === $value || '1' === $value || 1 === $value )
			? __( 'on', 'themeisle-tester' )
			: __( 'off', 'themeisle-tester' );

		return sprintf( '%s: %s', $label, $state );
	}
}
