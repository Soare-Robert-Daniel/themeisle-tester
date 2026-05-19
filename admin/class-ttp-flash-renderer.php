<?php
/**
 * Flash notice renderer for the Dashboard.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders flash notice markup for admin and REST morph responses.
 */
class TTP_Flash_Renderer {

	/**
	 * Render flash notice markup (inner content of #ttp-flash).
	 *
	 * @param mixed $result Action result, error, or null.
	 * @return void
	 */
	public function render_flash_markup( $result ) {
		if ( null === $result ) {
			return;
		}

		if ( is_wp_error( $result ) ) {
			echo '<div class="ttp-flash ttp-flash--error" role="alert" data-ttp-toast>';
			echo '<p class="ttp-flash__message">' . esc_html( $result->get_error_message() ) . '</p>';
			$this->render_flash_dismiss_button();
			echo '</div>';
			return;
		}

		if ( ! is_array( $result ) ) {
			return;
		}

		$message = isset( $result['message'] ) && is_string( $result['message'] ) ? $result['message'] : '';
		if ( '' === $message ) {
			return;
		}

		echo '<div class="ttp-flash ttp-flash--success" role="status" data-ttp-toast data-ttp-toast-autohide>';
		echo '<p class="ttp-flash__message">' . esc_html( $message ) . '</p>';

		$this->render_flash_dismiss_button();
		echo '</div>';
	}

	/**
	 * Render flash dismiss control.
	 *
	 * @return void
	 */
	private function render_flash_dismiss_button() {
		echo '<button type="button" class="ttp-flash__dismiss" data-ttp-toast-dismiss aria-label="' . esc_attr__( 'Dismiss notification', 'themeisle-tester' ) . '">&times;</button>';
	}
}
