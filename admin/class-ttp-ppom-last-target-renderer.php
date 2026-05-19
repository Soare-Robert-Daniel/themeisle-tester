<?php
/**
 * Renderer for the "Last generated PPOM test product" inline panel.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the persisted last-run record produced by the PPOM free-fields utility.
 */
class TTP_PPOM_Last_Target_Renderer {

	/**
	 * Render the inline panel for the last generated PPOM test product.
	 *
	 * @param mixed $result Inspect callback result (may be array or WP_Error).
	 * @return void
	 */
	public function render( $result ) {
		if ( is_wp_error( $result ) ) {
			echo '<p class="ttp-note ttp-note--error">' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}

		if ( ! is_array( $result ) || empty( $result['has_target'] ) ) {
			$this->render_empty_state();
			return;
		}

		$normalized = array();

		foreach ( $result as $key => $value ) {
			if ( is_string( $key ) ) {
				$normalized[ $key ] = $value;
			}
		}

		$this->render_target_panel( $normalized );
	}

	/**
	 * Render the empty state shown when no test product has been generated.
	 *
	 * @return void
	 */
	private function render_empty_state() {
		echo '<p class="ttp-note">' . esc_html__( 'No PPOM test product has been generated yet. Click Run to create one.', 'themeisle-tester' ) . '</p>';
	}

	/**
	 * Render the panel with a link to the last generated product.
	 *
	 * @param array<string,mixed> $result Inspect callback result.
	 * @return void
	 */
	private function render_target_panel( array $result ) {
		$product_id    = isset( $result['product_id'] ) && is_numeric( $result['product_id'] ) ? (int) $result['product_id'] : 0;
		$ppom_group_id = isset( $result['ppom_group_id'] ) && is_numeric( $result['ppom_group_id'] ) ? (int) $result['ppom_group_id'] : 0;
		$product_url   = isset( $result['product_url'] ) && is_string( $result['product_url'] ) ? $result['product_url'] : '';
		$product_title = isset( $result['product_title'] ) && is_string( $result['product_title'] ) ? $result['product_title'] : '';
		$generated_at  = isset( $result['generated_at'] ) && is_numeric( $result['generated_at'] ) ? (int) $result['generated_at'] : 0;

		$label = '' !== $product_title
			? $product_title
			/* translators: %d: WooCommerce product ID. */
			: sprintf( __( 'Product #%d', 'themeisle-tester' ), $product_id );

		echo '<div class="ttp-ppom-last-target">';
		echo '<p class="ttp-ppom-last-target__heading">' . esc_html__( 'Last generated test product', 'themeisle-tester' ) . '</p>';

		if ( '' !== $product_url ) {
			echo '<p class="ttp-ppom-last-target__link">';
			echo '<a href="' . esc_url( $product_url ) . '" target="_blank" rel="noopener noreferrer">';
			echo esc_html( $label );
			echo '</a>';
			echo '</p>';
		} else {
			echo '<p class="ttp-ppom-last-target__link">' . esc_html( $label ) . '</p>';
		}

		echo '<dl class="ttp-defs ttp-defs--compact ttp-ppom-last-target__meta">';

		if ( $product_id > 0 ) {
			echo '<dt>' . esc_html__( 'Product ID', 'themeisle-tester' ) . '</dt>';
			echo '<dd>' . esc_html( (string) $product_id ) . '</dd>';
		}

		if ( $ppom_group_id > 0 ) {
			echo '<dt>' . esc_html__( 'PPOM group ID', 'themeisle-tester' ) . '</dt>';
			echo '<dd>' . esc_html( (string) $ppom_group_id ) . '</dd>';
		}

		if ( $generated_at > 0 ) {
			$formatted = wp_date( 'Y-m-d H:i:s', $generated_at );

			if ( is_string( $formatted ) ) {
				echo '<dt>' . esc_html__( 'Generated at', 'themeisle-tester' ) . '</dt>';
				echo '<dd>' . esc_html( $formatted ) . '</dd>';
			}
		}

		echo '</dl>';
		echo '</div>';
	}
}
