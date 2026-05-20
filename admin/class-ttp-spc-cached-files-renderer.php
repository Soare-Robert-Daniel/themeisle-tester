<?php
/**
 * Renderer for the Super Page Cache "Cached HTML files" inspect widget.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the cached-files inspect output as a wp-admin table with size + mtime.
 *
 * @phpstan-type SpcFileRow array{
 *     name: string,
 *     size: int,
 *     size_human: string,
 *     mtime: int,
 *     mtime_human: string,
 *     url: string
 * }
 */
class TTP_SPC_Cached_Files_Renderer {

	/**
	 * Render the cached files inspect panel.
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

		$directory        = isset( $result['directory'] ) && is_string( $result['directory'] ) ? $result['directory'] : '';
		$directory_exists = ! empty( $result['directory_exists'] );
		$files            = isset( $result['files'] ) && is_array( $result['files'] ) ? $result['files'] : array();

		if ( ! $directory_exists ) {
			echo '<p class="ttp-note">';
			echo esc_html__( 'The Super Page Cache fallback-cache directory does not exist yet. Visit a frontend page while caching is enabled to populate it.', 'themeisle-tester' );
			echo '</p>';

			if ( '' !== $directory ) {
				echo '<p class="ttp-note"><code>' . esc_html( $directory ) . '</code></p>';
			}

			return;
		}

		if ( empty( $files ) ) {
			echo '<p class="ttp-empty">' . esc_html__( 'No cached HTML files in this directory yet.', 'themeisle-tester' ) . '</p>';

			if ( '' !== $directory ) {
				echo '<p class="ttp-note"><code>' . esc_html( $directory ) . '</code></p>';
			}

			return;
		}

		echo '<table class="widefat striped ttp-spc-cached-files">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'File', 'themeisle-tester' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Size', 'themeisle-tester' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Modified', 'themeisle-tester' ) . '</th>';
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'themeisle-tester' ) . '</span></th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}

			$this->render_row( $file );
		}

		echo '</tbody></table>';
	}

	/**
	 * Render a single file row.
	 *
	 * @param array<int|string,mixed> $file Sanitized file entry.
	 * @return void
	 */
	private function render_row( array $file ) {
		$name        = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';
		$size_human  = isset( $file['size_human'] ) && is_string( $file['size_human'] ) ? $file['size_human'] : '';
		$mtime_human = isset( $file['mtime_human'] ) && is_string( $file['mtime_human'] ) ? $file['mtime_human'] : '';
		$url         = isset( $file['url'] ) && is_string( $file['url'] ) ? $file['url'] : '';

		echo '<tr>';
		echo '<td><code>' . esc_html( $name ) . '</code></td>';
		echo '<td>' . esc_html( $size_human ) . '</td>';
		echo '<td>' . esc_html( $mtime_human ) . '</td>';
		echo '<td>';

		if ( '' !== $url ) {
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">';
			echo esc_html__( 'View', 'themeisle-tester' );
			echo '</a>';
			echo ' <span aria-hidden="true">·</span> ';
			echo '<a href="' . esc_url( $url ) . '" download="' . esc_attr( $name ) . '">';
			echo esc_html__( 'Download', 'themeisle-tester' );
			echo '</a>';
		} else {
			echo '<span class="ttp-note">' . esc_html__( 'Unavailable', 'themeisle-tester' ) . '</span>';
		}

		echo '</td>';
		echo '</tr>';
	}
}
