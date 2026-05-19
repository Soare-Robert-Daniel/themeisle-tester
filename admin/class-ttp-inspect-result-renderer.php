<?php
/**
 * Utility / Danger Utility inspect result renderer.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders inspect/run result key/value output for Dashboard cards.
 */
class TTP_Inspect_Result_Renderer {

	/**
	 * Render a generic result table.
	 *
	 * @param mixed $result Result.
	 * @return void
	 */
	public function render_result_table( $result ) {
		if ( is_wp_error( $result ) ) {
			echo '<p class="ttp-note ttp-note--error">' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}

		if ( ! is_array( $result ) ) {
			return;
		}

		if ( empty( $result ) ) {
			echo '<p class="ttp-empty">' . esc_html__( 'No data to display.', 'themeisle-tester' ) . '</p>';
			return;
		}

		echo '<dl class="ttp-defs">';
		foreach ( $result as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$display = is_scalar( $value ) ? (string) $value : '';

			echo '<dt>' . esc_html( (string) $key ) . '</dt><dd>' . esc_html( $display ) . '</dd>';
		}
		echo '</dl>';
	}
}
